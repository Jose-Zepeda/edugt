<?php

namespace Database\Seeders;

use App\Domain\Assessments\Models\AttemptAnswer;
use App\Domain\Assessments\Models\Question;
use App\Domain\Assessments\Models\QuestionOption;
use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\QuizAttempt;
use App\Domain\Audit\Models\SupportTicket;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Classrooms\Models\ClassroomCode;
use App\Domain\Classrooms\Models\ClassroomStudent;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Curriculum\Models\Mission;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Diagnostic\Models\DiagnosticAnswer;
use App\Domain\Diagnostic\Models\DiagnosticAttempt;
use App\Domain\Diagnostic\Models\DiagnosticQuestion;
use App\Domain\Diagnostic\Models\DiagnosticTest;
use App\Domain\Gamification\Models\Badge;
use App\Domain\Gamification\Models\UserBadge;
use App\Domain\Gamification\Models\UserXpProfile;
use App\Domain\Gamification\Models\XpLedgerEntry;
use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de simulación realista de estudiantes (Cohorte EduGT).
 * Genera métricas ricas y variadas para corroborar:
 *  - Analítica docente y reportes PDF (Ganancia de Hake, 7 dimensiones, alumnos en riesgo, preguntas difíciles).
 *  - Gamificación de estudiantes (Ranking global, rankings de aula, podios, insignias, niveles XP, diplomas).
 *  - Bitácoras y tickets de soporte administrativo.
 * Idempotente: se puede ejecutar repetidamente sin duplicar datos ni corromper llaves foráneas.
 */
class SimulatedStudentsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Asegurar aulas y docentes
        $marta = User::query()->where('email', 'marta@edugt.test')->first();
        $carlos = User::query()->where('email', 'carlos@edugt.test')->first();
        $lucia = User::query()->where('email', 'lucia@edugt.test')->first();

        if (! $marta || ! $carlos || ! $lucia) {
            return;
        }

        $aulaMarta = Classroom::query()->firstOrCreate(
            ['teacher_id' => $marta->id, 'name' => 'Cuarto Bachillerato A'],
            [
                'institution' => 'Instituto de Jutiapa',
                'grade_level' => 'Cuarto Bachillerato en Ciencias y Letras',
                'academic_year' => '2026',
                'is_active' => true,
            ]
        );
        ClassroomCode::query()->firstOrCreate(
            ['code' => 'AULA2026A'],
            ['classroom_id' => $aulaMarta->id, 'is_active' => true]
        );

        $aulaCarlos = Classroom::query()->firstOrCreate(
            ['teacher_id' => $carlos->id, 'name' => 'Perito Contador B'],
            [
                'institution' => 'Instituto de Jutiapa',
                'grade_level' => 'Cuarto Perito Contador',
                'academic_year' => '2026',
                'is_active' => true,
            ]
        );
        ClassroomCode::query()->firstOrCreate(
            ['code' => 'AULA2026B'],
            ['classroom_id' => $aulaCarlos->id, 'is_active' => true]
        );

        $aulaLucia = Classroom::query()->firstOrCreate(
            ['teacher_id' => $lucia->id, 'name' => 'Quinto Bachillerato en Computación'],
            [
                'institution' => 'Instituto Nacional de Educación Diversificada',
                'grade_level' => 'Quinto Bachillerato en Computación',
                'academic_year' => '2026',
                'is_active' => true,
            ]
        );
        ClassroomCode::query()->firstOrCreate(
            ['code' => 'AULA2026C'],
            ['classroom_id' => $aulaLucia->id, 'is_active' => true]
        );

        // 2. Obtener currículo y pruebas diagnósticas
        $modules = Module::query()->where('status', 'published')->with(['lessons', 'quiz.questions.options'])->orderBy('order_index')->get();
        $mod1 = $modules->get(0);
        $mod2 = $modules->get(1);
        $mod3 = $modules->get(2);

        $preTest = DiagnosticTest::query()->where('kind', 'pre')->first();
        $postTest = DiagnosticTest::query()->where('kind', 'post')->first();
        $preQuestions = $preTest ? DiagnosticQuestion::query()->where('diagnostic_test_id', $preTest->id)->orderBy('order_index')->get() : collect();
        $postQuestions = $postTest ? DiagnosticQuestion::query()->where('diagnostic_test_id', $postTest->id)->orderBy('order_index')->get() : collect();

        // 3. Definición de perfiles de estudiantes simulados
        $studentsConfig = [
            // --- AULA MARTA (Cuarto Bachillerato A) ---
            [
                'email' => 'sofia.castillo@edugt.test',
                'name' => 'Sofía Castillo',
                'avatar_url' => 'assets/img/avatars/avatar-11.jpg',
                'learning_track' => 'advanced',
                'classroom_id' => $aulaMarta->id,
                'streak' => 7,
                'pre_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 1, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 13, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 2, 'loops' => 1]],
                'completed_modules' => [1, 2, 3],
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [100]],
                    ['mod' => 2, 'attempts' => [100]],
                    ['mod' => 3, 'attempts' => [100]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control', 'maestro-del-bucle', 'maestro-pseudocodigo'],
                'xp' => 520,
            ],
            [
                'email' => 'diego.reyes@edugt.test',
                'name' => 'Diego Reyes',
                'avatar_url' => 'assets/img/avatars/avatar-06.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaMarta->id,
                'streak' => 5,
                'pre_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 2, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 12, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 1]],
                'completed_modules' => [1, 2],
                'extra_lessons' => [3 => [0, 1]], // primeras 2 lecciones del módulo 3
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control'],
                'xp' => 330,
            ],
            [
                'email' => 'alejandro.morales@edugt.test',
                'name' => 'Alejandro Morales',
                'avatar_url' => 'assets/img/avatars/avatar-02.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'extra_lessons' => [2 => [0]], // 1 lección del módulo 2
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [60, 80]], // reprobó primero, aprobó en 2do intento
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 150,
            ],
            [
                'email' => 'brandon.ramos@edugt.test',
                'name' => 'Brandon Ramos',
                'avatar_url' => 'assets/img/avatars/avatar-04.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 0,
                'pre_scores' => ['correct' => 4, 'dims' => ['abstraction' => 1, 'decomposition' => 0, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null, // no ha realizado post-test
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]], // leyó lecciones teóricas
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [20, 40, 40, 60]], // 4 intentos < 70 -> EN RIESGO (§3.7)
                ],
                'badges' => [],
                'xp' => 30,
            ],
            [
                'email' => 'mateo.mendez@edugt.test',
                'name' => 'Mateo Méndez',
                'avatar_url' => 'assets/img/avatars/avatar-01.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaMarta->id,
                'streak' => 2,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1]],
                'quizzes_passed' => [],
                'badges' => [],
                'xp' => 20,
            ],

            // --- AULA CARLOS (Perito Contador B) ---
            [
                'email' => 'valentina.gomez@edugt.test',
                'name' => 'Valentina Gómez',
                'avatar_url' => 'assets/img/avatars/avatar-12.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 4,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 11, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [60, 80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'kimberly.juarez@edugt.test',
                'name' => 'Kimberly Juárez',
                'avatar_url' => 'assets/img/avatars/avatar-13.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 1,
                'pre_scores' => ['correct' => 6, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 8, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1],
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables'],
                'xp' => 140,
            ],
            [
                'email' => 'estefany.alvarado@edugt.test',
                'name' => 'Estefany Alvarado',
                'avatar_url' => 'assets/img/avatars/avatar-08.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaCarlos->id,
                'streak' => 0,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]],
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [40, 40, 40, 60]], // 4 intentos < 70 -> EN RIESGO (§3.7)
                ],
                'badges' => [],
                'xp' => 30,
            ],
            [
                'email' => 'camila.hernandez@edugt.test',
                'name' => 'Camila Hernández',
                'avatar_url' => 'assets/img/avatars/avatar-07.jpg',
                'learning_track' => null, // Pre-test pendiente
                'classroom_id' => $aulaCarlos->id,
                'streak' => 0,
                'pre_scores' => null,
                'post_scores' => null,
                'completed_modules' => [],
                'quizzes_passed' => [],
                'badges' => [],
                'xp' => 0,
            ],

            // --- AULA LUCÍA (Quinto Bachillerato en Computación) ---
            [
                'email' => 'josue.rodas@edugt.test',
                'name' => 'Josué Rodas',
                'avatar_url' => 'assets/img/avatars/avatar-05.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaLucia->id,
                'streak' => 6,
                'pre_scores' => ['correct' => 9, 'dims' => ['abstraction' => 1, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 1, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 12, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 2, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 1]],
                'completed_modules' => [1, 2, 3],
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [100]],
                    ['mod' => 2, 'attempts' => [80]],
                    ['mod' => 3, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'depurador-elite', 'explorador-variables', 'mago-del-control', 'maestro-del-bucle', 'maestro-pseudocodigo'],
                'xp' => 450,
            ],
            [
                'email' => 'fatima.barrios@edugt.test',
                'name' => 'Fátima Barrios',
                'avatar_url' => 'assets/img/avatars/avatar-03.jpg',
                'learning_track' => 'intermediate',
                'classroom_id' => $aulaLucia->id,
                'streak' => 3,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => ['correct' => 10, 'dims' => ['abstraction' => 2, 'decomposition' => 2, 'pattern_recognition' => 1, 'algorithms' => 2, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'completed_modules' => [1, 2],
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [80]],
                    ['mod' => 2, 'attempts' => [80]],
                ],
                'badges' => ['novato-logica', 'explorador-variables', 'mago-del-control'],
                'xp' => 280,
            ],
            [
                'email' => 'daniel.chanchavac@edugt.test',
                'name' => 'Daniel Chanchavac',
                'avatar_url' => 'assets/img/avatars/avatar-09.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaLucia->id,
                'streak' => 0,
                'pre_scores' => ['correct' => 5, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 1, 'conditionals' => 0, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]],
                'quizzes_passed' => [
                    ['mod' => 1, 'attempts' => [40, 60, 40, 60]], // 4 intentos < 70 -> EN RIESGO
                ],
                'badges' => [],
                'xp' => 30,
            ],
            [
                'email' => 'andrea.salazar@edugt.test',
                'name' => 'Andrea Salazar',
                'avatar_url' => 'assets/img/avatars/avatar-10.jpg',
                'learning_track' => 'beginner',
                'classroom_id' => $aulaLucia->id,
                'streak' => 1,
                'pre_scores' => ['correct' => 7, 'dims' => ['abstraction' => 1, 'decomposition' => 1, 'pattern_recognition' => 1, 'algorithms' => 1, 'variables' => 2, 'conditionals' => 1, 'loops' => 0]],
                'post_scores' => null,
                'completed_modules' => [],
                'extra_lessons' => [1 => [0, 1, 2]],
                'quizzes_passed' => [],
                'badges' => [],
                'xp' => 30,
            ],
        ];

        // 4. Sembrar cada estudiante con sus métricas completas
        $dimensionsList = ['abstraction', 'decomposition', 'pattern_recognition', 'algorithms', 'variables', 'conditionals', 'loops'];

        foreach ($studentsConfig as $cfg) {
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
                        'joined_at' => now()->subDays(20),
                        'state' => 'active',
                    ]
                );
            }

            // A. Pre-test Diagnóstico
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
                        'started_at' => now()->subDays(18)->subMinutes(12),
                        'submitted_at' => now()->subDays(18),
                    ]
                );

                // Respuestas detalladas del Pre-test
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
                            'answered_at' => now()->subDays(18)->subMinutes(rand(1, 10)),
                        ]
                    );
                }
            }

            // B. Post-test Diagnóstico
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
                        'started_at' => now()->subDays(2)->subMinutes(14),
                        'submitted_at' => now()->subDays(2),
                    ]
                );

                // Respuestas detalladas del Post-test
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
                            'answered_at' => now()->subDays(2)->subMinutes(rand(1, 12)),
                        ]
                    );
                }
            }

            // C. Lecciones completadas
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
                        'time_spent_seconds' => rand(420, 850),
                        'started_at' => now()->subDays(rand(3, 14)),
                        'last_visited_at' => now()->subDays(rand(1, 3)),
                        'completed_at' => now()->subDays(rand(1, 3)),
                    ]
                );
            }

            // D. Intentos de Evaluaciones de Módulo (Quizzes)
            if (! empty($cfg['quizzes_passed'])) {
                foreach ($cfg['quizzes_passed'] as $qp) {
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
                                'started_at' => now()->subDays(max(1, 12 - ($qp['mod'] * 3)))->subMinutes(10),
                                'submitted_at' => now()->subDays(max(1, 12 - ($qp['mod'] * 3))),
                                'score' => $score,
                                'status' => 'submitted',
                                'xp_awarded' => $isPassed && $attNum === 1,
                                'xp_amount' => ($isPassed && $attNum === 1) ? 100 : 0,
                            ]
                        );

                        // Crear respuestas detalladas para cada pregunta del quiz
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
                                'awarded_at' => now()->subDays(rand(1, 10)),
                                'notified_at' => now(),
                            ]
                        );
                    }
                }
            }

            // F. Perfil y Libro Contable de XP
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
                // Registrar asientos contables de ejemplo que sumen el total
                $remainingXp = $xpTotal;
                if ($remainingXp >= 100) {
                    XpLedgerEntry::query()->create([
                        'user_id' => $user->id,
                        'delta' => 100,
                        'source' => 'quiz',
                        'reason' => 'Evaluación de Módulo 1 aprobada',
                        'created_at' => now()->subDays(12),
                    ]);
                    $remainingXp -= 100;
                }
                if ($remainingXp >= 100) {
                    XpLedgerEntry::query()->create([
                        'user_id' => $user->id,
                        'delta' => 100,
                        'source' => 'quiz',
                        'reason' => 'Evaluación de Módulo 2 aprobada',
                        'created_at' => now()->subDays(8),
                    ]);
                    $remainingXp -= 100;
                }
                if ($remainingXp >= 100) {
                    XpLedgerEntry::query()->create([
                        'user_id' => $user->id,
                        'delta' => 100,
                        'source' => 'quiz',
                        'reason' => 'Evaluación de Módulo 3 aprobada',
                        'created_at' => now()->subDays(4),
                    ]);
                    $remainingXp -= 100;
                }
                if ($remainingXp > 0) {
                    XpLedgerEntry::query()->create([
                        'user_id' => $user->id,
                        'delta' => $remainingXp,
                        'source' => 'lesson',
                        'reason' => 'Lecciones de lógica completadas',
                        'created_at' => now()->subDays(2),
                    ]);
                }
            }
        }

        // 5. Sembrar Tickets de Soporte representativos para el panel de Admin
        $reporter1 = User::query()->where('email', 'alejandro.morales@edugt.test')->first();
        if ($reporter1) {
            SupportTicket::query()->firstOrCreate(
                ['reporter_id' => $reporter1->id, 'description' => 'Sugerencia didáctica: Sería de gran ayuda agregar una tabla de verdad interactiva en el módulo de condicionales para visualizar mejor el operador AND y el operador OR.'],
                [
                    'kind' => 'suggestion',
                    'module' => 'curriculum',
                    'status' => 'open',
                ]
            );
        }

        $reporter2 = User::query()->where('email', 'kimberly.juarez@edugt.test')->first();
        if ($reporter2) {
            SupportTicket::query()->firstOrCreate(
                ['reporter_id' => $reporter2->id, 'description' => 'Incidencia de red: Al realizar la evaluación del módulo 1 desde un teléfono móvil, el temporizador tuvo un leve retraso debido a intermitencia en la señal.'],
                [
                    'kind' => 'error',
                    'module' => 'quizzes',
                    'status' => 'in_review',
                ]
            );
        }

        $reporter3 = User::query()->where('email', 'marta@edugt.test')->first();
        if ($reporter3) {
            SupportTicket::query()->firstOrCreate(
                ['reporter_id' => $reporter3->id, 'description' => 'Felicitaciones por el diseño del reporte en PDF con la Ganancia Normalizada de Hake y el desglose de las 7 dimensiones. Funciona excelente para la memoria de labores del instituto.'],
                [
                    'kind' => 'suggestion',
                    'module' => 'reports',
                    'status' => 'resolved',
                ]
            );
        }
    }
}
