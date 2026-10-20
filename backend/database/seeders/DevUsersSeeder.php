<?php

namespace Database\Seeders;

use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds de QA (contrato AGENTS.md §2 "Datos de prueba"):
 *  - admin + 3 docentes activos + 1 docente pendiente + 8 estudiantes
 *  - 2 aulas con códigos de inscripción
 */
class DevUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar usuario temporal de diagnóstico OAuth si existiera
        $tempOAuth = User::query()->where('email', 'test_oauth_check@edugt.test')->first();
        if ($tempOAuth !== null) {
            $tempOAuth->tokens()->delete();
            $tempOAuth->delete();
        }

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@edugt.test'],
            [
                'name' => 'Admin EduGT',
                'password' => \Illuminate\Support\Facades\Hash::make('admin1234'),
            ]
        );
        $admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

        foreach (['marta@edugt.test', 'carlos@edugt.test', 'lucia@edugt.test'] as $email) {
            $t = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Docente '.Str::before($email, '@'),
                    'password' => \Illuminate\Support\Facades\Hash::make('docente1234'),
                ]
            );
            $t->forceFill(['role' => 'teacher', 'status' => 'active'])->save();
        }

        $pending = User::query()->firstOrCreate(
            ['email' => 'pendiente@edugt.test'],
            [
                'name' => 'Docente Pendiente',
                'password' => \Illuminate\Support\Facades\Hash::make('docente1234'),
            ]
        );
        $pending->forceFill(['role' => 'teacher', 'status' => 'pending_approval'])->save();

        $students = [];
        foreach (range(1, 8) as $n) {
            $s = User::query()->firstOrCreate(
                ['email' => "estudiante{$n}@edugt.test"],
                [
                    'name' => "Estudiante {$n}",
                    'password' => \Illuminate\Support\Facades\Hash::make('estudiante1234'),
                ]
            );
            $s->forceFill(['role' => 'student', 'status' => 'active'])->save();
            $students[] = $s;
        }

        $marta = User::query()->where('email', 'marta@edugt.test')->first();
        $carlos = User::query()->where('email', 'carlos@edugt.test')->first();

        foreach ([
            [$marta, 'Cuarto Bachillerato A', '2026', 'AULA2026A'],
            [$carlos, 'Perito Contador B', '2026', 'AULA2026B'],
        ] as [$teacher, $name, $year, $code]) {
            $classroom = \App\Domain\Classrooms\Models\Classroom::query()->firstOrCreate(
                ['teacher_id' => $teacher->id, 'name' => $name],
                [
                    'institution' => 'Instituto de Jutiapa',
                    'grade_level' => $name,
                    'academic_year' => $year,
                    'is_active' => true,
                ]
            );
            \App\Domain\Classrooms\Models\ClassroomCode::query()->firstOrCreate(
                ['code' => $code],
                [
                    'classroom_id' => $classroom->id,
                    'is_active' => true,
                ]
            );

            foreach (array_slice($students, 0, 4) as $s) {
                \App\Domain\Classrooms\Models\ClassroomStudent::query()->firstOrCreate(
                    [
                        'classroom_id' => $classroom->id,
                        'student_id' => $s->id,
                    ],
                    [
                        'joined_at' => now(),
                        'state' => 'active',
                    ]
                );
            }
        }
    }
}
