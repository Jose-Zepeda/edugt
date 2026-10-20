<?php

use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\QuizAttempt;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\Mission;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;

/**
 * Regresión: aprobar el cuestionario de un módulo (>= 70) desbloquea el siguiente,
 * aunque el XP acumulado no alcance el nivel que pedía required_level.
 */
function seedTwoModuleMission(): array
{
    $mission = Mission::query()->create(['slug' => 'm2', 'title' => 'M2', 'status' => 'published']);

    $modules = [];
    foreach ([0, 1] as $i) {
        $module = Module::query()->create([
            'mission_id' => $mission->id, 'title' => "Mod {$i}",
            'required_level' => $i + 1, 'order_index' => $i, 'status' => 'published',
        ]);

        foreach ([0, 1] as $n) {
            Lesson::query()->create([
                'module_id' => $module->id, 'title' => $n === 1 ? 'Evaluación del módulo' : "Lección {$n}",
                'content_markdown' => 'x', 'estimated_minutes' => 5, 'xp_reward' => 10,
                'order_index' => $n, 'requires_quiz_pass' => $n === 1, 'status' => 'published',
            ]);
        }

        $quiz = Quiz::query()->create(['module_id' => $module->id, 'title' => "Quiz {$i}", 'passing_score' => 70]);
        $modules[] = [$module, $quiz];
    }

    return $modules;
}

beforeEach(function () {
    [$this->mod1, $this->quiz1] = seedTwoModuleMission()[0];
    $this->mod2 = Module::query()->where('order_index', 1)->first();
    $this->user = User::factory()->student()->create();
    $this->token = $this->user->createToken('spa')->plainTextToken;
});

function missionsFor($test): array
{
    return getJson('/api/v1/me/missions', ['Authorization' => "Bearer {$test->token}"])
        ->assertStatus(200)->json('data.0.modules');
}

it('mantiene bloqueado el módulo 2 mientras el quiz del módulo 1 no esté aprobado', function () {
    $modules = missionsFor($this);

    expect($modules[1]['lessons'][0]['locked'])->toBeTrue();
    expect($modules[0]['quiz_passed'])->toBeFalse();
});

it('desbloquea el módulo 2 al aprobar el quiz del módulo 1 con >= 70 aunque el XP sea < 100', function () {
    QuizAttempt::query()->create([
        'user_id' => $this->user->id, 'quiz_id' => $this->quiz1->id, 'attempt_number' => 1,
        'started_at' => now(), 'submitted_at' => now(), 'score' => 80, 'status' => 'submitted',
    ]);

    $modules = missionsFor($this);

    expect($modules[0]['quiz_passed'])->toBeTrue();
    expect($modules[0]['lessons'][1]['completed'])->toBeTrue();   // lección-evaluación
    expect($modules[1]['lessons'][0]['locked'])->toBeFalse();     // módulo 2 abierto
});

it('no desbloquea el módulo 2 con un quiz reprobado (< 70)', function () {
    QuizAttempt::query()->create([
        'user_id' => $this->user->id, 'quiz_id' => $this->quiz1->id, 'attempt_number' => 1,
        'started_at' => now(), 'submitted_at' => now(), 'score' => 60, 'status' => 'submitted',
    ]);

    expect(missionsFor($this)[1]['lessons'][0]['locked'])->toBeTrue();
});
