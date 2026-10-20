<?php

namespace App\Domain\Diagnostic\Actions;

use App\Domain\Diagnostic\Models\DiagnosticAnswer;
use App\Domain\Diagnostic\Models\DiagnosticAttempt;
use App\Domain\Diagnostic\Models\DiagnosticQuestion;
use App\Domain\Diagnostic\Models\DiagnosticTest;
use App\Domain\Diagnostic\Services\DiagnosticLevelAssigner;
use App\Domain\Users\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RF-6, RF-28: pretest/postest — reanudable, una sola vez, sin gamificación (RB-11).
 */
final readonly class TakeDiagnosticTest
{
    public function __construct(private DiagnosticLevelAssigner $assigner) {}

    /** Inicia (o reanuda) el intento del test kind=pre|post para el usuario. */
    public function start(User $user, string $kind): DiagnosticAttempt
    {
        $test = DiagnosticTest::query()->ofKind($kind)->first();

        if ($test === null) {
            throw ValidationException::withMessages([
                'kind' => ["No hay una prueba '{$kind}' disponible."],
            ]);
        }

        // RF-6: una sola presentación por kind — si ya está completed, 409
        $existing = DiagnosticAttempt::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->first();

        if ($existing !== null && $existing->status === 'completed') {
            throw new HttpResponseException(response()->json([
                'message' => 'Ya completaste esta prueba diagnóstica.',
                'code' => 'DIAGNOSTIC_ALREADY_COMPLETED',
            ], 409));
        }

        if ($existing !== null) {
            return $existing; // reanudar (RF-28: guardar avance)
        }

        return DiagnosticAttempt::query()->create([
            'user_id' => $user->id,
            'diagnostic_test_id' => $test->id,
            'kind' => $kind,
            'started_at' => now(),
        ]);
    }

    /**
     * Registra la respuesta de UNA pregunta (guarda avance; reanudable).
     * @return array{is_correct: bool}
     */
    public function answer(DiagnosticAttempt $attempt, int $questionId, int $selectedIndex): array
    {
        abort_unless($attempt->status === 'in_progress', 409, 'DIAGNOSTIC_NOT_ACTIVE');

        $question = DiagnosticQuestion::query()
            ->where('diagnostic_test_id', $attempt->diagnostic_test_id)
            ->findOrFail($questionId);

        return DB::transaction(function () use ($attempt, $question, $selectedIndex): array {
            $isCorrect = $question->correct_index === $selectedIndex;

            DiagnosticAnswer::query()->updateOrCreate(
                [
                    'diagnostic_attempt_id' => $attempt->id,
                    'diagnostic_question_id' => $question->id,
                ],
                [
                    'selected_index' => $selectedIndex,
                    'is_correct' => $isCorrect,
                    'answered_at' => now(),
                ],
            );

            // RB-11: sin XP, sin insignias, sin ranking — solo el registro
            return ['is_correct' => $isCorrect];
        });
    }

    /** Cierra el intento, calcula score por dimensión y (si es pre) asigna learning_track. */
    public function submit(DiagnosticAttempt $attempt): DiagnosticAttempt
    {
        abort_unless($attempt->status === 'in_progress', 409, 'DIAGNOSTIC_NOT_ACTIVE');

        return DB::transaction(function () use ($attempt): DiagnosticAttempt {
            $test = $attempt->test()->with('questions')->first();
            $questions = $test->questions;

            $answers = $attempt->answers()->get()->keyBy('diagnostic_question_id');

            $dimensionScores = [];
            foreach ($questions as $q) {
                $dim = $q->dimension;
                $dimensionScores[$dim]['total'] = ($dimensionScores[$dim]['total'] ?? 0) + 1;
                $answer = $answers->get($q->id);
                if ($answer !== null && $answer->is_correct) {
                    $dimensionScores[$dim]['correct'] = ($dimensionScores[$dim]['correct'] ?? 0) + 1;
                }
            }
            foreach ($dimensionScores as $dim => &$s) {
                $s['correct'] = $s['correct'] ?? 0;
            }
            unset($s);

            $totalCorrect = array_sum(array_column($dimensionScores, 'correct'));
            $scoreTotal = $questions->isNotEmpty()
                ? (int) round($totalCorrect * 100 / $questions->count())
                : 0;

            $attempt->forceFill([
                'dimension_scores' => $dimensionScores,
                'score_total' => $scoreTotal,
                'submitted_at' => now(),
                'status' => 'completed',
            ])->save();

            // RF-7: solo el pretest asigna la ruta de aprendizaje
            if ($attempt->kind === 'pre') {
                $user = User::query()->findOrFail($attempt->user_id);
                $this->assigner->apply($attempt, $user);
            }

            return $attempt->refresh();
        });
    }
}
