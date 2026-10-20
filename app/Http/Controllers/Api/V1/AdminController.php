<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Services\TeacherApprovalService;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RF-4: aprobación administrativa de docentes.
 * RB-09: acceso exclusivo admin (policy por rol en cada método).
 */
final class AdminController extends Controller
{
    public function __construct(private readonly TeacherApprovalService $approvals) {}

    private function requireAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isAdmin(), 403, 'Solo administradores.');

        return $user;
    }

    /** GET /api/v1/admin/metrics */
    public function metrics(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $pendingTeachers = User::query()->where('role', 'teacher')->where('status', 'pending_approval')->count();
        $activeTeachers = User::query()->where('role', 'teacher')->where('status', 'active')->count();
        $activeStudents = User::query()->where('role', 'student')->where('status', 'active')->count();
        $activeClassrooms = \App\Domain\Classrooms\Models\Classroom::query()->where('is_active', true)->count();

        $openTickets = \App\Domain\Audit\Models\SupportTicket::query()->where('status', 'open')->count();
        $inReviewTickets = \App\Domain\Audit\Models\SupportTicket::query()->where('status', 'in_review')->count();
        $resolvedTickets = \App\Domain\Audit\Models\SupportTicket::query()->where('status', 'resolved')->count();

        $recentLogs = \App\Domain\Audit\Models\AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'user_id', 'action', 'target_type', 'target_id', 'context', 'ip', 'created_at']);

        return response()->json([
            'teachers' => [
                'pending' => $pendingTeachers,
                'active' => $activeTeachers,
                'total' => $pendingTeachers + $activeTeachers,
            ],
            'students' => [
                'active' => $activeStudents,
            ],
            'classrooms' => [
                'active' => $activeClassrooms,
            ],
            'tickets' => [
                'open' => $openTickets,
                'in_review' => $inReviewTickets,
                'resolved' => $resolvedTickets,
                'total' => $openTickets + $inReviewTickets + $resolvedTickets,
            ],
            'recent_logs' => $recentLogs,
        ]);
    }

    /** GET /api/v1/admin/teachers?status=pending|active|all */
    public function pendingTeachers(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $status = $request->query('status', 'pending');
        if ($status === 'pending') {
            $teachers = $this->approvals->pendingList()->get(['id', 'name', 'email', 'status', 'created_at']);
        } else {
            $query = User::query()->where('role', 'teacher');
            if ($status !== 'all') {
                $query->where('status', $status);
            }
            $teachers = $query->orderByDesc('created_at')->get(['id', 'name', 'email', 'status', 'created_at']);
        }

        return response()->json([
            'data' => $teachers,
            'total' => $teachers->count(),
        ]);
    }

    /** POST /api/v1/admin/teachers/{id}/approve */
    public function approveTeacher(Request $request, int $id): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        $teacher = User::query()->where('role', 'teacher')->findOrFail($id);
        $teacher = $this->approvals->approve($admin, $teacher);

        return response()->json([
            'message' => 'Docente aprobado.',
            'user' => ['id' => $teacher->id, 'email' => $teacher->email, 'status' => $teacher->status],
        ]);
    }

    /** POST /api/v1/admin/teachers/{id}/reject */
    public function rejectTeacher(Request $request, int $id): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        $teacher = User::query()->where('role', 'teacher')->findOrFail($id);
        $teacher = $this->approvals->reject($admin, $teacher);

        return response()->json([
            'message' => 'Docente rechazado.',
            'user' => ['id' => $teacher->id, 'email' => $teacher->email, 'status' => $teacher->status],
        ]);
    }
}
