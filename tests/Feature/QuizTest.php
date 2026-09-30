<?php

use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\Question;
use App\Domain\Assessments\Models\QuestionOption;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Curriculum\Models\Mission;
use App\Domain\Gamification\Models\UserXpProfile;
use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Fase 6: Assessments — Card-Flow, calificación en servidor, RB-06/15, RB-10.
 */
function seedQuiz(): Quiz
{
    $mission = Mission::query()->create([
        'slug' => 'm', 'title' => 'M', 'status' => 'published',
    ]);
    $module = Module::query()->create([
        'mission_id' => $mission->id, 'title' => 'Mod',
        'required_level' => 1, 'order_index' => 0, 'status' => 'published',
    ]);

    $quiz = Quiz::query()->create([
        'module_id' => $module->id, 'title' => 'Quiz', 'passing_score' => 70,
    ]);

    // 2 preguntas, cada una con 3 opciones; la correcta es la opción B (is_correct=true)
    foreach ([1, 2] as $n) {
        $q = Question::query()->create([
            'quiz_id' => $quiz->id,
            'statement' => "Pregunta {$n}",
            'explanation' => "Explicación {$n}",
            'order_index' => $n,
        ]);

        foreach (['A', 'B', 'C'] as $i => $letter) {
            QuestionOption::query()->create([
                'question_id' => $q->id,
                'option_text' => "Opción {$letter}",
                'is_correct' => $i === 1,
                'feedback' => $i === 1 ? '¡Correcto!' : "Incorrecto: {$letter}",
                'order_index' => $i,
            ]);
        }
    }

    return $quiz;
}

beforeEach(function () {
    $this->quiz = seedQuiz();
    $this->user = User::factory()->student()->create();
    $this->token = $this->user->createToken('spa')->plainTextToken;
});

it('descarga el quiz sin revelar is_correct ni feedback (RB-10)', function () {
    $res = getJson("/api/v1/quizzes/{$this->quiz->id}", ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);

    $q = $res->json('data.questions.0');
    expect($q['options'][0])->not->toHaveKey('is_correct');
    expect($q['options'][0])->not->toHaveKey('feedback');
});

it('califica en servidor y devuelve feedback inmediato por opción (RF-12, RF-13)', function () {
    $start = postJson("/api/v1/quizzes/{$this->quiz->id}/attempts", [], ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(201);
    $attemptId = $start->json('attempt_id');

    $quizData = getJson("/api/v1/quizzes/{$this->quiz->id}", ['Authorization' => "Bearer {$this->token}"])->json('data');

    // respuesta incorrecta en Q1: devuelve feedback de la opción incorrecta
    $wrongOptionId = null;
    $rightOptionId = null;
    foreach ($quizData['questions'][0]['options'] as $o) {
        if (str_contains($o['option_text'], 'A')) {
            $wrongOptionId = $o['id'];
        }
        if (str_contains($o['option_text'], 'B')) {
            $rightOptionId = $o['id'];
        }
    }

    $res = postJson("/api/v1/quizzes/{$this->quiz->id}/attempts/{$attemptId}/answers", [
        'question_id' => $quizData['questions'][0]['id'],
        'option_id' => $wrongOptionId,
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    expect($res->json('is_correct'))->toBeFalse();
    expect($res->json('feedback'))->toContain('Incorrecto');

    // respuesta correcta en Q2
    $q2Right = null;
    foreach (getJson("/api/v1/quizzes/{$this->quiz->id}", ['Authorization' => "Bearer {$this->token}"])->json('data.questions.1.options') as $o) {
        if (str_contains($o['option_text'], 'B')) {
            $q2Right = $o['id'];
        }
    }

    $res2 = postJson("/api/v1/quizzes/{$this->quiz->id}/attempts/{$attemptId}/answers", [
        'question_id' => $quizData['questions'][1]['id'],
        'option_id' => $q2Right,
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    expect($res2->json('is_correct'))->toBeTrue();
});

it('submit calcula score 50 y reprueba (RB-05)', function () {
    $start = postJson("/api/v1/quizzes/{$this->quiz->id}/attempts", [], ['Authorization' => "Bearer {$this->token}"]);
    $attemptId = $start->json('attempt_id');

    $quizData = getJson("/api/v1/quizzes/{$this->quiz->id}", ['Authorization' => "Bearer {$this->token}"])->json('data');

    foreach ($quizData['questions'] as $q) {
        $wrong = collect($q['options'])->firstWhere(fn ($o) => str_contains($o['option_text'], 'A'));
        postJson("/api/v1/quizzes/{$this->quiz->id}/attempts/{$attemptId}/answers", [
            'question_id' => $q['id'],
            'option_id' => $wrong['id'],
        ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);
    }

    $res = postJson("/api/v1/quizzes/{$this->quiz->id}/attempts/{$attemptId}/submit", [], ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);

    expect($res->json('score'))->toBe(0);
    expect($res->json('passed'))->toBeFalse();
    expect($res->json('xp_awarded'))->toBeFalse();
});

it('RB-06/15: reintento 2 otorga XP×0.8, reintento 3 otorga XP×0.64', function () {
    // helper local: corre un intento completo respondiendo todo correcto
    $runAttempt = function () {
        $start = postJson("/api/v1/quizzes/{$this->quiz->id}/attempts", [], ['Authorization' => "Bearer {$this->token}"]);
        $attemptId = $start->json('attempt_id');

        $quizData = getJson("/api/v1/quizzes/{$this->quiz->id}", ['Authorization' => "Bearer {$this->token}"])->json('data');

        foreach ($quizData['questions'] as $q) {
            $right = collect($q['options'])->firstWhere(fn ($o) => str_contains($o['option_text'], 'B'));
            postJson("/api/v1/quizzes/{$this->quiz->id}/attempts/{$attemptId}/answers", [
                'question_id' => $q['id'],
                'option_id' => $right['id'],
            ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);
        }

        return postJson("/api/v1/quizzes/{$this->quiz->id}/attempts/{$attemptId}/submit", [], ['Authorization' => "Bearer {$this->token}"])
            ->assertStatus(200);
    };

    // intento 1: 50 XP (base)
    $r1 = $runAttempt();
    expect($r1->json('attempt_number'))->toBe(1);
    expect($r1->json('xp_amount'))->toBe(50);

    // intento 2: floor(50×0.8) = 40
    $r2 = $runAttempt();
    expect($r2->json('attempt_number'))->toBe(2);
    expect($r2->json('xp_amount'))->toBe(40);

    // intento 3: floor(50×0.64) = 32
    $r3 = $runAttempt();
    expect($r3->json('attempt_number'))->toBe(3);
    expect($r3->json('xp_amount'))->toBe(32);

    $profile = UserXpProfile::query()->where('user_id', $this->user->id)->first();
    expect($profile->xp_total)->toBe(50 + 40 + 32);
});

it('RF-14: historial de intentos expone score y ordinales', function () {
    postJson("/api/v1/quizzes/{$this->quiz->id}/attempts", [], ['Authorization' => "Bearer {$this->token}"]);
    postJson("/api/v1/quizzes/{$this->quiz->id}/attempts", [], ['Authorization' => "Bearer {$this->token}"]);

    $res = getJson("/api/v1/quizzes/{$this->quiz->id}/attempts", ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);

    // 0 submits → historial vacío (solo submitted)
    expect($res->json('data'))->toBeEmpty();
});
