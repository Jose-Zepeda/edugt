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

    /** GET /api/v1/admin/teachers?status=pending */
    public function pendingTeachers(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $teachers = $this->approvals->pendingList()->get(['id', 'name', 'email', 'created_at']);

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
