<?php

namespace App\Domain\Audit\Contracts;

use App\Domain\Users\Models\User;

/**
 * RF-23: bitácora de acciones administrativas.
 * Contrato DIP: Auth/Aulas/Reporting escriben vía esta interfaz.
 */
interface AuditLogger
{
    public function log(
        ?User $user,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $context = null,
    ): void;
}
