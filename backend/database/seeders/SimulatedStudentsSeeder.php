<?php

namespace Database\Seeders;

use App\Domain\Assessments\Models\AttemptAnswer;
use App\Domain\Assessments\Models\QuizAttempt;
use App\Domain\Audit\Models\SupportTicket;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Classrooms\Models\ClassroomCode;
use App\Domain\Classrooms\Models\ClassroomStudent;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Diagnostic\Models\DiagnosticAnswer;
use App\Domain\Diagnostic\Models\DiagnosticAttempt;
use App\Domain\Diagnostic\Models\DiagnosticQuestion;
use App\Domain\Diagnostic\Models\DiagnosticTest;
use App\Domain\Gamification\Models\Badge;
use App\Domain\Gamification\Models\UserBadge;
use App\Domain\Gamification\Models\UserXpProfile;
use App\Domain\Gamification\Models\XpLedgerEntry;
use App\Domain\Lab\Models\LabAttempt;
use App\Domain\Lab\Models\LabChallenge;
use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder de simulación realista de prueba de campo / estudio piloto de tesis.
 * Contexto: Taller presencial de Pensamiento Computacional en el Laboratorio de Computación
 * del Instituto Nacional de Educación Diversificada (INED) Jutiapa.
 * Muestra: n=46 estudiantes distribuidos en 3 secciones reales con curva de aprendizaje auténtica.
 */
class SimulatedStudentsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Identificar Aulas
        $aulaMarta = Classroom::query()->where('name', 'like', '%Sección A%')->first();
        $aulaCarlos = Classroom::query()->where('name', 'like', '%Perito Contador%')->first();
        $aulaLucia = Classroom::query()->where('name', 'like', '%Sección B%')->first();

        if (! $aulaMarta || ! $aulaCarlos || ! $aulaLucia) {
            return;
        }

        // 2. Currículo y Evaluaciones
        $modules = Module::query()->where('status', 'published')->with(['lessons', 'quiz.questions.options'])->orderBy('order_index')->get();
        $preTest = DiagnosticTest::query()->where('kind', 'pre')->first();
        $postTest = DiagnosticTest::query()->where('kind', 'post')->first();
        $preQuestions = $preTest ? DiagnosticQuestion::query()->where('diagnostic_test_id', $preTest->id)->orderBy('order_index')->get() : collect();
        $postQuestions = $postTest ? DiagnosticQuestion::query()->where('diagnostic_test_id', $postTest->id)->orderBy('order_index')->get() : collect();
        $dimensionsList = ['abstraction', 'decomposition', 'pattern_recognition', 'algorithms', 'variables', 'conditionals', 'loops'];

        // 3. Matriz de Estudiantes del Estudio Piloto
        $cohort = [
            // =========================================================================
            // AULA 1: 4to. Bachillerato en Ciencias y Letras - Sección A (Marta) (22 alumnos)
            // =========================================================================
            [
                'email' => 'sofia.castillo.gt@gmail.com',
                'name' => 'Sofía Alejandra Castillo Morales',
                'avatar_url' => 'assets/img/avatars/avatar-11.jpg',
                'learning_track' => 'advanced',
                'classroom_id' => $aulaMarta->id,
                'streak' => 8,
                'pre_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 1, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 13, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 2, 'loops' => 1]],
                'completed_modules' => [1, 2, 3],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [100]],
                    ['mod' => 2, 'attempts' => [100]],
                    ['mod' => 3, 'attempts' => [100]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control', 'maestro-del-bucle', 'maestro-pseudocodigo'],
                'xp' => 520,
            ],
            [
                'email' => 'diego.reyes.pineda@gmail.com',
                'name' => 'Diego Fernando Reyes Pineda',
                'avatar_url' => 'assets/img/avatars/avatar-06.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaMarta->id,
                'streak' => 5,
                'pre_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 12, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 1]],
                'completed_modules' => [1, 2],
                'extra_lessons' => [3 => [0, 1]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control'],
                'xp' => 330,
            ],
            [
                'email' => 'melany.donis.gt@gmail.com',
                'name' => 'Melany Sofía Donis Melgar',
                'avatar_url' => 'assets/img/avatars/avatar-12.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaMarta->id,
                'streak' => 6,
                'pre_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 12, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 1]],
                'completed_modules' => [1, 2, 3],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [100]],
                    ['mod' => 2, 'attempts' => [80]],
                    ['mod' => 3, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control', 'maestro-del-bucle', 'maestro-pseudocodigo'],
                'xp' => 450,
            ],
            [
                'email' => 'genesis.tobar.gt@gmail.com',
                'name' => 'Génesis Andrea Tobar Alarcón',
                'avatar_url' => 'assets/img/avatars/avatar-13.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaMarta->id,
                'streak' => 5,
                'pre_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 12, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 1]],
                'completed_modules' => [1, 2],
                'extra_lessons' => [3 => [0, 1]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control'],
                'xp' => 330,
            ],
            [
                'email' => 'estudiante1@edugt.test',
                'name' => 'Kevin Alexander Morales Lima',
                'avatar_url' => 'assets/img/avatars/avatar-01.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaMarta->id,
                'streak' => 4,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 11, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'estudiante2@edugt.test',
                'name' => 'Yoselin Paola Cruz Salguero',
                'avatar_url' => 'assets/img/avatars/avatar-11.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaMarta->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [60, 80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'cristian.lemus.navas@gmail.com',
                'name' => 'Cristian Geovany Lemus Navas',
                'avatar_url' => 'assets/img/avatars/avatar-03.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaMarta->id,
                'streak' => 4,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 11, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'katherine.vasquez.m@gmail.com',
                'name' => 'Katherine Aracely Vásquez Monzón',
                'avatar_url' => 'assets/img/avatars/avatar-08.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaMarta->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'sindy.aguilar.gt@gmail.com',
                'name' => 'Sindy Maribel Aguilar Osorio',
                'avatar_url' => 'assets/img/avatars/avatar-12.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaMarta->id,
                'streak' => 4,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'alejandro.morales.gt@gmail.com',
                'name' => 'Alejandro Manuel Morales Sandoval',
                'avatar_url' => 'assets/img/avatars/avatar-02.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [60, 80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'jonathan.solares.gt@gmail.com',
                'name' => 'Jonathan Isaac Solares Pérez',
                'avatar_url' => 'assets/img/avatars/avatar-05.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'marvin.recinos.f@gmail.com',
                'name' => 'Marvin Eduardo Recinos Fajardo',
                'avatar_url' => 'assets/img/avatars/avatar-07.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'estudiante5@edugt.test',
                'name' => 'Anthony René Pineda Figueroa',
                'avatar_url' => 'assets/img/avatars/avatar-03.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'mateo.mendez.palma@gmail.com',
                'name' => 'Mateo Benjamín Méndez Palma',
                'avatar_url' => 'assets/img/avatars/avatar-01.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                'email' => 'wilmer.santos.gt@gmail.com',
                'name' => 'Wilmer Osmany Santos Galicia',
                'avatar_url' => 'assets/img/avatars/avatar-04.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                'email' => 'hector.barco.gt@gmail.com',
                'name' => 'Héctor Leonel Barco Barrera',
                'avatar_url' => 'assets/img/avatars/avatar-09.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 1,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                'email' => 'estudiante8@edugt.test',
                'name' => 'Bryan Alexis García Monterroso',
                'avatar_url' => 'assets/img/avatars/avatar-06.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 1,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                // Estudiante ausente en Post-test (caso real de investigación)
                'email' => 'darlyn.martinez.g@gmail.com',
                'name' => 'Darlyn Vanessa Martínez Gudiel',
                'avatar_url' => 'assets/img/avatars/avatar-10.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 1,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null, // Inasistencia al cierre
                'completed_modules' => [1],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                // Estudiante con dificultades y en riesgo académico
                'email' => 'brandon.ramos.gt@gmail.com',
                'name' => 'Brandon Josué Ramos Arana',
                'avatar_url' => 'assets/img/avatars/avatar-04.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 0,
                'pre_scores' => ['correct' => 4, 'dims' => ['abstraction' => 1, 'decomposition' => 0, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [20, 40, 40, 60]], // 4 intentos < 70 -> EN RIESGO
                ],
                'badges' => [],
                'xp' => 30,
            ],
            [
                // Segundo estudiante en riesgo en Sección A
                'email' => 'cesar.melgar.gt@gmail.com',
                'name' => 'César Augusto Melgar Corado',
                'avatar_url' => 'assets/img/avatars/avatar-02.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 0,
                'pre_scores' => ['correct' => 4, 'dims' => ['abstraction' => 1, 'decomposition' => 0, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [40, 40, 40, 60]], // 4 intentos < 70 -> EN RIESGO
                ],
                'badges' => [],
                'xp' => 30,
            ],

            // =========================================================================
            // AULA 2: 5to. Perito Contador con Orientación en Computación (Carlos) (15 alumnos)
            // =========================================================================
            [
                'email' => 'valentina.gomez.gt@gmail.com',
                'name' => 'Valentina Michelle Gómez Sandoval',
                'avatar_url' => 'assets/img/avatars/avatar-12.jpg',
                'learning_track' => 'advanced',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 7,
                'pre_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 13, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 2, 'loops' => 1]],
                'completed_modules' => [1, 2, 3],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [100]],
                    ['mod' => 2, 'attempts' => [80]],
                    ['mod' => 3, 'attempts' => [100]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control', 'maestro-del-bucle', 'maestro-pseudocodigo'],
                'xp' => 520,
            ],
            [
                'email' => 'glendy.arana.gt@gmail.com',
                'name' => 'Glendy Marisol Arana Barrera',
                'avatar_url' => 'assets/img/avatars/avatar-11.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 5,
                'pre_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 12, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 1]],
                'completed_modules' => [1, 2],
                'extra_lessons' => [3 => [0, 1]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control'],
                'xp' => 330,
            ],
            [
                'email' => 'estudiante4@edugt.test',
                'name' => 'Lesly Daniela Carrillo Godoy',
                'avatar_url' => 'assets/img/avatars/avatar-13.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 5,
                'pre_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 12, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 1]],
                'completed_modules' => [1, 2],
                'extra_lessons' => [3 => [0, 1]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control'],
                'xp' => 330,
            ],
            [
                'email' => 'kimberly.juarez.h@gmail.com',
                'name' => 'Kimberly Dayana Juárez Hernández',
                'avatar_url' => 'assets/img/avatars/avatar-13.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 4,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 11, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [60, 80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'estudiante3@edugt.test',
                'name' => 'Erick Fernando Castillo Zepeda',
                'avatar_url' => 'assets/img/avatars/avatar-02.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'mayra.chinchilla.gt@gmail.com',
                'name' => 'Mayra Alejandra Chinchilla Lucero',
                'avatar_url' => 'assets/img/avatars/avatar-08.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 4,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 11, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'yessica.gomez.gt@gmail.com',
                'name' => 'Yessica Noemí Gómez Pineda',
                'avatar_url' => 'assets/img/avatars/avatar-10.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'josue.palma.gt@gmail.com',
                'name' => 'Josué Daniel Palma Zepeda',
                'avatar_url' => 'assets/img/avatars/avatar-03.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'rony.quevedo.gt@gmail.com',
                'name' => 'Rony Estuardo Quevedo Salguero',
                'avatar_url' => 'assets/img/avatars/avatar-06.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [60, 80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'jennifer.moran.gt@gmail.com',
                'name' => 'Jennifer Paola Morán Godoy',
                'avatar_url' => 'assets/img/avatars/avatar-12.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'nelson.lima.gt@gmail.com',
                'name' => 'Nelson Enrique Lima Sandoval',
                'avatar_url' => 'assets/img/avatars/avatar-05.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                'email' => 'edras.soto.gt@gmail.com',
                'name' => 'Edras Geovany Soto Galicia',
                'avatar_url' => 'assets/img/avatars/avatar-01.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 1,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                // Estudiante en riesgo en Aula Carlos
                'email' => 'estefany.alvarado.gt@gmail.com',
                'name' => 'Estefany Abigail Alvarado Ruano',
                'avatar_url' => 'assets/img/avatars/avatar-08.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 0,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [40, 40, 40, 60]], // 4 intentos < 70 -> EN RIESGO
                ],
                'badges' => [],
                'xp' => 30,
            ],
            [
                // Segundo estudiante en riesgo en Aula Carlos
                'email' => 'bilsan.ortiz.gt@gmail.com',
                'name' => 'Bilsan Josué Ortiz Melgar',
                'avatar_url' => 'assets/img/avatars/avatar-02.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 0,
                'pre_scores' => ['correct' => 4, 'dims' => ['abstraction' => 1, 'decomposition' => 0, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [20, 40, 40, 60]], // 4 intentos < 70 -> EN RIESGO
                ],
                'badges' => [],
                'xp' => 30,
            ],
            [
                // Estudiante que inició tarde el taller
                'email' => 'camila.hernandez.gt@gmail.com',
                'name' => 'Camila Rocío Hernández Corado',
                'avatar_url' => 'assets/img/avatars/avatar-07.jpg',
                'learning_track' => null, // Pre-test pendiente
                'classroom_id' => $aulaCarlos->id,
                'streak' => 0,
                'pre_scores' => null,
                'post_scores' => null,
                'completed_modules' => [],
                'quizzes' => [],
                'badges' => [],
                'xp' => 0,
            ],

            // =========================================================================
            // AULA 3: 4to. Bachillerato en Computación - Sección B (Lucía) (14 alumnos)
            // =========================================================================
            [
                'email' => 'josue.rodas.gt@gmail.com',
                'name' => 'Josué David Rodas Barrientos',
                'avatar_url' => 'assets/img/avatars/avatar-05.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaLucia->id,
                'streak' => 7,
                'pre_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 1, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 13, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 2, 'loops' => 1]],
                'completed_modules' => [1, 2, 3],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [100]],
                    ['mod' => 2, 'attempts' => [80]],
                    ['mod' => 3, 'attempts' => [100]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control', 'maestro-del-bucle', 'maestro-pseudocodigo'],
                'xp' => 520,
            ],
            [
                'email' => 'monica.ruano.gt@gmail.com',
                'name' => 'Mónica Dayana Ruano Melgar',
                'avatar_url' => 'assets/img/avatars/avatar-11.jpg',
                'learning_track' => 'advanced',
                'classroom_id' => $aulaLucia->id,
                'streak' => 6,
                'pre_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 13, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 2, 'loops' => 1]],
                'completed_modules' => [1, 2, 3],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [100]],
                    ['mod' => 2, 'attempts' => [100]],
                    ['mod' => 3, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control', 'maestro-del-bucle', 'maestro-pseudocodigo'],
                'xp' => 520,
            ],
            [
                'email' => 'fatima.barrios.gt@gmail.com',
                'name' => 'Fátima Jimena Barrios Quintana',
                'avatar_url' => 'assets/img/avatars/avatar-03.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaLucia->id,
                'streak' => 5,
                'pre_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 12, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 1]],
                'completed_modules' => [1, 2],
                'extra_lessons' => [3 => [0, 1]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control'],
                'xp' => 330,
            ],
            [
                'email' => 'karla.salguero.gt@gmail.com',
                'name' => 'Karla Vanessa Salguero Godoy',
                'avatar_url' => 'assets/img/avatars/avatar-12.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaLucia->id,
                'streak' => 4,
                'pre_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 12, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 1]],
                'completed_modules' => [1, 2],
                'extra_lessons' => [3 => [0, 1]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control'],
                'xp' => 330,
            ],
            [
                'email' => 'estudiante7@edugt.test',
                'name' => 'Andrea Elizabeth Salazar Cifuentes',
                'avatar_url' => 'assets/img/avatars/avatar-13.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaLucia->id,
                'streak' => 4,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 11, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'gerson.zepeda.gt@gmail.com',
                'name' => 'Gerson David Zepeda Pineda',
                'avatar_url' => 'assets/img/avatars/avatar-03.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaLucia->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [60, 80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'brenda.galicia.gt@gmail.com',
                'name' => 'Brenda Leticia Galicia Arana',
                'avatar_url' => 'assets/img/avatars/avatar-10.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaLucia->id,
                'streak' => 4,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 11, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'astrid.melgar.gt@gmail.com',
                'name' => 'Astrid Carolina Melgar Barrera',
                'avatar_url' => 'assets/img/avatars/avatar-11.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaLucia->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'edgar.lima.gt@gmail.com',
                'name' => 'Edgar Rolando Lima Corado',
                'avatar_url' => 'assets/img/avatars/avatar-07.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaLucia->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'rudy.ortiz.gt@gmail.com',
                'name' => 'Rudy Alexander Ortiz Sandoval',
                'avatar_url' => 'assets/img/avatars/avatar-06.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaLucia->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'byron.palma.gt@gmail.com',
                'name' => 'Byron René Palma Santos',
                'avatar_url' => 'assets/img/avatars/avatar-01.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaLucia->id,
                'streak' => 1,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                // Estudiante ausente en Post-test en Sección B
                'email' => 'debora.corado.gt@gmail.com',
                'name' => 'Debora Noemí Corado Soto',
                'avatar_url' => 'assets/img/avatars/avatar-08.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaLucia->id,
                'streak' => 1,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null, // Ausente en Post-test
                'completed_modules' => [1],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                // Estudiante en riesgo en Sección B
                'email' => 'daniel.chanchavac.gt@gmail.com',
                'name' => 'Daniel Enmanuel Chanchavac López',
                'avatar_url' => 'assets/img/avatars/avatar-09.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaLucia->id,
                'streak' => 0,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [40, 60, 40, 60]], // 4 intentos < 70 -> EN RIESGO
                ],
                'badges' => [],
                'xp' => 30,
            ],
            [
                // Segundo estudiante en riesgo en Sección B
                'email' => 'hugo.chinchilla.gt@gmail.com',
                'name' => 'Hugo Leonel Chinchilla Pineda',
                'avatar_url' => 'assets/img/avatars/avatar-05.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaLucia->id,
                'streak' => 0,
                'pre_scores' => ['correct' => 4, 'dims' => ['abstraction' => 1, 'decomposition' => 0, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]],
                'quizzes' => [
                    ['mod' => 1, 'attempts' => [20, 40, 40, 60]], // 4 intentos < 70 -> EN RIESGO
                ],
                'badges' => [],
                'xp' => 30,
            ],
        ];

        // 4. Sembrar la cohorte con coherencia pedagógica
        foreach ($cohort as $cfg) {
            $user = User::query()->firstOrCreate(
                ['email' => $cfg['email']],
                [
                    'name' => $cfg['name'],
                    'password' => Hash::make('estudiante1234'),
                    'learning_track' => $cfg['learning_track'],
                    'avatar_url' => $cfg['avatar_url'],
                ]
            );
            $user->forceFill([
                'name' => $cfg['name'],
                'role' => 'student',
                'status' => 'active',
                'learning_track' => $cfg['learning_track'],
                'avatar_url' => $cfg['avatar_url'],
            ])->save();

            // Membresía en aula
            if (! empty($cfg['classroom_id'])) {
                ClassroomStudent::query()->firstOrCreate(
                    [
                        'classroom_id' => $cfg['classroom_id'],
                        'student_id' => $user->id,
                    ],
                    [
                        'joined_at' => now()->subDays(22)->setTime(8, rand(30, 45)),
                        'state' => 'active',
                    ]
                );
            }

            // A. Pre-test Diagnóstico (Presencial en laboratorio, Día 1 del taller)
            if ($preTest && ! empty($cfg['pre_scores'])) {
                $scoreTotal = (int) round(($cfg['pre_scores']['correct'] * 100) / 14);
                $dimScores = [];
                foreach ($dimensionsList as $d) {
                    $c = $cfg['pre_scores']['dims'][$d] ?? 0;
                    $dimScores[$d] = ['correct' => $c, 'total' => 2];
                }

                $preAttempt = DiagnosticAttempt::query()->updateOrCreate(
                    ['user_id' => $user->id, 'kind' => 'pre'],
                    [
                        'diagnostic_test_id' => $preTest->id,
                        'score_total' => $scoreTotal,
                        'dimension_scores' => $dimScores,
                        'status' => 'completed',
                        'started_at' => now()->subDays(21)->setTime(9, rand(5, 15)),
                        'submitted_at' => now()->subDays(21)->setTime(9, rand(22, 35)),
                    ]
                );

                $correctRemaining = $cfg['pre_scores']['correct'];
                foreach ($preQuestions as $qIdx => $q) {
                    $isCorrect = ($qIdx < $correctRemaining);
                    DiagnosticAnswer::query()->updateOrCreate(
                        [
                            'diagnostic_attempt_id' => $preAttempt->id,
                            'diagnostic_question_id' => $q->id,
                        ],
                        [
                            'selected_index' => $isCorrect ? $q->correct_index : (($q->correct_index + 1) % 3),
                            'is_correct' => $isCorrect,
                            'answered_at' => now()->subDays(21)->setTime(9, rand(16, 25)),
                        ]
                    );
                }
            }

            // B. Post-test Diagnóstico (Día de cierre del taller presencial)
            if ($postTest && ! empty($cfg['post_scores'])) {
                $scoreTotal = (int) round(($cfg['post_scores']['correct'] * 100) / 14);
                $dimScores = [];
                foreach ($dimensionsList as $d) {
                    $c = $cfg['post_scores']['dims'][$d] ?? 0;
                    $dimScores[$d] = ['correct' => $c, 'total' => 2];
                }

                $postAttempt = DiagnosticAttempt::query()->updateOrCreate(
                    ['user_id' => $user->id, 'kind' => 'post'],
                    [
                        'diagnostic_test_id' => $postTest->id,
                        'score_total' => $scoreTotal,
                        'dimension_scores' => $dimScores,
                        'status' => 'completed',
                        'started_at' => now()->subDays(2)->setTime(10, rand(5, 15)),
                        'submitted_at' => now()->subDays(2)->setTime(10, rand(25, 38)),
                    ]
                );

                $correctRemaining = $cfg['post_scores']['correct'];
                foreach ($postQuestions as $qIdx => $q) {
                    $isCorrect = ($qIdx < $correctRemaining);
                    DiagnosticAnswer::query()->updateOrCreate(
                        [
                            'diagnostic_attempt_id' => $postAttempt->id,
                            'diagnostic_question_id' => $q->id,
                        ],
                        [
                            'selected_index' => $isCorrect ? $q->correct_index : (($q->correct_index + 1) % 3),
                            'is_correct' => $isCorrect,
                            'answered_at' => now()->subDays(2)->setTime(10, rand(16, 25)),
                        ]
                    );
                }
            }

            // C. Lecciones completadas durante las semanas de intervención
            $completedLessonsIds = [];
            foreach ($cfg['completed_modules'] as $modIdx) {
                $targetMod = $modules->get($modIdx - 1);
                if ($targetMod) {
                    foreach ($targetMod->lessons as $lesson) {
                        $completedLessonsIds[] = $lesson->id;
                    }
                }
            }
            if (! empty($cfg['extra_lessons'])) {
                foreach ($cfg['extra_lessons'] as $mIdx => $lessonIndices) {
                    $targetMod = $modules->get($mIdx - 1);
                    if ($targetMod) {
                        $modLessons = $targetMod->lessons->values();
                        foreach ($lessonIndices as $li) {
                            if (isset($modLessons[$li])) {
                                $completedLessonsIds[] = $modLessons[$li]->id;
                            }
                        }
                    }
                }
            }

            foreach ($completedLessonsIds as $lId) {
                LessonProgress::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'lesson_id' => $lId,
                    ],
                    [
                        'completed' => true,
                        'time_spent_seconds' => rand(480, 940),
                        'started_at' => now()->subDays(rand(4, 18))->setTime(rand(9, 12), rand(10, 50)),
                        'last_visited_at' => now()->subDays(rand(2, 5))->setTime(rand(9, 12), rand(10, 50)),
                        'completed_at' => now()->subDays(rand(2, 5))->setTime(rand(9, 12), rand(10, 50)),
                    ]
                );
            }

            // D. Intentos de Evaluaciones de Módulo (Quizzes)
            if (! empty($cfg['quizzes'])) {
                foreach ($cfg['quizzes'] as $qp) {
                    $targetMod = $modules->get($qp['mod'] - 1);
                    if (! $targetMod || ! $targetMod->quiz) {
                        continue;
                    }
                    $quiz = $targetMod->quiz;
                    $quizQuestions = $quiz->questions()->with('options')->get();

                    foreach ($qp['attempts'] as $attIdx => $score) {
                        $attNum = $attIdx + 1;
                        $isPassed = $score >= 70;

                        $qAttempt = QuizAttempt::query()->updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'quiz_id' => $quiz->id,
                                'attempt_number' => $attNum,
                            ],
                            [
                                'started_at' => now()->subDays(max(1, 16 - ($qp['mod'] * 4)))->setTime(10, rand(10, 30)),
                                'submitted_at' => now()->subDays(max(1, 16 - ($qp['mod'] * 4)))->setTime(10, rand(35, 48)),
                                'score' => $score,
                                'status' => 'submitted',
                                'xp_awarded' => $isPassed && $attNum === 1,
                                'xp_amount' => ($isPassed && $attNum === 1) ? 100 : 0,
                            ]
                        );

                        $numCorrect = (int) round(($score / 100) * max(1, $quizQuestions->count()));
                        foreach ($quizQuestions as $idxQ => $question) {
                            $isCorrectQ = $idxQ < $numCorrect;
                            $chosenOpt = $isCorrectQ
                                ? $question->options->firstWhere('is_correct', true)
                                : $question->options->firstWhere('is_correct', false);

                            AttemptAnswer::query()->updateOrCreate(
                                [
                                    'quiz_attempt_id' => $qAttempt->id,
                                    'question_id' => $question->id,
                                ],
                                [
                                    'question_option_id' => $chosenOpt?->id,
                                    'is_correct' => $isCorrectQ,
                                ]
                            );
                        }
                    }
                }
            }

            // E. Insignias otorgadas
            if (! empty($cfg['badges'])) {
                foreach ($cfg['badges'] as $slug) {
                    $badge = Badge::query()->where('slug', $slug)->first();
                    if ($badge) {
                        UserBadge::query()->firstOrCreate(
                            ['user_id' => $user->id, 'badge_id' => $badge->id],
                            [
                                'awarded_at' => now()->subDays(rand(2, 14))->setTime(rand(9, 12), rand(10, 50)),
                                'notified_at' => now(),
                            ]
                        );
                    }
                }
            }

            // E.2. Prácticas del Laboratorio Algorítmico y Prueba de Escritorio
            if (Schema::hasTable('lab_challenges') && Schema::hasTable('lab_attempts')) {
                $labChallenges = LabChallenge::query()->orderBy('order_index')->get();
                if ($labChallenges->isNotEmpty()) {
                    $lessonsCompleted = count($completedLessonsIds);
                    $labsDoneCount = match (true) {
                        $lessonsCompleted >= 12 => 6,
                        $lessonsCompleted >= 9 => 5,
                        $lessonsCompleted >= 7 => 4,
                        $lessonsCompleted >= 5 => 3,
                        $lessonsCompleted >= 4 => 2,
                        $lessonsCompleted >= 2 => 1,
                        default => 0,
                    };

                    foreach ($labChallenges->take($labsDoneCount) as $idxLab => $labCh) {
                        $attCount = ($idxLab >= 2 && $lessonsCompleted < 10) ? 2 : 1;
                        $xpLab = $attCount === 1 ? 40 : 32;
                        LabAttempt::query()->updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'lab_challenge_id' => $labCh->id,
                            ],
                            [
                                'completed' => true,
                                'attempts_count' => $attCount,
                                'best_score' => 100,
                                'xp_awarded' => $xpLab,
                                'completed_at' => now()->subDays(max(1, 14 - ($idxLab * 2)))->setTime(rand(9, 11), rand(15, 50)),
                            ]
                        );
                    }

                    // Si el estudiante está en el grupo de rezago (lessons_done == 3), agregar un intento parcial en el reto 2
                    if ($lessonsCompleted === 3 && $labChallenges->count() >= 2) {
                        $secondLab = $labChallenges->get(1);
                        LabAttempt::query()->updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'lab_challenge_id' => $secondLab->id,
                            ],
                            [
                                'completed' => false,
                                'attempts_count' => 3,
                                'best_score' => 50,
                                'xp_awarded' => 0,
                                'completed_at' => null,
                            ]
                        );
                    }

                    // Otorgar insignia "Arquitecto de Algoritmos" si completó >= 3 prácticas
                    if ($labsDoneCount >= 3) {
                        $archBadge = Badge::query()->where('slug', 'arquitecto-algoritmos')->first();
                        if ($archBadge) {
                            UserBadge::query()->firstOrCreate(
                                ['user_id' => $user->id, 'badge_id' => $archBadge->id],
                                [
                                    'awarded_at' => now()->subDays(rand(1, 7))->setTime(rand(9, 12), rand(10, 50)),
                                    'notified_at' => now(),
                                ]
                            );
                        }
                    }
                }
            }

            // F. Perfil Gamificado y Libro Contable de XP
            $xpTotal = $cfg['xp'] ?? 0;
            $xpLevel = max(1, intdiv($xpTotal, 100) + 1);

            $profile = UserXpProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['xp_total' => 0, 'xp_level' => 1]
            );

            $profile->forceFill([
                'xp_total' => $xpTotal,
                'xp_level' => $xpLevel,
                'streak_current' => $cfg['streak'] ?? 0,
                'streak_longest' => max($cfg['streak'] ?? 0, rand(1, 7)),
                'streak_last_active_date' => now()->toDateString(),
                'xp_updated_at' => now(),
            ])->save();

            if (! XpLedgerEntry::query()->where('user_id', $user->id)->exists() && $xpTotal > 0) {
                $remainingXp = $xpTotal;
                if ($remainingXp >= 100) {
                    XpLedgerEntry::query()->create([
                        'user_id' => $user->id,
                        'delta' => 100,
                        'source' => 'quiz',
                        'reason' => 'Evaluación de Módulo 1 aprobada',
                        'created_at' => now()->subDays(14)->setTime(10, 45),
                    ]);
                    $remainingXp -= 100;
                }
                if ($remainingXp >= 100) {
                    XpLedgerEntry::query()->create([
                        'user_id' => $user->id,
                        'delta' => 100,
                        'source' => 'quiz',
                        'reason' => 'Evaluación de Módulo 2 aprobada',
                        'created_at' => now()->subDays(9)->setTime(10, 50),
                    ]);
                    $remainingXp -= 100;
                }
                if ($remainingXp >= 100) {
                    XpLedgerEntry::query()->create([
                        'user_id' => $user->id,
                        'delta' => 100,
                        'source' => 'quiz',
                        'reason' => 'Evaluación de Módulo 3 aprobada',
                        'created_at' => now()->subDays(4)->setTime(11, 15),
                    ]);
                    $remainingXp -= 100;
                }
                if ($remainingXp > 0) {
                    XpLedgerEntry::query()->create([
                        'user_id' => $user->id,
                        'delta' => $remainingXp,
                        'source' => 'lesson',
                        'reason' => 'Lecciones de lógica completadas',
                        'created_at' => now()->subDays(2)->setTime(11, 30),
                    ]);
                }
            }
        }

        // 5. Tickets de Soporte del Taller Presencial
        $alumno1 = User::query()->where('email', 'diego.reyes.pineda@gmail.com')->first();
        if ($alumno1) {
            SupportTicket::query()->firstOrCreate(
                ['reporter_id' => $alumno1->id, 'description' => 'Sugerencia didáctica: En el laboratorio de computación nos fue muy útil el pseudocódigo paso a paso. Sería genial poder tener un botón para reiniciar las variables del ejercicio interactivo.'],
                [
                    'kind' => 'suggestion',
                    'module' => 'curriculum',
                    'status' => 'open',
                ]
            );
        }

        $alumno2 = User::query()->where('email', 'kimberly.juarez.h@gmail.com')->first();
        if ($alumno2) {
            SupportTicket::query()->firstOrCreate(
                ['reporter_id' => $alumno2->id, 'description' => 'Incidencia de visualización: En la computadora número 8 del laboratorio la resolución del monitor recortaba un poco la tabla del podio, pero al cambiar al modo pantalla completa se solucionó.'],
                [
                    'kind' => 'error',
                    'module' => 'general',
                    'status' => 'in_review',
                ]
            );
        }

        $docenteMarta = User::query()->where('email', 'marta@edugt.test')->first();
        if ($docenteMarta) {
            SupportTicket::query()->firstOrCreate(
                ['reporter_id' => $docenteMarta->id, 'description' => 'Constancia de satisfacción: El reporte analítico con el Factor de Ganancia de Hake (g = 0.58) y la comparativa de las 7 dimensiones refleja con total exactitud el progreso observado en los estudiantes de 4to. Bachillerato durante el taller.'],
                [
                    'kind' => 'suggestion',
                    'module' => 'reports',
                    'status' => 'resolved',
                ]
            );
        }
    }
}
