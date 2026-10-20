<?php

use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\QuizAttempt;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Curriculum\Models\Mission;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Gamification\Contracts\BadgeAwarder;
use App\Domain\Gamification\Models\Badge;
use App\Domain\Gamification\Models\BadgeRule;
use App\Domain\Users\Models\User;
use function Pest\Laravel\getJson;

function seedBadgeTestEnvironment(): array
{
    // 6 badges
    $badges = [
        ['slug' => 'novato-logica', 'title' => 'Novato en Lógica', 'description' => 'Aprobaste tu primer cuestionario.'],
        ['slug' => 'maestro-pseudocodigo', 'title' => 'Maestro del Pseudocódigo', 'description' => 'Aprobaste 5 cuestionarios.'],
        ['slug' => 'explorador-variables', 'title' => 'Explorador de Variables', 'description' => 'Completaste el módulo de variables.'],
        ['slug' => 'mago-del-control', 'title' => 'Mago del Control', 'description' => 'Dominaste las estructuras condicionales.'],
        ['slug' => 'maestro-del-bucle', 'title' => 'Maestro del Bucle', 'description' => 'Dominaste las estructuras cíclicas.'],
        ['slug' => 'depurador-elite', 'title' => 'Depurador Élite', 'description' => 'Aprobaste un cuestionario en el primer intento.'],
    ];

    foreach ($badges as $b) {
        Badge::query()->firstOrCreate(['slug' => $b['slug']], $b);
    }

    $mission = Mission::query()->create(['slug' => 'm-badge', 'title' => 'Mision Test', 'status' => 'published']);
    $modVar = Module::query()->create([
        'mission_id' => $mission->id, 'title' => 'Variables y Tipos de Datos',
        'required_level' => 1, 'order_index' => 0, 'status' => 'published',
    ]);
    $quizVar = Quiz::query()->create(['module_id' => $modVar->id, 'title' => 'Quiz Variables', 'passing_score' => 70]);

    $l1 = Lesson::query()->create(['module_id' => $modVar->id, 'title' => 'L1', 'content_markdown' => 'x', 'estimated_minutes' => 5, 'xp_reward' => 10, 'order_index' => 0, 'requires_quiz_pass' => false, 'status' => 'published']);
    $l2 = Lesson::query()->create(['module_id' => $modVar->id, 'title' => 'L2', 'content_markdown' => 'x', 'estimated_minutes' => 5, 'xp_reward' => 10, 'order_index' => 1, 'requires_quiz_pass' => false, 'status' => 'published']);
    $l3 = Lesson::query()->create(['module_id' => $modVar->id, 'title' => 'L3', 'content_markdown' => 'x', 'estimated_minutes' => 5, 'xp_reward' => 10, 'order_index' => 2, 'requires_quiz_pass' => false, 'status' => 'published']);

    $modCond = Module::query()->create([
        'mission_id' => $mission->id, 'title' => 'Estructuras Condicionales',
        'required_level' => 2, 'order_index' => 1, 'status' => 'published',
    ]);
    $quizCond = Quiz::query()->create(['module_id' => $modCond->id, 'title' => 'Quiz Condicionales', 'passing_score' => 70]);

    return [$modVar, $quizVar, [$l1, $l2, $l3], $modCond, $quizCond];
}

beforeEach(function () {
    [$this->modVar, $this->quizVar, $this->varLessons, $this->modCond, $this->quizCond] = seedBadgeTestEnvironment();
    $this->user = User::factory()->student()->create();
    $this->token = $this->user->createToken('spa')->plainTextToken;
    $this->awarder = app(BadgeAwarder::class);
});

it('otorga Novato en Lógica, Explorador de Variables y Depurador Élite al completar lecciones y aprobar el quiz en primer intento', function () {
    // 1. Completar lecciones de variables
    foreach ($this->varLessons as $l) {
        LessonProgress::query()->create(['user_id' => $this->user->id, 'lesson_id' => $l->id, 'completed' => true]);
    }

    // 2. Aprobar quiz de variables en intento 1 con score 100
    QuizAttempt::query()->create([
        'user_id' => $this->user->id,
        'quiz_id' => $this->quizVar->id,
        'attempt_number' => 1,
        'started_at' => now(),
        'submitted_at' => now(),
        'score' => 100,
        'status' => 'submitted',
    ]);

    // 3. Consultar /api/v1/me/badges
    $res = getJson('/api/v1/me/badges', ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);

    $obtainedSlugs = collect($res->json('obtained'))->pluck('slug')->all();

    expect($obtainedSlugs)->toContain('novato-logica');
    expect($obtainedSlugs)->toContain('explorador-variables');
    expect($obtainedSlugs)->toContain('depurador-elite');
    expect($obtainedSlugs)->not->toContain('mago-del-control');
});

