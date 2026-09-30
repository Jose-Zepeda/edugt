<?php

use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\Question;
use App\Domain\Assessments\Models\QuestionOption;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Curriculum\Models\Mission;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Gamification\Models\UserXpProfile;
use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Fase 4: Curriculum + desbloqueo RB-05 + XP de lección (una sola vez).
 */
function seedCurriculum(): array
{
    $mission = Mission::query()->create([
        'slug' => 'fundamentos', 'title' => 'Fundamentos', 'status' => 'published',
    ]);

    $module = Module::query()->create([
        'mission_id' => $mission->id,
        'title' => 'Variables', 'required_level' => 1, 'order_index' => 0, 'status' => 'published',
    ]);

    $l1 = Lesson::query()->create([
        'module_id' => $module->id, 'title' => 'L1', 'order_index' => 0,
        'xp_reward' => 10, 'status' => 'published', 'requires_quiz_pass' => false,
    ]);

    $l2 = Lesson::query()->create([
        'module_id' => $module->id, 'title' => 'L2', 'order_index' => 1,
        'xp_reward' => 10, 'status' => 'published', 'requires_quiz_pass' => false,
    ]);

    $l3 = Lesson::query()->create([
        'module_id' => $module->id, 'title' => 'L3', 'order_index' => 2,
        'xp_reward' => 10, 'status' => 'published', 'requires_quiz_pass' => false,
    ]);

    return compact('mission', 'module', 'l1', 'l2', 'l3');
}

beforeEach(function () {
    [$mission, $module, $l1, $l2, $l3] = array_values(seedCurriculum());
    $this->mission = $mission;
    $this->module = $module;
    $this->l1 = $l1;
    $this->l2 = $l2;
    $this->l3 = $l3;
});

describe('RB-05 — desbloqueo secuencial', function () {
    it('permite acceder a la primera lección y bloquea la segunda', function () {
        $user = User::factory()->student()->create();
        $token = $user->createToken('spa')->plainTextToken;

        getJson("/api/v1/lessons/{$this->l1->id}", ['Authorization' => "Bearer $token"])
            ->assertStatus(200);

        getJson("/api/v1/lessons/{$this->l2->id}", ['Authorization' => "Bearer $token"])
            ->assertStatus(403)
            ->assertJsonPath('message', 'LOCKED_CONTENT');
    });

    it('desbloquea la segunda lección al completar la primera', function () {
        $user = User::factory()->student()->create();
        $token = $user->createToken('spa')->plainTextToken;

        postJson("/api/v1/lessons/{$this->l1->id}/complete", [
            'time_spent_seconds' => 120,
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);

        getJson("/api/v1/lessons/{$this->l2->id}", ['Authorization' => "Bearer $token"])
            ->assertStatus(200);
    });
});

describe('RF-10 — XP y progreso', function () {
    it('otorga XP solo la primera vez que se completa la lección', function () {
        $user = User::factory()->student()->create();
        $token = $user->createToken('spa')->plainTextToken;

        postJson("/api/v1/lessons/{$this->l1->id}/complete", [
            'time_spent_seconds' => 60,
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);

        postJson("/api/v1/lessons/{$this->l1->id}/complete", [
            'time_spent_seconds' => 30,
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);

        $profile = UserXpProfile::query()->where('user_id', $user->id)->first();
        expect($profile->xp_total)->toBe(10); // solo una vez
    });

    it('expone el árbol de misiones con locked/completed por usuario', function () {
        $user = User::factory()->student()->create();
        $token = $user->createToken('spa')->plainTextToken;

        $res = getJson('/api/v1/me/missions', ['Authorization' => "Bearer $token"])
            ->assertStatus(200);

        $firstModule = $res->json('data.0.modules.0');
        expect($firstModule['lessons'][0]['locked'])->toBeFalse();
        expect($firstModule['lessons'][1]['locked'])->toBeTrue();
    });
});

describe('RF-17 — racha', function () {
    it('marca actividad y expone racha', function () {
        $user = User::factory()->student()->create();
        $token = $user->createToken('spa')->plainTextToken;

        postJson("/api/v1/lessons/{$this->l1->id}/complete", [
            'time_spent_seconds' => 45,
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);

        $res = getJson('/api/v1/me/streak', ['Authorization' => "Bearer $token"])
            ->assertStatus(200);
        expect($res->json('current'))->toBe(1);
    });
});
