<?php

namespace App\Domain\Gamification\Contracts;

/**
 * RF-18/RB-08: el ranking se recalcula solo tras evaluación validada (quiz).
 * Derivado de xp_ledger — sin tabla snapshot (KISS para n<=500).
 */
interface RankingService
{
    /**
     * @return list<array{user_id: int, name: string, xp_total: int, xp_level: int, position: int}>
     */
    public function global(int $limit = 50, int $offset = 0): array;

    /** @return list<array{user_id: int, name: string, xp_total: int, xp_level: int, position: int}> */
    public function forClassroom(int $classroomId, int $limit = 50, int $offset = 0): array;

    /** Posición global del usuario (1-based). */
    public function positionOf(int $userId): int;
}
