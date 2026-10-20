<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Curriculum\Actions\CompleteLesson;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Gamification\Contracts\BadgeAwarder;
use App\Domain\Gamification\Contracts\RankingService;
use App\Domain\Gamification\Contracts\StreakService;
use App\Domain\Gamification\Contracts\XpLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * RF-17: XP, racha, insignias, rankings.
 */
final class GamificationController extends Controller
{
    public function __construct(
        private readonly XpLedger $xpLedger,
        private readonly StreakService $streak,
        private readonly CompleteLesson $completeLesson,
        private readonly RankingService $rankings,
        private readonly BadgeAwarder $badges,
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

        // Evalúa el estado del usuario y otorga automáticamente cualquier insignia merecida
        $this->badges->evaluateAll($user);

        $obtained = \App\Domain\Gamification\Models\UserBadge::query()
            ->where('user_id', $user->id)
            ->with('badge')
            ->orderBy('awarded_at', 'asc')
            ->get()
            ->map(fn ($ub) => [
                'id' => $ub->badge->id,
                'slug' => $ub->badge->slug,
                'title' => $ub->badge->title,
                'description' => $ub->badge->description,
                'awarded_at' => $ub->awarded_at?->toIso8601String(),
                'notified' => $ub->notified_at !== null,
                'notified_at' => $ub->notified_at?->toIso8601String(),
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

        // Insignias recién obtenidas que no han sido notificadas/cerradas
        $unnotified = $obtained->where('notified', false)->values();

        return response()->json([
            'obtained' => $obtained,
            'locked' => $locked,
            'unnotified' => $unnotified,
        ]);
    }

    /** POST /api/v1/me/badges/dismiss or POST /api/v1/me/badges/{id}/dismiss */
    public function dismissBadgeNotification(Request $request, ?int $id = null): JsonResponse
    {
        $user = $request->user();

        $query = \App\Domain\Gamification\Models\UserBadge::query()
            ->where('user_id', $user->id)
            ->whereNull('notified_at');

        if ($id !== null) {
            $query->where('badge_id', $id);
        } elseif ($request->filled('badge_id')) {
            $query->where('badge_id', (int) $request->input('badge_id'));
        } elseif ($request->filled('badge_ids')) {
            $query->whereIn('badge_id', (array) $request->input('badge_ids'));
        }

        $affected = $query->update(['notified_at' => now()]);

        return response()->json([
            'success' => true,
            'dismissed_count' => $affected,
        ]);
    }

    /** GET /api/v1/rankings?scope=global|classroom&classroom_id= */
    public function rankings(Request $request): JsonResponse
    {
        $scope = $request->query('scope', 'global');
        $user = $request->user();

        if ($scope === 'classroom') {
            $classroomId = (int) $request->query('classroom_id');

            // Si no se especificó un aula, buscar la primera aula activa a la que pertenece el usuario
            if ($classroomId <= 0 && $user) {
                if ($user->isTeacher()) {
                    $classroomId = (int) DB::table('classrooms')
                        ->where('teacher_id', $user->id)
                        ->value('id');
                } else {
                    $classroomId = (int) DB::table('classroom_students')
                        ->where('student_id', $user->id)
                        ->where('state', 'active')
                        ->value('classroom_id');
                }
            }

            $classroom = $classroomId > 0
                ? DB::table('classrooms')->where('id', $classroomId)->first(['id', 'name', 'grade_level', 'academic_year'])
                : null;

            $rows = $classroomId > 0 ? $this->rankings->forClassroom($classroomId) : [];

            $userClassrooms = [];
            if ($user && $user->isStudent()) {
                $userClassrooms = DB::table('classroom_students as cs')
                    ->join('classrooms as c', 'c.id', '=', 'cs.classroom_id')
                    ->where('cs.student_id', $user->id)
                    ->where('cs.state', 'active')
                    ->orderBy('c.name')
                    ->get(['c.id', 'c.name']);
            }

            return response()->json([
                'scope' => $scope,
                'classroom_id' => $classroomId ?: null,
                'classroom_name' => $classroom?->name,
                'classrooms' => $userClassrooms,
                'data' => $rows,
            ]);
        }

        return response()->json([
            'scope' => $scope,
            'data' => $this->rankings->global(),
        ]);
    }

    private function levelFor(int $xpTotal): int
    {
        return max(1, intdiv($xpTotal, 100) + 1);
    }
}
