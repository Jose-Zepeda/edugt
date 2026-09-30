<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Data\OAuthOutcome;
use App\Domain\Auth\Contracts\GoogleOAuthGateway;
use App\Domain\Users\Models\User;
use Laravel\Socialite\Facades\Socialite;

/**
 * Implementación de GoogleOAuthGateway sobre Socialite (§3.1.2).
 * La validación del token/identidad la hace Google + Socialite en backend.
 */
final class SocialiteGoogleGateway implements GoogleOAuthGateway
{
    public function resolve(): OAuthOutcome
    {
        $googleUser = Socialite::driver('google')->user();

        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();

        throw_if(empty($email) || empty($googleId), new \RuntimeException('google_identity_incomplete'));

        // RB-03/RB-04: si ya existe por google_id → login; si existe solo email → también login/vínculo en CompleteGoogleUser
        $user = User::query()->where('google_id', $googleId)->first();

        if ($user !== null) {
            return OAuthOutcome::login($user);
        }

        $emailUser = User::query()->where('email', $email)->first();
        if ($emailUser !== null) {
            // Cuenta tradicional con este email: vincular google_id (RB-03)
            $emailUser->forceFill(['google_id' => $googleId])->save();
            return OAuthOutcome::login($emailUser->refresh());
        }

        // Usuario nuevo: exigir rol en el paso siguiente (formulario posterior)
        return OAuthOutcome::requireRoleChoice(
            googleId: (string) $googleId,
            email: $email,
            name: $googleUser->getName(),
            avatarUrl: $googleUser->getAvatar(),
        );
    }
}
