<?php

namespace App\Domain\Gamification\Services;

use App\Domain\Gamification\Contracts\RankingService;
use Illuminate\Support\Facades\DB;

/**
 * Ranking derivado del ledger (server-authoritative, RB-08):
 * consulta agregada ordenada por xp_total DESC, sin snapshots.
 */
final class RankingServiceDb implements RankingService
{
    public function global(int $limit = 50, int $offset = 0): array
    {
        $rows = DB::table('user_xp_profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('u.status', 'active')
            ->orderByDesc('p.xp_total')
            ->orderBy('p.user_id')
            ->offset($offset)->limit($limit)
            ->get(['p.user_id', 'u.name', 'p.xp_total', 'p.xp_level']);

        return $rows->map(fn ($r, $i) => [
            'user_id' => $r->user_id,
            'name' => $r->name,
            'xp_total' => (int) $r->xp_total,
            'xp_level' => (int) $r->xp_level,
            'position' => $offset + $i + 1,
        ])->all();
    }

    public function forClassroom(int $classroomId, int $limit = 50, int $offset = 0): array
    {
        $rows = DB::table('classroom_students as cs')
            ->join('user_xp_profiles as p', 'p.user_id', '=', 'cs.student_id')
            ->join('users as u', 'u.id', '=', 'cs.student_id')
            ->where('cs.classroom_id', $classroomId)
            ->where('cs.state', 'active')
            ->where('u.status', 'active')
            ->orderByDesc('p.xp_total')
            ->orderBy('p.user_id')
            ->offset($offset)->limit($limit)
            ->get(['cs.student_id as user_id', 'u.name', 'p.xp_total', 'p.xp_level']);

        return $rows->map(fn ($r, $i) => [
            'user_id' => $r->user_id,
            'name' => $r->name,
            'xp_total' => (int) $r->xp_total,
            'xp_level' => (int) $r->xp_level,
            'position' => $offset + $i + 1,
        ])->all();
    }

    public function positionOf(int $userId): int
    {
        $better = DB::table('user_xp_profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('u.status', 'active')
            ->where('p.xp_total', '>', function ($q) use ($userId) {
                $q->select('xp_total')->from('user_xp_profiles')->where('user_id', $userId);
            })
            ->count();

        return $better + 1;
    }
}
