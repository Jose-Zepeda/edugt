<?php

namespace Database\Seeders;

use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds de Docentes, Admin y Estudiantes Base con identidades reales guatemaltecas
 * para el Instituto Nacional de Educación Diversificada (INED) Jutiapa.
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

        // 1. Administrador institucional
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@edugt.test'],
            [
                'name' => 'Ing. Administrador de Plataforma',
                'password' => Hash::make('admin1234'),
            ]
        );
        $admin->forceFill([
            'name' => 'Ing. Administrador de Plataforma',
            'role' => 'admin',
            'status' => 'active',
        ])->save();

        // 2. Docentes con nombres reales y cargos del INED Jutiapa
        $teachers = [
            'marta@edugt.test' => 'Profa. Marta Lidia Quevedo de García',
            'carlos@edugt.test' => 'Prof. Carlos Humberto Ramírez Sandoval',
            'lucia@edugt.test' => 'Licda. Lucía Marisol Morales Pineda',
        ];

        foreach ($teachers as $email => $name) {
            $t = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('docente1234'),
                ]
            );
            $t->forceFill([
                'name' => $name,
                'role' => 'teacher',
                'status' => 'active',
            ])->save();
        }

        $pending = User::query()->firstOrCreate(
            ['email' => 'pendiente@edugt.test'],
            [
                'name' => 'Prof. Edwin Rodolfo Sandoval García',
                'password' => Hash::make('docente1234'),
            ]
        );
        $pending->forceFill([
            'name' => 'Prof. Edwin Rodolfo Sandoval García',
            'role' => 'teacher',
            'status' => 'pending_approval',
        ])->save();

        // 3. Estudiantes base actualizados con nombres completos guatemaltecos
        $baseStudents = [
            1 => ['name' => 'Kevin Alexander Morales Lima', 'avatar' => 'assets/img/avatars/avatar-01.jpg'],
            2 => ['name' => 'Yoselin Paola Cruz Salguero', 'avatar' => 'assets/img/avatars/avatar-11.jpg'],
            3 => ['name' => 'Erick Fernando Castillo Zepeda', 'avatar' => 'assets/img/avatars/avatar-02.jpg'],
            4 => ['name' => 'Lesly Daniela Carrillo Godoy', 'avatar' => 'assets/img/avatars/avatar-12.jpg'],
            5 => ['name' => 'Anthony René Pineda Figueroa', 'avatar' => 'assets/img/avatars/avatar-03.jpg'],
            6 => ['name' => 'Gerson David Rodas Barrientos', 'avatar' => 'assets/img/avatars/avatar-05.jpg'],
            7 => ['name' => 'Andrea Elizabeth Salazar Cifuentes', 'avatar' => 'assets/img/avatars/avatar-13.jpg'],
            8 => ['name' => 'Bryan Alexis García Monterroso', 'avatar' => 'assets/img/avatars/avatar-06.jpg'],
        ];

        foreach ($baseStudents as $n => $data) {
            $s = User::query()->firstOrCreate(
                ['email' => "estudiante{$n}@edugt.test"],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('estudiante1234'),
                    'avatar_url' => $data['avatar'],
                ]
            );
            $s->forceFill([
                'name' => $data['name'],
                'role' => 'student',
                'status' => 'active',
                'avatar_url' => $data['avatar'],
            ])->save();
        }

        // 4. Aulas con grados y secciones reales de diversificado en Jutiapa
        $marta = User::query()->where('email', 'marta@edugt.test')->first();
        $carlos = User::query()->where('email', 'carlos@edugt.test')->first();
        $lucia = User::query()->where('email', 'lucia@edugt.test')->first();

        $classrooms = [
            [$marta, '4to. Bachillerato en Ciencias y Letras - Sección A', '2026', 'AULA2026A', 'Cuarto Bachillerato en Ciencias y Letras'],
            [$carlos, '5to. Perito Contador con Orientación en Computación', '2026', 'AULA2026B', 'Quinto Perito Contador'],
            [$lucia, '4to. Bachillerato en Computación - Sección B', '2026', 'AULA2026C', 'Cuarto Bachillerato en Computación'],
        ];

        foreach ($classrooms as [$teacher, $name, $year, $code, $gradeLevel]) {
            $classroom = \App\Domain\Classrooms\Models\Classroom::query()->firstOrCreate(
                ['teacher_id' => $teacher->id, 'name' => $name],
                [
                    'institution' => 'Instituto Nacional de Educación Diversificada (INED) Jutiapa',
                    'grade_level' => $gradeLevel,
                    'academic_year' => $year,
                    'is_active' => true,
                ]
            );
            $classroom->forceFill([
                'institution' => 'Instituto Nacional de Educación Diversificada (INED) Jutiapa',
                'grade_level' => $gradeLevel,
            ])->save();

            \App\Domain\Classrooms\Models\ClassroomCode::query()->firstOrCreate(
                ['code' => $code],
                [
                    'classroom_id' => $classroom->id,
                    'is_active' => true,
                ]
            );
        }
    }
}
