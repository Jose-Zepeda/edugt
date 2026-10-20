<?php

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Classrooms\Models\ClassroomStudent;
use App\Domain\Gamification\Models\UserXpProfile;
use App\Domain\Users\Models\User;
use function Pest\Laravel\getJson;

/**
 * Tests para ranking por aula (RF-18, RF-19) y soporte multi-aula.
 */
describe('Classroom Rankings', function () {
    beforeEach(function () {
        $this->teacher = User::factory()->teacher()->create(['name' => 'Profesor Carlos']);
        $this->classroomA = Classroom::query()->create([
            'name' => 'Aula A - Programación',
            'teacher_id' => $this->teacher->id,
            'academic_year' => 2026,
            'is_active' => true,
        ]);
        $this->classroomB = Classroom::query()->create([
            'name' => 'Aula B - Lógica',
            'teacher_id' => $this->teacher->id,
            'academic_year' => 2026,
            'is_active' => true,
        ]);
    });

    it('incluye a todas las personas inscritas en el aula, incluso con 0 XP', function () {
        $student1 = User::factory()->student()->create(['name' => 'Ana López']);
        $student2 = User::factory()->student()->create(['name' => 'Beto Morales']);
        $student3 = User::factory()->student()->create(['name' => 'Carlos Ramos']);

        // Inscribir a los 3 en el Aula A
        ClassroomStudent::query()->create(['classroom_id' => $this->classroomA->id, 'student_id' => $student1->id, 'state' => 'active', 'joined_at' => now()]);
        ClassroomStudent::query()->create(['classroom_id' => $this->classroomA->id, 'student_id' => $student2->id, 'state' => 'active', 'joined_at' => now()]);
        ClassroomStudent::query()->create(['classroom_id' => $this->classroomA->id, 'student_id' => $student3->id, 'state' => 'active', 'joined_at' => now()]);

        // Solo Ana tiene perfil de XP generado
        UserXpProfile::query()->create([
            'user_id' => $student1->id,
            'xp_total' => 150,
            'xp_level' => 2,
        ]);

        $token = $student2->createToken('spa')->plainTextToken;

        $res = getJson("/api/v1/rankings?scope=classroom&classroom_id={$this->classroomA->id}", [
            'Authorization' => "Bearer $token",
        ])->assertStatus(200);

        $data = $res->json('data');
        expect($data)->toHaveCount(3);

        $names = collect($data)->pluck('name')->all();
        expect($names)->toContain('Ana López')
            ->toContain('Beto Morales')
            ->toContain('Carlos Ramos');

        // Ana debe estar en 1° lugar con 150 XP
        expect($data[0]['name'])->toBe('Ana López');
        expect($data[0]['xp_total'])->toBe(150);
        expect($data[0]['position'])->toBe(1);

        // Los estudiantes sin XP deben tener 0 XP y nivel 1
        $beto = collect($data)->firstWhere('name', 'Beto Morales');
        expect($beto['xp_total'])->toBe(0);
        expect($beto['xp_level'])->toBe(1);
    });

    it('permite consultar los rankings independientes para estudiantes en diferentes aulas', function () {
        $student = User::factory()->student()->create(['name' => 'Estudiante Multiaula']);
        $compA = User::factory()->student()->create(['name' => 'Compañero Solo A']);
        $compB = User::factory()->student()->create(['name' => 'Compañero Solo B']);

        // Inscribir en Aula A
        ClassroomStudent::query()->create(['classroom_id' => $this->classroomA->id, 'student_id' => $student->id, 'state' => 'active', 'joined_at' => now()]);
        ClassroomStudent::query()->create(['classroom_id' => $this->classroomA->id, 'student_id' => $compA->id, 'state' => 'active', 'joined_at' => now()]);

        // Inscribir en Aula B
        ClassroomStudent::query()->create(['classroom_id' => $this->classroomB->id, 'student_id' => $student->id, 'state' => 'active', 'joined_at' => now()]);
        ClassroomStudent::query()->create(['classroom_id' => $this->classroomB->id, 'student_id' => $compB->id, 'state' => 'active', 'joined_at' => now()]);

        // XP Profiles
        UserXpProfile::query()->create(['user_id' => $student->id, 'xp_total' => 200, 'xp_level' => 3]);
        UserXpProfile::query()->create(['user_id' => $compA->id, 'xp_total' => 300, 'xp_level' => 4]);
        UserXpProfile::query()->create(['user_id' => $compB->id, 'xp_total' => 50, 'xp_level' => 1]);

        $token = $student->createToken('spa')->plainTextToken;

        // 1. Ranking de Aula A
        $resA = getJson("/api/v1/rankings?scope=classroom&classroom_id={$this->classroomA->id}", [
            'Authorization' => "Bearer $token",
        ])->assertStatus(200);

        expect($resA->json('classroom_id'))->toBe($this->classroomA->id);
        expect($resA->json('classroom_name'))->toBe('Aula A - Programación');
        $dataA = $resA->json('data');
        expect($dataA)->toHaveCount(2);
        expect(collect($dataA)->pluck('name')->all())->toContain('Compañero Solo A')->toContain('Estudiante Multiaula');
        expect(collect($dataA)->pluck('name')->all())->not->toContain('Compañero Solo B');

        // En Aula A: Compañero Solo A (300 XP) es 1°, Estudiante Multiaula (200 XP) es 2°
        expect($dataA[0]['name'])->toBe('Compañero Solo A');
        expect($dataA[1]['name'])->toBe('Estudiante Multiaula');

        // 2. Ranking de Aula B
        $resB = getJson("/api/v1/rankings?scope=classroom&classroom_id={$this->classroomB->id}", [
            'Authorization' => "Bearer $token",
        ])->assertStatus(200);

        expect($resB->json('classroom_id'))->toBe($this->classroomB->id);
        expect($resB->json('classroom_name'))->toBe('Aula B - Lógica');
        $dataB = $resB->json('data');
        expect($dataB)->toHaveCount(2);
        expect(collect($dataB)->pluck('name')->all())->toContain('Compañero Solo B')->toContain('Estudiante Multiaula');
        expect(collect($dataB)->pluck('name')->all())->not->toContain('Compañero Solo A');

        // En Aula B: Estudiante Multiaula (200 XP) es 1°, Compañero Solo B (50 XP) es 2°
        expect($dataB[0]['name'])->toBe('Estudiante Multiaula');
        expect($dataB[1]['name'])->toBe('Compañero Solo B');

        // La respuesta incluye la lista de aulas del estudiante para el selector
        $classrooms = $resA->json('classrooms');
        expect(collect($classrooms)->pluck('id')->all())->toContain($this->classroomA->id)->toContain($this->classroomB->id);
    });

    it('resuelve automáticamente el aula activa si no se envía classroom_id', function () {
        $student = User::factory()->student()->create(['name' => 'Estudiante Con Aula']);
        ClassroomStudent::query()->create(['classroom_id' => $this->classroomA->id, 'student_id' => $student->id, 'state' => 'active', 'joined_at' => now()]);

        $token = $student->createToken('spa')->plainTextToken;

        $res = getJson('/api/v1/rankings?scope=classroom', [
            'Authorization' => "Bearer $token",
        ])->assertStatus(200);

        expect($res->json('classroom_id'))->toBe($this->classroomA->id);
        expect($res->json('classroom_name'))->toBe('Aula A - Programación');
        expect($res->json('data'))->toHaveCount(1);
        expect($res->json('data.0.name'))->toBe('Estudiante Con Aula');
    });
});
