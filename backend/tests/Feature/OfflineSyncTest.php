<?php

use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Curriculum\Models\Mission;
use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Fase 10: OfflineSync — RB-13 (quiz offline no existe), RB-14 (solo lectura).
 */
beforeEach(function () {
    $mission = Mission::query()->create(['slug' => 'mo', 'title' => 'MO', 'status' => 'published']);
    $module = Module::query()->create([
        'mission_id' => $mission->id, 'title' => 'ModO',
        'required_level' => 1, 'order_index' => 0, 'status' => 'published',
    ]);
    $this->lesson = Lesson::query()->create([
        'module_id' => $module->id, 'title' => 'LO',
        'content_markdown' => '# Contenido', 'pseudocode_json' => ['code' => 'x = 1'],
        'status' => 'published', 'xp_reward' => 10, 'order_index' => 0,
    ]);

    $this->user = User::factory()->student()->create();
    $this->token = $this->user->createToken('spa')->plainTextToken;
});

it('RF-25: bundle expone lecciones con hash de versión y sin respuestas', function () {
    $res = getJson('/api/v1/offline/bundle', ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    $lesson = $res->json('data.lessons.0');
    expect($lesson['title'])->toBe('LO');
    expect($lesson)->toHaveKey('version_hash');
    expect($lesson['content_markdown'])->toBe('# Contenido');
});

it('RB-14: sync registra lectura offline con XP=0', function () {
    $res = postJson('/api/v1/offline/sync', [
        'items' => [[
            'lesson_id' => $this->lesson->id,
            'time_spent_seconds' => 300,
            'last_visited_at' => now()->subMinutes(10)->toIso8601String(),
        ]],
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    expect($res->json('accepted'))->toBe(1);
    expect($res->json('xp_awarded'))->toBe(0);

    $progress = LessonProgress::query()->where('user_id', $this->user->id)->first();
    expect($progress->time_spent_seconds)->toBe(300);
    expect($progress->completed)->toBeFalse(); // lectura no completa la lección
});

it('RB-13: intento de quiz offline se rechaza sin registrar nada', function () {
    $res = postJson('/api/v1/offline/sync', [
        'items' => [[
            'lesson_id' => $this->lesson->id,
            'time_spent_seconds' => 60,
            'last_visited_at' => now()->toIso8601String(),
            'quiz_id' => 1,
            'quiz_answers' => [1, 2, 3],
        ]],
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    expect($res->json('rejected'))->toBe(1);
    expect($res->json('xp_awarded'))->toBe(0);
    expect(LessonProgress::query()->where('user_id', $this->user->id)->exists())->toBeFalse();
});

it('merge idempotente: no retrocede last_visited_at', function () {
    $newer = now()->subMinutes(5)->toIso8601String();
    $older = now()->subHour()->toIso8601String();

    postJson('/api/v1/offline/sync', [
        'items' => [['lesson_id' => $this->lesson->id, 'time_spent_seconds' => 100, 'last_visited_at' => $newer]],
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    postJson('/api/v1/offline/sync', [
        'items' => [['lesson_id' => $this->lesson->id, 'time_spent_seconds' => 999, 'last_visited_at' => $older]],
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    $progress = LessonProgress::query()->where('user_id', $this->user->id)->first();
    // el tiempo del viejo (999s) NO debe sumarse porque es más antiguo que la última visita
    expect($progress->time_spent_seconds)->toBe(100);
});
