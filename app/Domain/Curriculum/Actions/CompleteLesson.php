<?php

namespace App\Domain\Curriculum\Actions;

use App\Domain\Curriculum\Contracts\UnlockEvaluator;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Gamification\Contracts\XpLedger;
use App\Domain\Gamification\Contracts\StreakService;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * RF-9, RF-10: registrar visita/completado de lección, tiempo invertido.
 * XP de lección solo en el primer completado.
 */
final readonly class CompleteLesson
{
    public function __construct(
        private UnlockEvaluator $unlockEvaluator,
        private XpLedger $xpLedger,
        private StreakService $streak,
    ) {}

    public function start(User $user, Lesson $lesson): LessonProgress
    {
        abort_unless($this->unlockEvaluator->canAccess($user, $lesson), 403, 'LOCKED_CONTENT');

        return LessonProgress::query()->updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            [
                'started_at' => now(),
                'last_visited_at' => now(),
            ],
        );
    }

    /**
     * Marca la lección como completada y otorga XP una sola vez.
     * @return array{xp_awarded: bool, xp_amount: int, completed: bool}
     */
    public function execute(User $user, Lesson $lesson, int $timeSpentSeconds): array
    {
        abort_unless($this->unlockEvaluator->canAccess($user, $lesson), 403, 'LOCKED_CONTENT');

        $timeSpent = max(0, $timeSpentSeconds);

        return DB::transaction(function () use ($user, $lesson, $timeSpent): array {
            /** @var LessonProgress $progress */
            $progress = LessonProgress::query()->firstOrCreate(
                ['user_id' => $user->id, 'lesson_id' => $lesson->id],
                ['started_at' => now(), 'last_visited_at' => now(), 'time_spent_seconds' => 0],
            );

            $xpAwarded = false;

            if (! $progress->completed) {
                $progress->forceFill([
                    'completed' => true,
                    'completed_at' => now(),
                    'time_spent_seconds' => $progress->time_spent_seconds + $timeSpent,
                    'last_visited_at' => now(),
                ])->save();
                $xpAwarded = true;

                $this->xpLedger->award(
                    userId: $user->id,
                    delta: $lesson->xp_reward,
                    source: 'lesson',
                    reason: 'lesson_completed',
                    metadata: ['lesson_id' => $lesson->id],
                );
                $this->streak->markActivity($user->id);
            } else {
                $progress->forceFill([
                    'time_spent_seconds' => $progress->time_spent_seconds + $timeSpent,
                    'last_visited_at' => now(),
                ])->save();
            }

            return [
                'xp_awarded' => $xpAwarded,
                'xp_amount' => $xpAwarded ? $lesson->xp_reward : 0,
                'completed' => true,
            ];
        });
    }
}
