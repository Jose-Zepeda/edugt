<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Diagnostic\Actions\TakeDiagnosticTest;
use App\Domain\Diagnostic\Models\DiagnosticAttempt;
use App\Domain\Diagnostic\Models\DiagnosticTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RF-6/RF-28: pretest y postest.
 */
final class DiagnosticController extends Controller
{
    public function __construct(private readonly TakeDiagnosticTest $takeTest) {}

    /** GET /api/v1/diagnostic/tests/{kind} — devuelve preguntas SIN correct_index (RB-10) */
    public function show(Request $request, string $kind): JsonResponse
    {
        $user = $request->user();

        abort_unless(in_array($kind, ['pre', 'post'], true), 422, 'kind inválido');

        $attempt = ($this->takeTest)->start($user, $kind);

        $test = DiagnosticTest::query()->ofKind($kind)->with('questions')->first();

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->id,
                'kind' => $kind,
                'time_limit_minutes' => $test->time_limit_minutes,
                'question_count' => $test->question_count,
                'answered' => $attempt->answers()->count(),
                'questions' => $test->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'dimension' => $q->dimension,
                    'statement' => $q->statement,
                    'options' => $q->options, // sin índice correcto (RB-10)
                ]),
            ],
        ]);
    }

    /** POST /api/v1/diagnostic/tests/{kind}/answers {question_id, selected_index} */
    public function answer(Request $request, string $kind): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'selected_index' => ['required', 'integer', 'min:0'],
        ]);

        $attempt = DiagnosticAttempt::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $result = ($this->takeTest)->answer($attempt, $data['question_id'], $data['selected_index']);

        return response()->json($result);
    }

    /** POST /api/v1/diagnostic/tests/{kind}/submit */
    public function submit(Request $request, string $kind): JsonResponse
    {
        $user = $request->user();

        $attempt = DiagnosticAttempt::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $attempt = ($this->takeTest)->submit($attempt);

        $preScore = null;
        $gain = null;
        if ($kind === 'post') {
            $preAttempt = DiagnosticAttempt::query()
                ->where('user_id', $user->id)
                ->where('kind', 'pre')
                ->where('status', 'completed')
                ->first();
            if ($preAttempt !== null) {
                $preScore = (int) $preAttempt->score_total;
                $gain = (int) $attempt->score_total - $preScore;
            }
        }

        return response()->json([
            'score_total' => $attempt->score_total,
            'pre_score' => $preScore,
            'gain' => $gain,
            'dimension_scores' => $attempt->dimension_scores,
            'learning_track' => $kind === 'pre' ? $user->refresh()->learning_track : null,
        ]);
    }
}
