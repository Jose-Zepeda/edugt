<?php

namespace Database\Factories;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Domain\Users\Models\User>
 */
class UserFactory extends Factory
{
    /** @var class-string<\App\Domain\Users\Models\User> */
    protected $model = \App\Domain\Users\Models\User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'student',
            'status' => 'active',
        ];
    }

    public function student(): static
    {
        return $this->state(fn () => ['role' => 'student', 'status' => 'active']);
    }

    public function teacher(): static
    {
        return $this->state(fn () => ['role' => 'teacher', 'status' => 'active']);
    }

    public function pendingTeacher(): static
    {
        return $this->state(fn () => ['role' => 'teacher', 'status' => 'pending_approval']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin', 'status' => 'active']);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
