<?php

use App\Domain\Users\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Fase 11: Support (RF-24) + Audit (RF-23).
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->adminToken = $this->admin->createToken('spa')->plainTextToken;
    $this->teacher = User::factory()->teacher()->create();
    $this->teacherToken = $this->teacher->createToken('spa')->plainTextToken;
    $this->student = User::factory()->student()->create();
    $this->studentToken = $this->student->createToken('spa')->plainTextToken;
});

it('RF-24: docente reporta un error y admin lo lista', function () {
    clean_guard();
    $create = postJson('/api/v1/support/tickets', [
        'kind' => 'error',
        'description' => 'La lección 3 no muestra el pseudocódigo completo.',
        'module' => 'curriculum',
    ], ['Authorization' => "Bearer {$this->teacherToken}"])->assertStatus(201);

    expect($create->json('status'))->toBe('open');

    clean_guard();
    $list = getJson('/api/v1/admin/support-tickets?status=open', ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertStatus(200);

    expect($list->json('data.0.description'))->toContain('pseudocódigo');
});

it('RF-24: descripción demasiado corta → 422', function () {
    postJson('/api/v1/support/tickets', [
        'kind' => 'error',
        'description' => 'corta',
    ], ['Authorization' => "Bearer {$this->teacherToken}"])->assertStatus(422);
});

it('RB-09: estudiante NO lista tickets ni audit-logs', function () {
    clean_guard();
    getJson('/api/v1/admin/support-tickets', ['Authorization' => "Bearer {$this->studentToken}"])->assertStatus(403);

    clean_guard();
    getJson('/api/v1/admin/audit-logs', ['Authorization' => "Bearer {$this->studentToken}"])->assertStatus(403);
});

it('RF-23: acción admin queda en la bitácora', function () {
    clean_guard();
    postJson("/api/v1/admin/support-tickets/999/status", ['status' => 'resolved'], ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertStatus(404); // no existe el ticket

    // crear ticket real y cambiarlo
    $create = postJson('/api/v1/support/tickets', [
        'kind' => 'suggestion', 'description' => 'Sugerencia: agregar modo oscuro al aula.',
    ], ['Authorization' => "Bearer {$this->teacherToken}"]);
    $id = $create->json('id');

    clean_guard();
    postJson("/api/v1/admin/support-tickets/{$id}/status", ['status' => 'resolved'], ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertStatus(200);

    clean_guard();
    $logs = getJson('/api/v1/admin/audit-logs', ['Authorization' => "Bearer {$this->adminToken}"])->assertStatus(200);
    expect(collect($logs->json('data'))->pluck('action'))->toContain('support.status_changed');
});

it('permite a estudiantes y docentes listar exclusivamente sus propios tickets vía /api/v1/support/tickets/my', function () {
    clean_guard();
    postJson('/api/v1/support/tickets', [
        'kind' => 'error',
        'description' => 'Error reportado por el estudiante en el módulo 1.',
        'module' => 'variables',
    ], ['Authorization' => "Bearer {$this->studentToken}"])->assertStatus(201);

    clean_guard();
    $studentTickets = getJson('/api/v1/support/tickets/my', ['Authorization' => "Bearer {$this->studentToken}"])
        ->assertStatus(200);
    expect($studentTickets->json('data'))->toHaveCount(1);
    expect($studentTickets->json('data.0.description'))->toContain('Error reportado por el estudiante');

    clean_guard();
    $teacherTickets = getJson('/api/v1/support/tickets/my', ['Authorization' => "Bearer {$this->teacherToken}"])
        ->assertStatus(200);
    expect($teacherTickets->json('data'))->toHaveCount(0);
});

it('permite a administradores consultar métricas globales vía /api/v1/admin/metrics y deniega a otros', function () {
    clean_guard();
    $res = getJson('/api/v1/admin/metrics', ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertStatus(200);

    expect($res->json('teachers'))->toHaveKeys(['pending', 'active', 'total']);
    expect($res->json('students'))->toHaveKey('active');
    expect($res->json('classrooms'))->toHaveKey('active');
    expect($res->json('tickets'))->toHaveKeys(['open', 'in_review', 'resolved', 'total']);
    expect($res->json('recent_logs'))->toBeArray();

    clean_guard();
    getJson('/api/v1/admin/metrics', ['Authorization' => "Bearer {$this->studentToken}"])
        ->assertStatus(403);
});

