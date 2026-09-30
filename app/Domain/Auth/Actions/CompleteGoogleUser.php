<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Data\OAuthOutcome;
use App\Domain\Auth\Data\UserRole;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * RF-2, RB-01, RB-03, RB-04 (§3.1.2).
 * Completa el flujo Google: o login de cuenta existente o alta con rol elegido.
 */
final readonly class CompleteGoogleUser
{
    public function __invoke(OAuthOutcome $outcome, ?UserRole $role, ?string $name): User
    {
        if ($outcome instanceof \App\Domain\Auth\Data\OAuthLogin) {
            return $outcome->user; // RB-04: login directo, rol intacto
        }

        /** @var \App\Domain\Auth\Data\RequireRoleChoice $outcome */
        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => [__('auth.role_required')],
            ]);
        }

        // RB-03: email ya existente (cuenta tradicional sin google_id) → vincular, no duplicar
        $existing = User::query()->where('email', $outcome->email)->first();
        if ($existing !== null) {
            $existing->forceFill([
                'google_id' => $outcome->googleId,
                'avatar_url' => $existing->avatar_url ?? $outcome->avatarUrl,
            ])->save(); // RB-04: el rol del existente NO se toca

            return $existing->refresh();
        }

        /** @var User $user */
        $user = User::query()->create([
            'name' => $name ?? $outcome->name ?? __('auth.google_user'),
            'email' => $outcome->email,
            'password' => Hash::make(uniqid('edugt-', true)), // cuenta Google: password inaccesible
            'avatar_url' => $outcome->avatarUrl,
        ]);

        $user->forceFill([
            'google_id' => $outcome->googleId,
            'role' => $role->value,
            'status' => $role === UserRole::Teacher ? 'pending_approval' : 'active', // RB-02
        ])->save();

        return $user->refresh();
    }
}
