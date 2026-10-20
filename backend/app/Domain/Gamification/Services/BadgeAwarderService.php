<?php

namespace App\Domain\Gamification\Services;

use App\Domain\Assessments\Models\QuizAttempt;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Gamification\Contracts\BadgeAwarder;
use App\Domain\Gamification\Models\Badge;
use App\Domain\Gamification\Models\BadgeRule;
use App\Domain\Gamification\Models\UserBadge;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Evalúa y otorga insignias del sistema (RF-17).
 * RB-07: Jamás duplica (UNIQUE user_id, badge_id).
 */
final class BadgeAwarderService implements BadgeAwarder
{
    /** @return list<int> */
    public function evaluate(string $event, array $context = []): array
    {
        /** @var User|null $user */
        $user = $context['user'] ?? null;
        if (! $user && isset($context['user_id'])) {
            $user = User::query()->find($context['user_id']);
        }
        $user ??= Auth::user();

        if ($user === null) {
            return [];
        }

        return $this->evaluateAll($user);
    }

    /**
     * Evalúa el progreso del usuario y otorga cualquier insignia ganada.
     * @return list<int>
     */
    public function evaluateAll(User $user): array
    {
        $awarded = [];
        $allBadges = Badge::query()->with('rules')->get();

        // 1. Cuestionarios aprobados por el usuario (score >= 70)
        $passedAttempts = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', 'submitted')
            ->where('score', '>=', 70)
            ->get();

        $totalPassedQuizzesCount = $passedAttempts->pluck('quiz_id')->unique()->count();
        $totalPassedAttemptsCount = $passedAttempts->count();

        // 2. Lecciones completadas por el usuario
        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->pluck('lesson_id');

        foreach ($allBadges as $badge) {
            $shouldAward = false;

            switch ($badge->slug) {
                case 'novato-logica':
                    // Aprobó su primer cuestionario
                    if ($totalPassedQuizzesCount >= 1 || $totalPassedAttemptsCount >= 1) {
                        $shouldAward = true;
                    }
                    break;

                case 'depurador-elite':
                    // Aprobó un cuestionario en el primer intento enviado
                    $hasPassedFirstAttempt = QuizAttempt::query()
                        ->where('user_id', $user->id)
                        ->where('status', 'submitted')
                        ->where('score', '>=', 70)
                        ->where(function ($q) use ($user) {
                            $q->where('attempt_number', 1)
                              ->orWhereIn('id', function ($sub) use ($user) {
                                  $sub->selectRaw('MIN(id)')
                                      ->from('quiz_attempts')
                                      ->where('user_id', $user->id)
                                      ->where('status', 'submitted')
                                      ->groupBy('quiz_id');
                              });
                        })
                        ->exists();

                    if ($hasPassedFirstAttempt) {
                        $shouldAward = true;
                    }
                    break;

                case 'explorador-variables':
                    // Completó el módulo de variables y tipos de datos (Módulo 1)
                    $varModule = Module::query()
                        ->where(function ($q) {
                            $q->where('title', 'like', '%Variable%')
                              ->orWhere('order_index', 0);
                        })
                        ->first();

                    if ($varModule) {
                        $varQuiz = $varModule->quiz()->first();
                        $passedVarQuiz = $varQuiz && $passedAttempts->contains('quiz_id', $varQuiz->id);
                        $varLessons = $varModule->lessons()->where('requires_quiz_pass', false)->pluck('id');
                        $allVarLessonsCompleted = $varLessons->isNotEmpty() && $varLessons->every(fn ($id) => $completedLessonIds->contains($id));

                        if ($passedVarQuiz || $allVarLessonsCompleted || $completedLessonIds->count() >= 3) {
                            $shouldAward = true;
                        }
                    }
                    break;

                case 'mago-del-control':
                    // Dominó estructuras condicionales (Módulo 2)
                    $condModule = Module::query()
                        ->where(function ($q) {
                            $q->where('title', 'like', '%Condicional%')
                              ->orWhere('order_index', 1);
                        })
                        ->first();

                    if ($condModule) {
                        $condQuiz = $condModule->quiz()->first();
                        $passedCondQuiz = $condQuiz && $passedAttempts->contains('quiz_id', $condQuiz->id);
                        $condLessons = $condModule->lessons()->where('requires_quiz_pass', false)->pluck('id');
                        $allCondLessonsCompleted = $condLessons->isNotEmpty() && $condLessons->every(fn ($id) => $completedLessonIds->contains($id));

                        if ($passedCondQuiz || $allCondLessonsCompleted) {
                            $shouldAward = true;
                        }
                    }
                    break;

                case 'maestro-del-bucle':
                    // Dominó estructuras cíclicas / bucles (Módulo 3)
                    $loopModule = Module::query()
                        ->where(function ($q) {
                            $q->where('title', 'like', '%Cíclica%')
                              ->orWhere('title', 'like', '%Bucle%')
                              ->orWhere('title', 'like', '%Ciclo%')
                              ->orWhere('order_index', 2);
                        })
                        ->first();

                    if ($loopModule) {
                        $loopQuiz = $loopModule->quiz()->first();
                        $passedLoopQuiz = $loopQuiz && $passedAttempts->contains('quiz_id', $loopQuiz->id);
                        $loopLessons = $loopModule->lessons()->where('requires_quiz_pass', false)->pluck('id');
                        $allLoopLessonsCompleted = $loopLessons->isNotEmpty() && $loopLessons->every(fn ($id) => $completedLessonIds->contains($id));

                        if ($passedLoopQuiz || $allLoopLessonsCompleted) {
                            $shouldAward = true;
                        }
                    }
                    break;

                case 'maestro-pseudocodigo':
                    // Aprobó los 3 cuestionarios de módulo (ruta completa) o 5 intentos aprobados
                    if ($totalPassedQuizzesCount >= 3 || $totalPassedAttemptsCount >= 5) {
                        $shouldAward = true;
                    }
                    break;

                default:
                    // Reglas dinámicas adicionales en base de datos
                    foreach ($badge->rules as $rule) {
                        if (! $rule->is_active) {
                            continue;
                        }

                        $conds = $rule->conditions ?? [];
                        if ($rule->event === 'quiz_passed') {
                            $reqCount = (int) ($conds['count'] ?? 1);
                            $reqAttempt = (int) ($conds['attempt_number'] ?? 0);

                            if ($totalPassedAttemptsCount >= $reqCount) {
                                if ($reqAttempt <= 0 || $passedAttempts->contains('attempt_number', $reqAttempt)) {
                                    $shouldAward = true;
                                }
                            }
                        } elseif ($rule->event === 'lesson_completed') {
                            $reqCount = (int) ($conds['count'] ?? 1);
                            if ($completedLessonIds->count() >= $reqCount) {
                                $shouldAward = true;
                            }
                        }
                    }
                    break;
            }

            if ($shouldAward) {
                if ($this->awardBadge($user->id, $badge->id)) {
                    $awarded[] = $badge->id;
                }
            }
        }

        return $awarded;
    }

    private function awardBadge(int $userId, int $badgeId): bool
    {
        $exists = UserBadge::query()
            ->where('user_id', $userId)
            ->where('badge_id', $badgeId)
            ->exists();

        if ($exists) {
            return false;
        }

        UserBadge::query()->create([
            'user_id' => $userId,
            'badge_id' => $badgeId,
            'awarded_at' => now(),
            'notified_at' => null,
        ]);

        return true;
    }
}
