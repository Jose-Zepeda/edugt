<?php

namespace App\Domain\Auth\Rules;

use App\Domain\Auth\Services\TurnstileVerifier;
use Illuminate\Contracts\Validation\ImplicitRule;

final readonly class TurnstileRule implements ImplicitRule
{
    public function __construct(
        private ?string $clientIp = null,
        private ?TurnstileVerifier $verifier = null,
    ) {}

    public function passes($attribute, $value): bool
    {
        $verifier = $this->verifier ?? app(TurnstileVerifier::class);
        $token = is_string($value) ? $value : null;

        return $verifier->verify($token, $this->clientIp);
    }

    public function message(): string
    {
        return 'Por favor, completa la verificación de seguridad (CAPTCHA).';
    }
}
