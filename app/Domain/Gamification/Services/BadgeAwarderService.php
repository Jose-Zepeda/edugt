<?php

namespace App\Domain\Gamification\Services;

use App\Domain\Gamification\Contracts\BadgeAwarder;
use App\Domain\Gamification\Models\Badge;
use App\Domain\Gamification\Models\BadgeRule;
use App\Domain\Gamification\Models\UserBadge;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Evalúa badge_rules declarativas. Eventos conocidos:
 *  - quiz_passed      {quiz_id, module_id, attempt_number}
 *  - lesson_completed {lesson_id, module_id}
 *  - streak_days      {days}
 * Condición típica: {"count": N} o {"module_id": X} o {"quiz_id": Y}.
 */
final class BadgeAwarderService implements BadgeAwarder
{
    /** @return list<int> */
    public function evaluate(string $event, array $context = []): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null) {
            return [];
        }

        $rules = BadgeRule::query()
            ->with('badge')
            ->where('event', $event)
            ->where('is_active', true)
            ->get();

        $awarded = [];

        foreach ($rules as $rule) {
            if (! $this->matches($rule->conditions ?? [], $context)) {
                continue;
            }

            $exists = UserBadge::query()
                ->where('user_id', $user->id)
                ->where('badge_id', $rule->badge_id)
                ->exists();
            if ($exists) {
                continue; // RB-07: no duplicar
            }

            UserBadge::query()->create([
                'user_id' => $user->id,
                'badge_id' => $rule->badge_id,
                'awarded_at' => now(),
            ]);

            $awarded[] = $rule->badge_id;
        }

        return $awarded;
    }

    /**
     * Todas las condiciones deben cumplirse:
     *  - "count": N → el contexto debe llevar "count" >= N (acumulado fuera)
     *  - "module_id": X / "quiz_id": Y → igualdad
     */
    private function matches(array $conditions, array $context): bool
    {
        foreach ($conditions as $key => $expected) {
            if ($key === 'count') {
                if ((int) ($context['count'] ?? 0) < (int) $expected) {
                    return false;
                }
                continue;
            }

            if (($context[$key] ?? null) != $expected) { // comparación laxa int/string
                return false;
            }
        }

        return true;
    }
}
