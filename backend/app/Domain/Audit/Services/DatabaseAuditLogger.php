<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Request;

final readonly class DatabaseAuditLogger implements AuditLogger
{
    public function log(
        ?User $user,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $context = null,
    ): void {
        \App\Domain\Audit\Models\AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'context' => $context,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
