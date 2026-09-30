<?php

namespace App\Domain\Auth\Data;

/**
 * Resultado de resolver una identidad OAuth de Google (§3.1.2).
 */
abstract class OAuthOutcome
{
    /**
     * Usuario existente (o ya vinculado) → login directo.
     * RB-03: nunca se crea duplicado por email.
     * RB-04: el rol existente no cambia.
     */
    public static function login(\App\Domain\Users\Models\User $user): OAuthLogin
    {
        return new OAuthLogin($user);
    }

    /**
     * Usuario nuevo → exigir nombre y rol (student|teacher) antes de crear.
     */
    public static function requireRoleChoice(string $googleId, string $email, ?string $name, ?string $avatarUrl): RequireRoleChoice
    {
        return new RequireRoleChoice($googleId, $email, $name, $avatarUrl);
    }
}

final class OAuthLogin extends OAuthOutcome
{
    public function __construct(public readonly \App\Domain\Users\Models\User $user) {}
}

final class RequireRoleChoice extends OAuthOutcome
{
    public function __construct(
        public readonly string $googleId,
        public readonly string $email,
        public readonly ?string $name,
        public readonly ?string $avatarUrl,
    ) {}
}