it('otorga Mago del Control cuando el usuario aprueba el quiz de condicionales', function () {
    QuizAttempt::query()->create([
        'user_id' => $this->user->id,
        'quiz_id' => $this->quizCond->id,
        'attempt_number' => 1,
        'started_at' => now(),
        'submitted_at' => now(),
        'score' => 85,
        'status' => 'submitted',
    ]);

    $res = getJson('/api/v1/me/badges', ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);

    $obtainedSlugs = collect($res->json('obtained'))->pluck('slug')->all();
    expect($obtainedSlugs)->toContain('mago-del-control');
});

it('otorga Maestro del Bucle y Maestro del Pseudocódigo al aprobar los 3 módulos (incluyendo Estructuras Cíclicas)', function () {
    $modLoop = Module::query()->create([
        'mission_id' => $this->modVar->mission_id,
        'title' => 'Estructuras Cíclicas (Bucles)',
        'required_level' => 3,
        'order_index' => 2,
        'status' => 'published',
    ]);
    $quizLoop = Quiz::query()->create([
        'module_id' => $modLoop->id,
        'title' => 'Evaluación: Estructuras Cíclicas (Bucles)',
        'passing_score' => 70,
    ]);

    foreach ([$this->quizVar, $this->quizCond, $quizLoop] as $q) {
        QuizAttempt::query()->create([
            'user_id' => $this->user->id,
            'quiz_id' => $q->id,
            'attempt_number' => 1,
            'started_at' => now(),
            'submitted_at' => now(),
            'score' => 90,
            'status' => 'submitted',
        ]);
    }

    $res = getJson('/api/v1/me/badges', ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);

    $obtainedSlugs = collect($res->json('obtained'))->pluck('slug')->all();
    expect($obtainedSlugs)->toContain('maestro-del-bucle');
    expect($obtainedSlugs)->toContain('maestro-pseudocodigo');
});

it('marca insignias como no notificadas al otorgarse y permite descartar su notificación para que no vuelva a aparecer', function () {
    // 1. Otorgar insignias al aprobar quiz en intento 1
    QuizAttempt::query()->create([
        'user_id' => $this->user->id,
        'quiz_id' => $this->quizVar->id,
        'attempt_number' => 1,
        'started_at' => now(),
        'submitted_at' => now(),
        'score' => 100,
        'status' => 'submitted',
    ]);

    // 2. Primera consulta: recién conseguidas aparecen con notified = false y en unnotified
    $res = getJson('/api/v1/me/badges', ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);

    $unnotified = $res->json('unnotified');
    expect($unnotified)->not->toBeEmpty();
    $allIds = collect($unnotified)->pluck('id')->all();

    $firstBadge = collect($res->json('obtained'))->firstWhere('id', $allIds[0]);
    expect($firstBadge['notified'])->toBeFalse();
    expect($firstBadge['notified_at'])->toBeNull();

    // 3. El usuario descarta / cierra las notificaciones (POST /api/v1/me/badges/dismiss)
    $this->postJson('/api/v1/me/badges/dismiss', [
        'badge_ids' => $allIds,
    ], ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200)
        ->assertJson(['success' => true]);

    // 4. Siguiente consulta: ya no aparece ninguna en unnotified y notified = true
    $res2 = getJson('/api/v1/me/badges', ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);

    expect($res2->json('unnotified'))->toBeEmpty();
    $obtainedAfter = collect($res2->json('obtained'))->firstWhere('id', $allIds[0]);
    expect($obtainedAfter['notified'])->toBeTrue();
    expect($obtainedAfter['notified_at'])->not->toBeNull();
});

it('permite descartar notificación por id individual /api/v1/me/badges/{id}/dismiss', function () {
    QuizAttempt::query()->create([
        'user_id' => $this->user->id,
        'quiz_id' => $this->quizVar->id,
        'attempt_number' => 1,
        'started_at' => now(),
        'submitted_at' => now(),
        'score' => 100,
        'status' => 'submitted',
    ]);

    $res = getJson('/api/v1/me/badges', ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);
    $badgeId = $res->json('unnotified.0.id');

    $this->postJson("/api/v1/me/badges/{$badgeId}/dismiss", [], ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200)
        ->assertJson(['success' => true]);

    $res2 = getJson('/api/v1/me/badges', ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);
    $remainingUnnotifiedIds = collect($res2->json('unnotified'))->pluck('id')->all();
    expect($remainingUnnotifiedIds)->not->toContain($badgeId);

    $obtainedAfter = collect($res2->json('obtained'))->firstWhere('id', $badgeId);
    expect($obtainedAfter['notified'])->toBeTrue();
    expect($obtainedAfter['notified_at'])->not->toBeNull();
});

