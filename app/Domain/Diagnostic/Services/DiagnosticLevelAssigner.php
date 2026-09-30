<?php

namespace App\Domain\Diagnostic\Services;

use App\Domain\Diagnostic\Models\DiagnosticAttempt;
use App\Domain\Users\Models\User;

/**
 * RF-7 + §3.1.3: asigna learning_track a partir del pretest.
 * RB-11: NO otorga XP, insignias ni toca ranking — el módulo Diagnostic
 * no importa Gamification (test de arquitectura lo verifica).
 */
final class DiagnosticLevelAssigner
{
    /** Umbrales por % de aciertos del score_total. */
    public function assign(DiagnosticAttempt $attempt): string
    {
        $score = (int) ($attempt->score_total ?? 0);

        return match (true) {
            $score >= 80 => 'advanced',
            $score >= 50 => 'intermediate',
            default => 'beginner',
        };
    }

    public function apply(DiagnosticAttempt $attempt, User $user): void
    {
        $track = $this->assign($attempt);

        $user->forceFill(['learning_track' => $track])->save();
    }
}
