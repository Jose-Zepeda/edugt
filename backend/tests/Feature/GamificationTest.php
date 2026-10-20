<?php

use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\Question;
use App\Domain\Assessments\Models\QuestionOption;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Curriculum\Models\Mission;
use App\Domain\Gamification\Models\Badge;
use App\Domain\Gamification\Models\BadgeRule;
use App\Domain\Gamification\Models\UserBadge;
use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Fase 7: badges (RB-07) + rankings (RF-18, RB-08).
 */
function seedBadgeQuiz(): Quiz
{
    $mission = Mission::query()->create(['slug' => 'm2', 'title' => 'M2', 'status' => 'published']);
    $module = Module::query()->create([
        'mission_id' => $mission->id, 'title' => 'Mod2',
        'required_level' => 1, 'order_index' => 0, 'status' => 'published',
    ]);

    $quiz = Quiz::query()->create(['module_id' => $module->id, 'title' => 'Q2', 'passing_score' => 70]);

    $q = Question::query()->create([
        'quiz_id' => $quiz->id, 'statement' => '2+2?', 'order_index' => 0,
    ]);

    foreach (['A', 'B'] as $i => $letter) {
        QuestionOption::query()->create([
            'question_id' => $q->id,
            'option_text' => $letter === 'A' ? '4' : '5',
            'is_correct' => $letter === 'A',
            'order_index' => $i,
        ]);
    }

    return $quiz;
}

function passQuiz(User $user, Quiz $quiz): void
{
    $token = $user->createToken('spa')->plainTextToken;

    $start = postJson("/api/v1/quizzes/{$quiz->id}/attempts", [], ['Authorization' => "Bearer $token"]);
    $attemptId = $start->json('attempt_id');

    $quizData = getJson("/api/v1/quizzes/{$quiz->id}", ['Authorization' => "Bearer $token"])->json('data');

    foreach ($quizData['questions'] as $q) {
        $right = collect($q['options'])->firstWhere('option_text', '4');
        postJson("/api/v1/quizzes/{$quiz->id}/attempts/{$attemptId}/answers", [
            'question_id' => $q['id'],
            'option_id' => $right['id'],
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);
    }

    postJson("/api/v1/quizzes/{$quiz->id}/attempts/{$attemptId}/submit", [], ['Authorization' => "Bearer $token"])
        ->assertStatus(200);
}

beforeEach(function () {
    $this->quiz = seedBadgeQuiz();
    $this->user = User::factory()->student()->create();
});

it('RB-07: otorga insignia al aprobar y no la duplica en reintentos', function () {
    Badge::query()->create([
        'slug' => 'novato-logica', 'title' => 'Novato en Lógica', 'description' => 'Aprobó tu primer quiz',
    ]);
    BadgeRule::query()->create([
        'badge_id' => Badge::query()->where('slug', 'novato-logica')->first()->id,
        'event' => 'quiz_passed',
        'conditions' => ['count' => 1],
        'is_active' => true,
    ]);

    passQuiz($this->user, $this->quiz);
    passQuiz($this->user, $this->quiz); // reintento

    expect(UserBadge::query()->where('user_id', $this->user->id)->count())->toBe(1); // RB-07
});

it('RF-17: /me/badges distingue obtenidas y bloqueadas', function () {
    $b1 = Badge::query()->create([
        'slug' => 'novato-logica', 'title' => 'Novato en Lógica', 'description' => 'x',
    ]);
    Badge::query()->create([
        'slug' => 'maestro-pseudocodigo', 'title' => 'Maestro del Pseudocódigo', 'description' => 'y',
    ]);

    UserBadge::query()->create([
        'user_id' => $this->user->id, 'badge_id' => $b1->id, 'awarded_at' => now(),
    ]);

    $token = $this->user->createToken('spa')->plainTextToken;
    $res = getJson('/api/v1/me/badges', ['Authorization' => "Bearer $token"])->assertStatus(200);

    expect($res->json('obtained.0.slug'))->toBe('novato-logica');
    expect(collect($res->json('locked'))->pluck('slug'))->toContain('maestro-pseudocodigo');
});

it('RF-18: ranking global ordena por XP descendente', function () {
    $u1 = User::factory()->student()->create(['name' => 'Primero']);
    $u2 = User::factory()->student()->create(['name' => 'Segundo']);

    $p1 = \App\Domain\Gamification\Models\UserXpProfile::query()->create([
        'user_id' => $u1->id, 'xp_total' => 250, 'xp_level' => 3,
    ]);
    \App\Domain\Gamification\Models\UserXpProfile::query()->create([
        'user_id' => $u2->id, 'xp_total' => 90, 'xp_level' => 1,
    ]);

    $token = $this->user->createToken('spa')->plainTextToken;
    $res = getJson('/api/v1/rankings?scope=global', ['Authorization' => "Bearer $token"])->assertStatus(200);

    expect($res->json('data.0.name'))->toBe('Primero');
    expect($res->json('data.0.position'))->toBe(1);
    expect($res->json('data.1.name'))->toBe('Segundo');
});

it('RB-08: el ranking NO cambia por el pretest (solo quiz valida)', function () {
    // ya cubierto por DiagnosticTest (RB-11): el pretest no crea perfil XP
    // aquí verificamos que un quiz reprobado tampoco suma
    $token = $this->user->createToken('spa')->plainTextToken;

    $start = postJson("/api/v1/quizzes/{$this->quiz->id}/attempts", [], ['Authorization' => "Bearer $token"]);
    $attemptId = $start->json('attempt_id');

    $quizData = getJson("/api/v1/quizzes/{$this->quiz->id}", ['Authorization' => "Bearer $token"])->json('data');
    foreach ($quizData['questions'] as $q) {
        $wrong = collect($q['options'])->firstWhere('option_text', '5');
        postJson("/api/v1/quizzes/{$this->quiz->id}/attempts/{$attemptId}/answers", [
            'question_id' => $q['id'],
            'option_id' => $wrong['id'],
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);
    }
    postJson("/api/v1/quizzes/{$this->quiz->id}/attempts/{$attemptId}/submit", [], ['Authorization' => "Bearer $token"]);

    expect(\App\Domain\Gamification\Models\UserXpProfile::query()->where('user_id', $this->user->id)->exists())->toBeFalse();
});
