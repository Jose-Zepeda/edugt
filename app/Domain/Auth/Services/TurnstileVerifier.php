<?php

namespace App\Domain\Auth\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Valida el token de Cloudflare Turnstile contra la API oficial.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        // 1. Si el captcha está desactivado en la configuración, se aprueba
        if (!config('services.turnstile.enabled', true)) {
            return true;
        }

        // 2. En entorno de tests, validar de forma determinista sin llamadas de red
        if (app()->environment('testing')) {
            return $token === 'test-valid-turnstile-token';
        }

        if (empty($token)) {
            return false;
        }

        $secret = config('services.turnstile.secret_key');
        if (empty($secret)) {
            return true;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                return isset($body['success']) && $body['success'] === true;
            }

            Log::warning('Turnstile verification request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Error connecting to Cloudflare Turnstile API', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
