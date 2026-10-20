<?php

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Classrooms\Models\ClassroomCode;
use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Fase 8: aulas, códigos regenerables, membresías.
 */
beforeEach(function () {
    $this->teacher = User::factory()->teacher()->create();
    $this->token = $this->teacher->createToken('spa')->plainTextToken;
    $this->student = User::factory()->student()->create();
    $this->studentToken = $this->student->createToken('spa')->plainTextToken;
});

it('docente pendiente NO puede crear aula (RB-02, criterio de Fase 3/8)', function () {
    $pending = User::factory()->pendingTeacher()->create();
    $token = $pending->createToken('spa')->plainTextToken;

    postJson('/api/v1/classrooms', [
        'name' => 'Aula', 'academic_year' => '2026',
    ], ['Authorization' => "Bearer $token"])->assertStatus(403);
});

it('docente activo crea aula con código de inscripción', function () {
    $res = postJson('/api/v1/classrooms', [
        'name' => 'Cuarto A', 'institution' => 'INEB', 'academic_year' => '2026',
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(201);

    expect($res->json('data.code'))->toBeString();
    expect(strlen($res->json('data.code')))->toBe(8);
});

it('estudiante se inscribe por código; duplicado da 409', function () {
    $create = postJson('/api/v1/classrooms', [
        'name' => 'Cuarto B', 'academic_year' => '2026',
    ], ['Authorization' => "Bearer {$this->token}"]);
    $code = $create->json('data.code');

    clean_guard();
    postJson('/api/v1/classrooms/join', ['code' => $code], ['Authorization' => "Bearer {$this->studentToken}"])
        ->assertStatus(201);

    clean_guard();
    postJson('/api/v1/classrooms/join', ['code' => $code], ['Authorization' => "Bearer {$this->studentToken}"])
        ->assertStatus(409)
        ->assertJsonPath('code', 'ALREADY_ENROLLED');
});

it('código regenerado invalida el anterior (criterio Fase 8)', function () {
    $create = postJson('/api/v1/classrooms', [
        'name' => 'Quinto A', 'academic_year' => '2026',
    ], ['Authorization' => "Bearer {$this->token}"]);
    $classroomId = $create->json('data.id');
    $oldCode = $create->json('data.code');

    $regen = postJson("/api/v1/classrooms/{$classroomId}/codes/regenerate", [], ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);
    $newCode = $regen->json('code');

    expect($newCode)->not->toBe($oldCode);

    // el código viejo ya no inscribe
    clean_guard();
    postJson('/api/v1/classrooms/join', ['code' => $oldCode], ['Authorization' => "Bearer {$this->studentToken}"])
        ->assertStatus(422);

    // el nuevo sí
    clean_guard();
    postJson('/api/v1/classrooms/join', ['code' => $newCode], ['Authorization' => "Bearer {$this->studentToken}"])
        ->assertStatus(201);
});

it('docente ve estudiantes inscritos con su progreso de lecciones y estado de pretest y puede retirarlos', function () {
    $create = postJson('/api/v1/classrooms', [
        'name' => 'Quinto B', 'academic_year' => '2026',
    ], ['Authorization' => "Bearer {$this->token}"]);
    $classroomId = $create->json('data.id');
    $code = $create->json('data.code');

    clean_guard();
    postJson('/api/v1/classrooms/join', ['code' => $code], ['Authorization' => "Bearer {$this->studentToken}"]);

    clean_guard();
    $students = getJson("/api/v1/classrooms/{$classroomId}/students", ['Authorization' => "Bearer {$this->token}"])
        ->assertStatus(200);
    expect($students->json('data.0.student_id'))->toBe($this->student->id);
    expect($students->json('data.0'))->toHaveKeys([
        'progress_percent',
        'completed_lessons',
        'total_lessons',
        'pretest_completed',
        'modules_progress',
        'xp_total',
        'badges_count',
    ]);

    clean_guard();
    postJson("/api/v1/classrooms/{$classroomId}/students/{$this->student->id}/state", [
        'state' => 'removed',
    ], ['Authorization' => "Bearer {$this->token}"])->assertStatus(200);

    // retirado: no aparece en la lista
    clean_guard();
    $after = getJson("/api/v1/classrooms/{$classroomId}/students", ['Authorization' => "Bearer {$this->token}"]);
    expect(collect($after->json('data'))->count())->toBe(0);
});

it('estudiante NO puede ver estudiantes de un aula (RB-09)', function () {
    $create = postJson('/api/v1/classrooms', [
        'name' => 'Quinto C', 'academic_year' => '2026',
    ], ['Authorization' => "Bearer {$this->token}"]);
    $classroomId = $create->json('data.id');

    clean_guard();
    getJson("/api/v1/classrooms/{$classroomId}/students", ['Authorization' => "Bearer {$this->studentToken}"])
        ->assertStatus(403);
});

it('docente dueño puede editar su aula', function () {
    $create = postJson('/api/v1/classrooms', [
        'name' => 'Original', 'academic_year' => '2026',
    ], ['Authorization' => "Bearer {$this->token}"]);
    $classroomId = $create->json('data.id');

    clean_guard();
    $res = \Pest\Laravel\putJson("/api/v1/classrooms/{$classroomId}", [
        'name' => 'Actualizado',
        'institution' => 'INEB Central',
        'grade_level' => 'Quinto Bachillerato',
        'academic_year' => '2026',
    ], ['Authorization' => "Bearer {$this->token}"]);

    $res->assertStatus(200)->assertJsonPath('data.name', 'Actualizado');
    expect(Classroom::find($classroomId)->name)->toBe('Actualizado');
});

it('docente dueño puede eliminar su aula', function () {
    $create = postJson('/api/v1/classrooms', [
        'name' => 'Para Eliminar', 'academic_year' => '2026',
    ], ['Authorization' => "Bearer {$this->token}"]);
    $classroomId = $create->json('data.id');

    clean_guard();
    $res = \Pest\Laravel\deleteJson("/api/v1/classrooms/{$classroomId}", [], ['Authorization' => "Bearer {$this->token}"]);
    $res->assertStatus(200);

    expect(Classroom::find($classroomId))->toBeNull();
});

it('estudiante puede listar las aulas en las que está inscrito', function () {
    $create = postJson('/api/v1/classrooms', [
        'name' => 'Quinto Computación', 'academic_year' => '2026', 'institution' => 'INEB Central',
    ], ['Authorization' => "Bearer {$this->token}"]);
    $code = $create->json('data.code');

    clean_guard();
    postJson('/api/v1/classrooms/join', ['code' => $code], ['Authorization' => "Bearer {$this->studentToken}"])
        ->assertStatus(201);

    clean_guard();
    $res = getJson('/api/v1/classrooms', ['Authorization' => "Bearer {$this->studentToken}"])
        ->assertStatus(200);

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.name'))->toBe('Quinto Computación');
    expect($res->json('data.0.teacher_name'))->toBe($this->teacher->name);
});
