<?php

namespace App\Domain\Gamification\Contracts;

use App\Domain\Users\Models\User;

/**
 * RF-17: insignias automáticas por reglas declarativas en BD (§3.2).
 */
interface BadgeAwarder
{
    /**
     * Evalúa las reglas activas para el evento dado y otorga insignias nuevas.
     * RB-07: jamás duplica (UNIQUE(user_id, badge_id) + firstOrCreate).
     *
     * @return list<int> ids de insignias otorgadas en esta llamada
     */
    public function evaluate(string $event, array $context = []): array;

    /**
     * Evalúa el estado global del usuario y otorga retroactivamente cualquier insignia pendiente.
     *
     * @return list<int> ids de insignias otorgadas
     */
    public function evaluateAll(User $user): array;
}
