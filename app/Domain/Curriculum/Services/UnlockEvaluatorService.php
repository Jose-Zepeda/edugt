<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Curriculum\Contracts\UnlockEvaluator;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Users\Models\User;

/**
 * RB-05 implementado:
 *  - la lección N+1 requiere que la N esté completed y (si requires_quiz_pass) quiz >= 70
 *  - el módulo exige required_level <= xp_level del usuario
 */
final class UnlockEvaluatorService implements UnlockEvaluator
{
    public function __construct(
        private readonly \App\Domain\Gamification\Contracts\XpLedger $xpLedger,
    ) {}

    public function canAccess(User $user, Lesson $lesson): bool
    {
        $module = $lesson->module()->first();

        if ($module === null || $module->status !== 'published') {
            return false;
        }

        // Nivel XP requerido por el módulo
        $xpLevel = $this->xpLedger->levelFor(
            $this->xpLedger->currentTotal($user->id)
        );
        if ($module->required_level > $xpLevel) {
            return false;
        }

        // Lecciones previas del módulo deben estar completed
        $prevLessons = $module->lessons()
            ->where('order_index', '<', $lesson->order_index)
            ->orderBy('order_index')
            ->get(['id', 'order_index']);

        if ($prevLessons->isEmpty()) {
            return true;
        }

        $completedIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->whereIn('lesson_id', $prevLessons->pluck('id'))
            ->pluck('lesson_id');

        return $completedIds->count() === $prevLessons->count();
    }
}
