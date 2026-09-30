<?php

use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\Question;
use App\Domain\Assessments\Models\QuestionOption;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Curriculum\Models\Mission;
use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Fase 9: Analytics + Reporting PDF (RF-20, RF-21, RF-22).
 */
function seedReportingScenario(): array
{
    $teacher = User::factory()->teacher()->create();
    $students = User::factory()->count(3)->student()->create();

    $classroom = \App\Domain\Classrooms\Models\Classroom::query()->create([
        'teacher_id' => $teacher->id, 'name' => 'Aula X', 'academic_year' => '2026',
    ]);
    $code = \App\Domain\Classrooms\Models\ClassroomCode::query()->create([
        'classroom_id' => $classroom->id, 'code' => 'CODIGO1', 'is_active' => true,
    ]);
    foreach ($students as $s) {
        \App\Domain\Classrooms\Models\ClassroomStudent::query()->create([
            'classroom_id' => $classroom->id, 'student_id' => $s->id,
            'joined_at' => now(), 'state' => 'active',
        ]);
    }

    $mission = Mission::query()->create(['slug' => 'mr', 'title' => 'MR', 'status' => 'published']);
    $module = Module::query()->create([
        'mission_id' => $mission->id, 'title' => 'ModR',
        'required_level' => 1, 'order_index' => 0, 'status' => 'published',
    ]);
    $quiz = Quiz::query()->create(['module_id' => $module->id, 'title' => 'QR', 'passing_score' => 70]);
    $q = Question::query()->create(['quiz_id' => $quiz->id, 'statement' => 'P?', 'order_index' => 0]);
    $optOk = QuestionOption::query()->create([
        'question_id' => $q->id, 'option_text' => 'OK', 'is_correct' => true, 'order_index' => 0,
    ]);
    $optBad = QuestionOption::query()->create([
        'question_id' => $q->id, 'option_text' => 'BAD', 'is_correct' => false, 'order_index' => 1,
    ]);

    // intentos: 2 aprobados, 1 reprobado (para métricas variadas)
    foreach ([$students[0], $students[1]] as $s) {
        $a = \App\Domain\Assessments\Models\QuizAttempt::query()->create([
            'user_id' => $s->id, 'quiz_id' => $quiz->id,
            'attempt_number' => 1, 'started_at' => now(),
            'submitted_at' => now(), 'score' => 100, 'status' => 'submitted',
        ]);
        \App\Domain\Assessments\Models\AttemptAnswer::query()->create([
            'quiz_attempt_id' => $a->id, 'question_id' => $q->id,
            'question_option_id' => $optOk->id, 'is_correct' => true,
        ]);
    }
    $a3 = \App\Domain\Assessments\Models\QuizAttempt::query()->create([
        'user_id' => $students[2]->id, 'quiz_id' => $quiz->id,
        'attempt_number' => 1, 'started_at' => now(),
        'submitted_at' => now(), 'score' => 0, 'status' => 'submitted',
    ]);
    \App\Domain\Assessments\Models\AttemptAnswer::query()->create([
        'quiz_attempt_id' => $a3->id, 'question_id' => $q->id,
        'question_option_id' => $optBad->id, 'is_correct' => false,
    ]);

    return compact('teacher', 'classroom', 'quiz');
}

beforeEach(function () {
    $seed = seedReportingScenario();
    $teacher = $seed['teacher'];
    $classroom = $seed['classroom'];
    $this->teacher = $teacher;
    $this->classroom = $classroom;
    $this->teacherToken = $teacher->createToken('spa')->plainTextToken;
    $this->outsider = User::factory()->teacher()->create();
    $this->outsiderToken = $this->outsider->createToken('spa')->plainTextToken;
});

it('RF-21: docente dueño consulta métricas de su aula', function () {
    $res = getJson("/api/v1/classrooms/{$this->classroom->id}/analytics", ['Authorization' => "Bearer {$this->teacherToken}"])
        ->assertStatus(200);

    $metrics = $res->json('data.metrics');
    expect($metrics['total_attempts'])->toBe(3);
    expect($metrics['passed_attempts'])->toBe(2);
    expect($metrics['pass_rate'])->toBe(66.7);

    // la pregunta con más error: la única pregunta del quiz
    expect($res->json('data.top_failed_questions.0.error_rate'))->toBe(33.3);
});

it('RB-09: docente NO dueño recibe 403', function () {
    getJson("/api/v1/classrooms/{$this->classroom->id}/analytics", ['Authorization' => "Bearer {$this->outsiderToken}"])
        ->assertStatus(403);
});

it('RF-22/RB-12: el reporte es PDF (magic bytes %PDF-) y no existe endpoint Excel', function () {
    $res = postJson('/api/v1/reports', [
        'kind' => 'classroom_performance',
        'classroom_id' => $this->classroom->id,
    ], ['Authorization' => "Bearer {$this->teacherToken}"])->assertStatus(200);

    expect(str_starts_with($res->getContent(), '%PDF-'))->toBeTrue();

    // el tipo de reporte inválido → 422 (no Excel alternativo)
    postJson('/api/v1/reports', ['kind' => 'excel'], ['Authorization' => "Bearer {$this->teacherToken}"])
        ->assertStatus(422);
});

it('RF-28: comparación pre/post refleja ganancia media', function () {
    $students = User::query()->where('role', 'student')->get();

    $test = \App\Domain\Diagnostic\Models\DiagnosticTest::query()->create([
        'kind' => 'pre', 'version' => 1, 'question_count' => 12, 'is_active' => true,
    ]);
    foreach ($students as $s) {
        \App\Domain\Diagnostic\Models\DiagnosticAttempt::query()->create([
            'user_id' => $s->id, 'diagnostic_test_id' => $test->id, 'kind' => 'pre',
            'score_total' => 40, 'started_at' => now(), 'submitted_at' => now(), 'status' => 'completed',
        ]);
    }

    $res = getJson("/api/v1/classrooms/{$this->classroom->id}/analytics", ['Authorization' => "Bearer {$this->teacherToken}"]);
    $prePost = $res->json('data.pre_post');

    // 3 pretests, 0 postest todavía → pares (base Wilcoxon) = 0
    expect($prePost['pre_students'])->toBe(3);
    expect($prePost['post_students'])->toBe(0);
    expect($prePost['paired_students'])->toBe(0);
    expect($prePost['post_avg'])->toBeNull();
});
