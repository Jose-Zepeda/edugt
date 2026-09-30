<?php

namespace App\Domain\Auth\Services;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * RF-4, RB-02: aprobación/rechazo de docentes — solo admin.
 */
final readonly class TeacherApprovalService
{
    public function __construct(private AuditLogger $audit) {}

    public function approve(User $admin, User $teacher): User
    {
        abort_unless($admin->isAdmin(), 403, 'Solo un administrador puede aprobar docentes.');

        DB::transaction(function () use ($admin, $teacher): void {
            $teacher->forceFill(['status' => 'active'])->save();
            $this->audit->log(
                user: $admin,
                action: 'teacher.approved',
                targetType: 'user',
                targetId: $teacher->id,
                context: ['email' => $teacher->email],
            );
        });

        return $teacher->refresh();
    }

    public function reject(User $admin, User $teacher): User
    {
        abort_unless($admin->isAdmin(), 403, 'Solo un administrador puede rechazar docentes.');

        DB::transaction(function () use ($admin, $teacher): void {
            $teacher->forceFill(['status' => 'suspended'])->save();
            $this->audit->log(
                user: $admin,
                action: 'teacher.rejected',
                targetType: 'user',
                targetId: $teacher->id,
                context: ['email' => $teacher->email],
            );
        });

        return $teacher->refresh();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<User> */
    public function pendingList(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()
            ->where('role', 'teacher')
            ->where('status', 'pending_approval')
            ->orderBy('created_at');
    }
}
