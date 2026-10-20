<?php

use App\Domain\Diagnostic\Models\DiagnosticTest;
use App\Domain\Diagnostic\Models\DiagnosticQuestion;
use App\Domain\Diagnostic\Models\DiagnosticAttempt;
use App\Domain\Gamification\Models\UserXpProfile;
use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Fase 5: Diagnostic — pretest reanudable, una sola vez, RB-11 (sin XP).
 */
function seedPreTest(): DiagnosticTest
{
    $test = DiagnosticTest::query()->create([
        'kind' => 'pre', 'version' => 1, 'question_count' => 12,
        'time_limit_minutes' => 15, 'is_active' => true,
    ]);

    $dims = ['algorithms', 'variables', 'conditionals'];
    foreach ($dims as $i => $dim) {
        for ($j = 0; $j < 4; $j++) {
            DiagnosticQuestion::query()->create([
                'diagnostic_test_id' => $test->id,
                'dimension' => $dim,
                'statement' => "Pregunta {$dim} {$j}",
                'options' => ['a', 'b', 'c'],
                'correct_index' => 1,
                'order_index' => $i * 4 + $j,
            ]);
        }
    }

    return $test;
}

it('descarga el pretest sin revelar respuestas (RB-10)', function () {
    seedPreTest();
    $user = User::factory()->student()->create();
    $token = $user->createToken('spa')->plainTextToken;

    $res = getJson('/api/v1/diagnostic/tests/pre', ['Authorization' => "Bearer $token"])
        ->assertStatus(200);

    $questions = $res->json('data.questions');
    expect(count($questions))->toBe(12);
    expect($questions[0])->not->toHaveKey('correct_index');
});

it('registra respuestas y calcula score al submit', function () {
    seedPreTest();
    $user = User::factory()->student()->create();
    $token = $user->createToken('spa')->plainTextToken;

    $res = getJson('/api/v1/diagnostic/tests/pre', ['Authorization' => "Bearer $token"]);
    $questions = $res->json('data.questions');

    // responde 6 correctas (index 1) y 6 incorrectas (index 0)
    foreach ($questions as $i => $q) {
        postJson('/api/v1/diagnostic/tests/pre/answers', [
            'question_id' => $q['id'],
            'selected_index' => $i < 6 ? 1 : 0,
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);
    }

    $res = postJson('/api/v1/diagnostic/tests/pre/submit', [], ['Authorization' => "Bearer $token"])
        ->assertStatus(200);

    expect($res->json('score_total'))->toBe(50);
    expect($res->json('dimension_scores.algorithms.correct'))->toBe(4);
    expect($res->json('learning_track'))->toBe('intermediate'); // 50 >= 50
});

it('permite reanudar tras interrupción sin duplicar intento (RF-28)', function () {
    seedPreTest();
    $user = User::factory()->student()->create();
    $token = $user->createToken('spa')->plainTextToken;

    // primer start
    $r1 = getJson('/api/v1/diagnostic/tests/pre', ['Authorization' => "Bearer $token"])->assertStatus(200);
    $attemptId1 = $r1->json('data.attempt_id');

    // responde una pregunta
    postJson('/api/v1/diagnostic/tests/pre/answers', [
        'question_id' => $r1->json('data.questions.0.id'),
        'selected_index' => 1,
    ], ['Authorization' => "Bearer $token"])->assertStatus(200);

    // segundo start (interrupción simulada) — mismo attempt
    $r2 = getJson('/api/v1/diagnostic/tests/pre', ['Authorization' => "Bearer $token"])->assertStatus(200);
    expect($r2->json('data.attempt_id'))->toBe($attemptId1);
    expect($r2->json('data.answered'))->toBe(1);
});

it('rechaza repetir el pretest ya completado (409)', function () {
    seedPreTest();
    $user = User::factory()->student()->create();
    $token = $user->createToken('spa')->plainTextToken;

    getJson('/api/v1/diagnostic/tests/pre', ['Authorization' => "Bearer $token"]);
    postJson('/api/v1/diagnostic/tests/pre/submit', [], ['Authorization' => "Bearer $token"])
        ->assertStatus(200);

    getJson('/api/v1/diagnostic/tests/pre', ['Authorization' => "Bearer $token"])
        ->assertStatus(409)
        ->assertJsonPath('code', 'DIAGNOSTIC_ALREADY_COMPLETED');
});

it('RB-11: el pretest NO otorga XP ni crea perfil de gamificación', function () {
    seedPreTest();
    $user = User::factory()->student()->create();
    $token = $user->createToken('spa')->plainTextToken;

    getJson('/api/v1/diagnostic/tests/pre', ['Authorization' => "Bearer $token"]);
    postJson('/api/v1/diagnostic/tests/pre/submit', [], ['Authorization' => "Bearer $token"])
        ->assertStatus(200);

    // El perfil de XP no debe existir: Diagnostic no toca Gamification
    expect(UserXpProfile::query()->where('user_id', $user->id)->exists())->toBeFalse();
});
