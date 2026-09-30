<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Curriculum\Actions\CompleteLesson;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Gamification\Contracts\StreakService;
use App\Domain\Gamification\Contracts\XpLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RF-17: XP, racha, insignias, rankings.
 */
final class GamificationController extends Controller
{
    public function __construct(
        private readonly XpLedger $xpLedger,
        private readonly StreakService $streak,
        private readonly CompleteLesson $completeLesson,
        private readonly \App\Domain\Gamification\Contracts\RankingService $rankings,
    ) {}

    /** POST /api/v1/lessons/{id}/complete {time_spent_seconds} */
    public function completeLesson(Request $request, int $lessonId): JsonResponse
    {
        $user = $request->user();
        $lesson = Lesson::query()->findOrFail($lessonId);

        $data = $request->validate([
            'time_spent_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);

        $result = $this->completeLesson->execute($user, $lesson, $data['time_spent_seconds']);

        return response()->json($result);
    }

    /** GET /api/v1/me/xp */
    public function xp(Request $request): JsonResponse
    {
        $user = $request->user();
        $total = $this->xpLedger->currentTotal($user->id);
        $level = $this->levelFor($total);
        $floor = ($level - 1) * 100;
        $next = $level * 100;

        return response()->json([
            'xp_total' => $total,
            'xp_level' => $level,
            'level_floor_xp' => $floor,
            'next_level_xp' => $next,
            'progress_to_next_level' => $next > $floor ? round(($total - $floor) / ($next - $floor) * 100, 1) : 100.0,
        ]);
    }

    /** GET /api/v1/me/streak */
    public function streak(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'current' => $this->streak->current($user->id),
            'longest' => $this->streak->longest($user->id),
        ]);
    }

    /** GET /api/v1/me/badges — catálogo: obtenidas + bloqueadas (RF-17) */
    public function badges(Request $request): JsonResponse
    {
        $user = $request->user();

        $obtained = \App\Domain\Gamification\Models\UserBadge::query()
            ->where('user_id', $user->id)
            ->with('badge')
            ->get()
            ->map(fn ($ub) => [
                'id' => $ub->badge->id,
                'slug' => $ub->badge->slug,
                'title' => $ub->badge->title,
                'description' => $ub->badge->description,
                'awarded_at' => $ub->awarded_at?->toIso8601String(),
            ]);

        $obtainedIds = $obtained->pluck('id');

        $locked = \App\Domain\Gamification\Models\Badge::query()
            ->whereNotIn('id', $obtainedIds)
            ->get(['id', 'slug', 'title', 'description'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'slug' => $b->slug,
                'title' => $b->title,
                'description' => $b->description,
            ]);

        return response()->json([
            'obtained' => $obtained,
            'locked' => $locked,
        ]);
    }

    /** GET /api/v1/rankings?scope=global|classroom&classroom_id= */
    public function rankings(Request $request): JsonResponse
    {
        $scope = $request->query('scope', 'global');

        $rows = $scope === 'classroom'
            ? $this->rankings->forClassroom((int) $request->query('classroom_id'))
            : $this->rankings->global();

        return response()->json([
            'scope' => $scope,
            'data' => $rows,
        ]);
    }

    private function levelFor(int $xpTotal): int
    {
        return max(1, intdiv($xpTotal, 100) + 1);
    }
}
