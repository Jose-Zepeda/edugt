<?php

use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    config()->set('app.locale', 'es');
});

describe('RF-3 / RB-01 — registro solo student|teacher', function () {
    it('registra un estudiante y devuelve token (cuenta activa)', function () {
        $res = postJson('/api/v1/auth/register', [
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'password' => 'SuperSecret123!',
            'role' => 'student',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.status', 'active');
        expect($res->json('token'))->toBeString();
        expect(User::where('email', 'ana@example.com')->exists())->toBeTrue();
    });

    it('registra un docente como pending_approval sin token (RB-02)', function () {
        $res = postJson('/api/v1/auth/register', [
            'name' => 'Mario Pérez',
            'email' => 'mario@example.com',
            'password' => 'SuperSecret123!',
            'role' => 'teacher',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonPath('user.status', 'pending_approval');
        expect($res->json('token'))->toBeNull();
    });

    it('rechaza role=admin con 422 (RB-01)', function () {
        $res = postJson('/api/v1/auth/register', [
            'name' => 'Atacante',
            'email' => 'hacker@example.com',
            'password' => 'SuperSecret123!',
            'role' => 'admin',
        ]);

        $res->assertStatus(422);
        expect(User::where('email', 'hacker@example.com')->exists())->toBeFalse();
    });

    it('rechaza role faltante o inválido', function (string $role) {
        postJson('/api/v1/auth/register', [
            'name' => 'X',
            'email' => 'x'.$role.'@example.com',
            'password' => 'SuperSecret123!',
            'role' => $role,
        ])->assertStatus(422);
    })->with(['', 'ADMIN', 'studente', 'root']);
});

describe('RF-5 / RB-03 — sin duplicados por email', function () {
    it('rechaza registro con email ya existente', function () {
        User::factory()->create(['email' => 'dup@example.com']);

        postJson('/api/v1/auth/register', [
            'name' => 'Duplicado',
            'email' => 'dup@example.com',
            'password' => 'SuperSecret123!',
            'role' => 'student',
        ])->assertStatus(422);

        expect(User::where('email', 'dup@example.com')->count())->toBe(1);
    });
});

describe('RF-1 — login tradicional', function () {
    it('loguea un estudiante activo y devuelve token', function () {
        $user = User::factory()->student()->create(['email' => 'login@example.com']);

        $res = postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $res->assertStatus(200);
        expect($res->json('token'))->toBeString();
        expect($res->json('user.id'))->toBe($user->id);
    });

    it('devuelve 423 TEACHER_PENDING_APPROVAL para docente pendiente (RB-02)', function () {
        User::factory()->pendingTeacher()->create(['email' => 'pend@example.com']);

        postJson('/api/v1/auth/login', [
            'email' => 'pend@example.com',
            'password' => 'password',
        ])->assertStatus(423)->assertJsonPath('code', 'TEACHER_PENDING_APPROVAL');
    });

    it('devuelve 403 para cuenta suspendida', function () {
        User::factory()->create(['email' => 'susp@example.com', 'status' => 'suspended']);

        postJson('/api/v1/auth/login', [
            'email' => 'susp@example.com',
            'password' => 'password',
        ])->assertStatus(403);
    });

    it('no revela existencia del email (mensaje genérico, 422)', function () {
        postJson('/api/v1/auth/login', [
            'email' => 'noexiste@example.com',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonPath('errors.email.0', 'Estas credenciales no coinciden con nuestros registros.');
    });
});

describe('RF-1 — logout y /me', function () {
    it('revoca el token actual en logout', function () {
        $user = User::factory()->student()->create();
        $token = $user->createToken('spa')->plainTextToken;

        postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer $token"])
            ->assertStatus(204);

        // El token ya no sirve (forgetGuards simula un cliente nuevo: los guards cachean el usuario dentro del test)
        auth()->forgetGuards();
        getJson('/api/v1/me', ['Authorization' => "Bearer $token"])->assertStatus(401);
    });

    it('expone /me solo autenticado', function () {
        getJson('/api/v1/me')->assertStatus(401);
    });
});
