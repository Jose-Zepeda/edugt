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
    public function global(int $limit = 500, int $offset = 0): array
    {
        $rows = DB::table('users as u')
            ->leftJoin('user_xp_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.role', 'student')
            ->where('u.status', 'active')
            ->orderByRaw('COALESCE(p.xp_total, 0) DESC')
            ->orderBy('u.id')
            ->offset($offset)->limit($limit)
            ->get([
                'u.id as user_id',
                'u.name',
                'u.avatar_url',
                DB::raw('COALESCE(p.xp_total, 0) as xp_total'),
                DB::raw('COALESCE(p.xp_level, 1) as xp_level'),
            ]);

        return $rows->map(fn ($r, $i) => [
            'user_id' => (int) $r->user_id,
            'name' => $r->name,
            'avatar_url' => $r->avatar_url,
            'xp_total' => (int) $r->xp_total,
            'xp_level' => (int) $r->xp_level,
            'position' => $offset + $i + 1,
        ])->all();
    }

    public function forClassroom(int $classroomId, int $limit = 500, int $offset = 0): array
    {
        $rows = DB::table('classroom_students as cs')
            ->join('users as u', 'u.id', '=', 'cs.student_id')
            ->leftJoin('user_xp_profiles as p', 'p.user_id', '=', 'cs.student_id')
            ->where('cs.classroom_id', $classroomId)
            ->where('cs.state', 'active')
            ->where('u.status', 'active')
            ->orderByRaw('COALESCE(p.xp_total, 0) DESC')
            ->orderBy('u.name')
            ->offset($offset)->limit($limit)
            ->get([
                'cs.student_id as user_id',
                'u.name',
                'u.avatar_url',
                DB::raw('COALESCE(p.xp_total, 0) as xp_total'),
                DB::raw('COALESCE(p.xp_level, 1) as xp_level'),
            ]);

        return $rows->map(fn ($r, $i) => [
            'user_id' => (int) $r->user_id,
            'name' => $r->name,
            'avatar_url' => $r->avatar_url,
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
