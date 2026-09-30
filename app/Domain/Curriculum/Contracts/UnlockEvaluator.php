<?php

namespace App\Domain\Curriculum\Contracts;

use App\Domain\Users\Models\User;

/**
 * RB-05: evaluador de desbloqueo de lecciones (Fase 4).
 */
interface UnlockEvaluator
{
    public function canAccess(User $user, \App\Domain\Curriculum\Models\Lesson $lesson): bool;
}
