<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Users\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * RF-1 (login tradicional). RB-02: teacher pending_approval no entra — 423.
 */
final readonly class LoginUser
{
    public function __invoke(string $email, string $password): User
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->password === null || ! password_verify($password, $user->password)) {
            // Mensaje genérico: no revelar si el email existe (seguridad)
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status === 'pending_approval') {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => __('auth.teacher_pending'),
                    'code' => 'TEACHER_PENDING_APPROVAL',
                    'status' => 'pending_approval',
                ], 423)
            );
        }

        if ($user->status === 'suspended') {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => __('auth.suspended'),
                    'code' => 'ACCOUNT_SUSPENDED',
                    'status' => 'suspended',
                ], 403)
            );
        }

        return $user;
    }
}
