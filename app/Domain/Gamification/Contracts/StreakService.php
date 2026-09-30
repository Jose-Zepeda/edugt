<?php

namespace App\Domain\Gamification\Contracts;

/**
 * RF-17: racha por día local America/Guatemala (RB-16).
 */
interface StreakService
{
    /** Marca actividad "hoy" (login o completar lesson/quiz). Idempotente por día local. */
    public function markActivity(int $userId, ?\Illuminate\Support\Carbon $localDate = null): void;

    public function current(int $userId): int;

    public function longest(int $userId): int;
}
