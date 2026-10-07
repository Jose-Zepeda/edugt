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
        ])->assertStatus(422)->assertJsonPath('errors.email.0', 'Credenciales invalidas, revisa el correo o la contraseña');
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

describe('RF-2 — completar registro Google sin token previo', function () {
    it('permite completar registro con Google sin header Authorization', function () {
        $res = postJson('/api/v1/auth/google/complete', [
            'google_id' => 'gid_123456789',
            'email' => 'nuevo_google@example.com',
            'name' => 'Usuario Google',
            'avatar_url' => 'https://lh3.googleusercontent.com/a/test',
            'role' => 'student',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.status', 'active');
        expect($res->json('token'))->toBeString();
        expect(User::where('email', 'nuevo_google@example.com')->exists())->toBeTrue();
    });

    it('inicia sesión a usuario existente de Google y devuelve token válido', function () {
        $student = User::factory()->student()->create([
            'email' => 'estudiante_google@example.com',
            'google_id' => 'gid_999888',
        ]);

        $mockGateway = Mockery::mock(\App\Domain\Auth\Contracts\GoogleOAuthGateway::class);
        $mockGateway->shouldReceive('resolve')
            ->once()
            ->andReturn(\App\Domain\Auth\Data\OAuthOutcome::login($student));

        app()->instance(\App\Domain\Auth\Contracts\GoogleOAuthGateway::class, $mockGateway);

        $res = $this->get('/api/v1/auth/google/callback');
        $res->assertStatus(302);

        $targetUrl = $res->headers->get('Location');
        expect($targetUrl)->toContain('/google-landing.html?token=');

        parse_str(parse_url($targetUrl, PHP_URL_QUERY), $queryParams);
        expect($queryParams)->toHaveKey('token');
        expect($queryParams['token'])->not->toBeEmpty();

        // El token funciona y expone al estudiante con su progreso
        getJson('/api/v1/me', ['Authorization' => 'Bearer '.$queryParams['token']])
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'estudiante_google@example.com')
            ->assertJsonPath('user.role', 'student');
    });

    it('redirige a usuario nuevo de Google a elegir rol', function () {
        $mockGateway = Mockery::mock(\App\Domain\Auth\Contracts\GoogleOAuthGateway::class);
        $mockGateway->shouldReceive('resolve')
            ->once()
            ->andReturn(\App\Domain\Auth\Data\OAuthOutcome::requireRoleChoice(
                googleId: 'gid_nuevo_123',
                email: 'nuevo@google.com',
                name: 'Nuevo Google',
                avatarUrl: 'https://example.com/photo.jpg'
            ));

        app()->instance(\App\Domain\Auth\Contracts\GoogleOAuthGateway::class, $mockGateway);

        $res = $this->get('/api/v1/auth/google/callback');
        $res->assertStatus(302);

        $targetUrl = $res->headers->get('Location');
        expect($targetUrl)->toContain('/google-landing.html?');
        parse_str(parse_url($targetUrl, PHP_URL_QUERY), $queryParams);
        expect($queryParams)->toHaveKey('google_id', 'gid_nuevo_123');
        expect($queryParams)->toHaveKey('email', 'nuevo@google.com');
    });
});

describe('Protección Anti-Bot y CAPTCHA Cloudflare Turnstile', function () {
    it('bloquea el registro si el campo honeypot website_hp está lleno', function () {
        $res = postJson('/api/v1/auth/register', [
            'name' => 'Bot Spammer',
            'email' => 'bot@spammer.test',
            'password' => 'SuperSecret123!',
            'role' => 'student',
            'website_hp' => 'https://spam-bot.site',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['website_hp']);
        expect(User::where('email', 'bot@spammer.test')->exists())->toBeFalse();
    });

    it('bloquea el login si el campo honeypot website_hp está lleno', function () {
        $user = User::factory()->student()->create(['email' => 'legit@example.com']);

        $res = postJson('/api/v1/auth/login', [
            'email' => 'legit@example.com',
            'password' => 'password',
            'website_hp' => 'http://spam-link.site',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['website_hp']);
    });

    it('exige token de Turnstile válido cuando el captcha está habilitado en registro', function () {
        config(['services.turnstile.enabled' => true]);

        // Sin token
        $resSinToken = postJson('/api/v1/auth/register', [
            'name' => 'Usuario Real',
            'email' => 'usuario_real@test.com',
            'password' => 'SuperSecret123!',
            'role' => 'student',
        ]);
        $resSinToken->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);

        // Con token inválido
        $resTokenInvalido = postJson('/api/v1/auth/register', [
            'name' => 'Usuario Real',
            'email' => 'usuario_real@test.com',
            'password' => 'SuperSecret123!',
            'role' => 'student',
            'turnstile_token' => 'invalid-token-123',
        ]);
        $resTokenInvalido->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);

        // Con token válido
        $resTokenValido = postJson('/api/v1/auth/register', [
            'name' => 'Usuario Real',
            'email' => 'usuario_real@test.com',
            'password' => 'SuperSecret123!',
            'role' => 'student',
            'turnstile_token' => 'test-valid-turnstile-token',
        ]);
        $resTokenValido->assertStatus(201);
        expect(User::where('email', 'usuario_real@test.com')->exists())->toBeTrue();
    });

    it('exige token de Turnstile válido cuando el captcha está habilitado en login', function () {
        config(['services.turnstile.enabled' => true]);
        $user = User::factory()->student()->create(['email' => 'estudiante_captcha@example.com']);

        // Sin token
        $resSinToken = postJson('/api/v1/auth/login', [
            'email' => 'estudiante_captcha@example.com',
            'password' => 'password',
        ]);
        $resSinToken->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);

        // Con token válido
        $resTokenValido = postJson('/api/v1/auth/login', [
            'email' => 'estudiante_captcha@example.com',
            'password' => 'password',
            'turnstile_token' => 'test-valid-turnstile-token',
        ]);
        $resTokenValido->assertStatus(200)
            ->assertJsonPath('user.id', $user->id);
    });
});

