<?php

namespace App\Domain\Auth\Contracts;

use App\Domain\Auth\Data\OAuthOutcome;

/**
 * DIP (§4): el dominio Auth depende del contrato, no de Socialite.
 * El controlador inyecta la implementación SocialiteGoogleGateway.
 */
interface GoogleOAuthGateway
{
    /** Resuelve el usuario Google autenticado tras el callback. */
    public function resolve(): OAuthOutcome;
}
