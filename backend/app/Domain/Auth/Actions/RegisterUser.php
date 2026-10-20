<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Data\UserRole;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * RF-1, RF-3, RB-01, RB-02.
 * Registro tradicional: SOLO student|teacher (RB-01).
 * Teacher nace pending_approval (RB-02); student nace active.
 */
final readonly class RegisterUser
{
    public function __invoke(string $name, string $email, string $password, UserRole $role): User
    {
        // RB-03: un email existente jamás se duplica — se rechaza con 422
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => [__('auth.email_taken')],
            ]);
        }

        /** @var User $user */
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // role/status pasan por forceFill: jamás por fillable (RB-01)
        $user->forceFill([
            'role' => $role->value,
            'status' => $role === UserRole::Teacher ? 'pending_approval' : 'active',
        ])->save();

        return $user->refresh();
    }

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'in:student,teacher'], // RB-01: 'admin' rechazado aquí
        ];
    }
}
