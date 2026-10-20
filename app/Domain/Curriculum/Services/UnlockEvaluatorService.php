<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Assessments\Models\QuizAttempt;
use App\Domain\Curriculum\Contracts\UnlockEvaluator;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Users\Models\User;

/**
 * RB-05 implementado:
 *  - la lección N+1 requiere que la N esté completed (la lección-evaluación
 *    con requires_quiz_pass cuenta como completed cuando el quiz del módulo está aprobado >= 70)
 *  - un módulo se desbloquea cuando el cuestionario del módulo anterior de la misma misión
 *    fue aprobado (puntaje >= passing_score)
 */
final class UnlockEvaluatorService implements UnlockEvaluator
{
    public function __construct(
        private readonly \App\Domain\Gamification\Contracts\XpLedger $xpLedger,
    ) {}

    public function canAccess(User $user, Lesson $lesson): bool
    {
        if (! $this->hasCompletedPretestIfRequired($user)) {
            return false;
        }

        $module = $lesson->module()->first();

        if ($module === null || $module->status !== 'published') {
            return false;
        }

        // El módulo previo debe tener su cuestionario aprobado
        if (! $this->previousModulesPassed($user, $module)) {
            return false;
        }

        // Lecciones previas del módulo deben estar completed
        $prevLessons = $module->lessons()
            ->where('order_index', '<', $lesson->order_index)
            ->orderBy('order_index')
            ->get(['id', 'order_index', 'requires_quiz_pass']);

        if ($prevLessons->isEmpty()) {
            return true;
        }

        $completedIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->whereIn('lesson_id', $prevLessons->pluck('id'))
            ->pluck('lesson_id');

        $quizPassed = null;

        foreach ($prevLessons as $prev) {
            if ($completedIds->contains($prev->id)) {
                continue;
            }

            if ($prev->requires_quiz_pass) {
                $quizPassed ??= $this->hasPassedModuleQuiz($user, $module);
                if ($quizPassed) {
                    continue;
                }
            }

            return false;
        }

        return true;
    }

    /** Cuestionario del módulo aprobado por el usuario (mejor intento enviado >= passing_score). */
    public function hasPassedModuleQuiz(User $user, Module $module): bool
    {
        $quiz = $module->quiz()->first();

        // Un módulo sin cuestionario no bloquea la progresión
        if ($quiz === null) {
            return true;
        }

        return QuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->where('status', 'submitted')
            ->where('score', '>=', $quiz->passing_score)
            ->exists();
    }

    /** Todos los módulos publicados anteriores de la misma misión tienen su cuestionario aprobado. */
    private function previousModulesPassed(User $user, Module $module): bool
    {
        $previous = Module::query()
            ->where('mission_id', $module->mission_id)
            ->where('status', 'published')
            ->where('order_index', '<', $module->order_index)
            ->orderBy('order_index')
            ->get();

        foreach ($previous as $prevModule) {
            if (! $this->hasPassedModuleQuiz($user, $prevModule)) {
                return false;
            }
        }

        return true;
    }

    /**
     * El estudiante debe completar el Pre-test diagnóstico antes de iniciar el Módulo 1.
     * Si el entorno no tiene ningún Pre-test activo sembrado (ej. tests unitarios aislados), no bloquea.
     */
    private function hasCompletedPretestIfRequired(User $user): bool
    {
        if ($user->learning_track !== null) {
            return true;
        }

        if (! \App\Domain\Diagnostic\Models\DiagnosticTest::query()->ofKind('pre')->exists()) {
            return true;
        }

        return \App\Domain\Diagnostic\Models\DiagnosticAttempt::query()
            ->where('user_id', $user->id)
            ->where('kind', 'pre')
            ->where('status', 'completed')
            ->exists()
            || User::query()->whereKey($user->id)->whereNotNull('learning_track')->exists();
    }
}
