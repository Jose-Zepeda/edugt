<?php

use App\Domain\Curriculum\Models\Mission;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Gamification\Models\Badge;
use App\Domain\Gamification\Models\BadgeRule;
use App\Domain\Gamification\Models\UserBadge;
use App\Domain\Lab\Models\LabChallenge;
use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function seedLabEnvironment(): array
{
    $mission = Mission::query()->create(['slug' => 'm-lab', 'title' => 'Misión Lab', 'status' => 'published']);
    $module = Module::query()->create([
        'mission_id' => $mission->id,
        'title' => 'Módulo 1: Variables',
        'required_level' => 1,
        'order_index' => 0,
        'status' => 'published',
    ]);

    $badge = Badge::query()->firstOrCreate(
        ['slug' => 'arquitecto-algoritmos'],
        ['title' => 'Arquitecto de Algoritmos', 'description' => 'Superaste 3 prácticas en el Laboratorio.']
    );
    BadgeRule::query()->firstOrCreate(
        ['badge_id' => $badge->id, 'event' => 'lab_completed'],
        ['conditions' => ['count' => 3], 'is_active' => true]
    );

    $challenges = [];
    for ($i = 1; $i <= 3; $i++) {
        $challenges[] = LabChallenge::query()->create([
            'module_id' => $module->id,
            'slug' => "lab-test-{$i}",
            'title' => "Práctica {$i}",
            'description' => "Descripción práctica {$i}",
            'dimension' => 'variables',
            'difficulty' => 'beginner',
            'xp_reward' => 40,
            'order_index' => $i,
            'simulator_json' => [
                'code_lines' => ['a = 10', 'b = 20'],
                'variables_names' => ['a', 'b'],
                'steps' => [
                    ['line' => 1, 'vars' => ['a' => 10, 'b' => '—'], 'changed' => 'a', 'output' => null, 'explanation' => 'Paso 1'],
                ],
            ],
            'challenge_json' => [
                'prompt' => 'Ordena los bloques',
                'blocks' => [
                    ['id' => 'b2', 'code' => 'b = 20'],
                    ['id' => 'b1', 'code' => 'a = 10'],
                ],
                'trace_question' => '¿Valor final?',
                'trace_options' => [
                    ['id' => 'opt_a', 'label' => 'a = 10, b = 20'],
                    ['id' => 'opt_b', 'label' => 'a = 20, b = 10'],
                ],
            ],
            'solution_json' => [
                'correct_block_order' => ['b1', 'b2'],
                'correct_trace_option' => 'opt_a',
                'explanation_success' => 'Correcto',
                'explanation_error_order' => 'Error de orden',
                'explanation_error_trace' => 'Error de traza',
            ],
            'status' => 'published',
        ]);
    }

    return compact('module', 'challenges', 'badge');
}

beforeEach(function () {
    $env = seedLabEnvironment();
    $this->challenges = $env['challenges'];
    $this->badge = $env['badge'];
    $this->student = User::factory()->student()->create();
    $this->token = $this->student->createToken('spa')->plainTextToken;
});

it('RB-10: lista los retos del laboratorio sin exponer solution_json al navegador', function () {
    $res = getJson('/api/v1/lab/challenges', ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);

    expect($res->json('summary.total_challenges'))->toBe(3);
    expect($res->json('summary.completed_challenges'))->toBe(0);
    expect($res->json('data.0.solution_json'))->toBeNull();
    expect($res->json('data.0.simulator.code_lines'))->toHaveCount(2);
});

it('evalúa en servidor el orden de bloques y prueba de escritorio, otorgando XP e insignia al completar 3 retos', function () {
    $ch1 = $this->challenges[0];

    // 1. Intento fallido (orden incorrecto) → no otorga XP
    $failRes = postJson("/api/v1/lab/challenges/{$ch1->id}/submit", [
        'block_order' => ['b2', 'b1'],
        'trace_option' => 'opt_a',
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    expect($failRes->json('passed'))->toBeFalse();
    expect($failRes->json('order_ok'))->toBeFalse();
    expect($failRes->json('trace_ok'))->toBeTrue();
    expect($failRes->json('xp_awarded_now'))->toBe(0);

    // 2. Segundo intento correcto en reto 1 → otorga XP con decaimiento (40 * 0.8 = 32 XP)
    $okRes1 = postJson("/api/v1/lab/challenges/{$ch1->id}/submit", [
        'block_order' => ['b1', 'b2'],
        'trace_option' => 'opt_a',
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    expect($okRes1->json('passed'))->toBeTrue();
    expect($okRes1->json('xp_awarded_now'))->toBe(32);

    // 3. Completar reto 2 y reto 3 en primer intento → desbloquea insignia Arquitecto de Algoritmos
    foreach ([$this->challenges[1], $this->challenges[2]] as $ch) {
        postJson("/api/v1/lab/challenges/{$ch->id}/submit", [
            'block_order' => ['b1', 'b2'],
            'trace_option' => 'opt_a',
        ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);
    }

    expect(UserBadge::query()
        ->where('user_id', $this->student->id)
        ->where('badge_id', $this->badge->id)
        ->exists())->toBeTrue();
});
