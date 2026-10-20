<?php

namespace App\Domain\Assessments\Actions;

use App\Domain\Assessments\Models\Question;
use App\Domain\Assessments\Models\QuestionOption;
use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\QuizAttempt;
use App\Domain\Assessments\Models\AttemptAnswer;
use App\Domain\Assessments\Services\QuizGrader;
use App\Domain\Gamification\Contracts\XpLedger;
use App\Domain\Gamification\Models\Badge;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RF-11..RF-16: intentos Card-Flow con calificación en servidor.
 * RB-06: reintentos ilimitados, ordinal en servidor; XP = floor(base × 0.8^(n-1)) si passed.
 */
final readonly class TakeQuiz
{
    public function __construct(
        private QuizGrader $grader,
        private XpLedger $xpLedger,
        private \App\Domain\Gamification\Contracts\BadgeAwarder $badges,
        private \App\Domain\Gamification\Contracts\StreakService $streak,
    ) {}

    /** RF-11/RB-10: payload sin is_correct ni feedback de otras opciones. */
    public function show(Quiz $quiz): array
    {
        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'passing_score' => $quiz->passing_score,
            'questions' => $quiz->questions->map(fn ($q) => [
                'id' => $q->id,
                'statement' => $q->statement,
                'type' => $q->type,
                'options' => $q->options->map(fn ($o) => [
                    'id' => $o->id,
                    'option_text' => $o->option_text,
                ]), // SIN is_correct ni feedback (RB-10)
            ]),
        ];
    }

    public function start(User $user, Quiz $quiz): QuizAttempt
    {
        // Reanuda intento en progreso si existe (evita inflar attempt_number al refrescar)
        $existing = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // ordinal en servidor (RB-06)
        $next = (int) QuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->max('attempt_number') + 1;

        return QuizAttempt::query()->create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'attempt_number' => $next,
            'started_at' => now(),
        ]);
    }

    /** RF-13: respuesta con feedback inmediato de la opción elegida. */
    public function answer(User $user, QuizAttempt $attempt, int $questionId, int $optionId): array
    {
        abort_unless($attempt->status === 'in_progress', 409, 'ATTEMPT_NOT_ACTIVE');
        abort_unless($attempt->user_id === $user->id, 403, 'FORBIDDEN');

        $question = Question::query()->where('quiz_id', $attempt->quiz_id)->findOrFail($questionId);
        $option = QuestionOption::query()->where('question_id', $question->id)->findOrFail($optionId);

        return DB::transaction(function () use ($attempt, $question, $option): array {
            AttemptAnswer::query()->updateOrCreate(
                [
                    'quiz_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                ],
                [
                    'question_option_id' => $option->id,
                    'is_correct' => $option->is_correct,
                ],
            );

            // RF-13: feedback inmediato de la opción elegida (la correcta se revela solo si acertó)
            return [
                'is_correct' => $option->is_correct,
                'feedback' => $option->feedback ?? $question->explanation,
            ];
        });
    }

    /**
     * Submit: califica en servidor, otorga XP con decremento por reintento.
     * @return array{score: int, passed: bool, attempt_number: int, xp_awarded: bool, xp_amount: int}
     */
    public function submit(User $user, QuizAttempt $attempt): array
    {
        abort_unless($attempt->status === 'in_progress', 409, 'ATTEMPT_NOT_ACTIVE');
        abort_unless($attempt->user_id === $user->id, 403, 'FORBIDDEN');

        return DB::transaction(function () use ($user, $attempt): array {
            $grade = $this->grader->grade($attempt);

            $attempt->forceFill([
                'score' => $grade['score'],
                'submitted_at' => now(),
                'status' => 'submitted',
            ])->save();

            $xpAwarded = false;
            $xpAmount = 0;

            // RB-06: cada intento aprobado otorga XP; los reintentos aplican −20 % acumulado
            if ($grade['passed']) {
                $base = 50; // RF-16: recompensa base de quiz (ajustable en admin)

                // RB-06/15: intento n >= 2 → floor(base × 0.8^(n-1))
                $xp = (int) floor($base * 0.8 ** ($attempt->attempt_number - 1));

                $this->xpLedger->award(
                    userId: $user->id,
                    delta: $xp,
                    source: 'quiz',
                    reason: 'quiz_passed',
                    metadata: ['quiz_id' => $attempt->quiz_id, 'attempt_number' => $attempt->attempt_number],
                );

                $attempt->forceFill(['xp_awarded' => true, 'xp_amount' => $xp])->save();
                $xpAwarded = true;
                $xpAmount = $xp;

                // RF-17: insignias por reglas + actividad de racha (RB-16)
                $awardedBadgeIds = $this->badges->evaluate('quiz_passed', [
                    'user' => $user,
                    'user_id' => $user->id,
                    'quiz_id' => $attempt->quiz_id,
                    'attempt_number' => $attempt->attempt_number,
                    'count' => 1,
                ]);
                $this->streak->markActivity($user->id);

                $newBadges = ! empty($awardedBadgeIds)
                    ? Badge::query()->whereIn('id', $awardedBadgeIds)->get(['id', 'slug', 'title', 'description'])->toArray()
                    : [];
            } else {
                $newBadges = [];
            }

            return [
                'score' => $grade['score'],
                'passed' => $grade['passed'],
                'attempt_number' => $attempt->attempt_number,
                'xp_awarded' => $xpAwarded,
                'xp_amount' => $xpAmount,
                'new_badges' => $newBadges,
            ];
        });
    }

    /** RF-14: historial de intentos del usuario para un quiz. */
    public function history(User $user, Quiz $quiz): array
    {
        return QuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->where('status', 'submitted')
            ->orderByDesc('attempt_number')
            ->get(['id', 'attempt_number', 'score', 'submitted_at'])
            ->toArray();
    }
}
