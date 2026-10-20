<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analytics\Contracts\AnalyticsQuery;
use App\Domain\Reporting\Contracts\ReportExporter;
use App\Domain\Classrooms\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RF-20, RF-21, RF-22: analítica docente y reportes PDF.
 * RB-09: docente solo ve SUS aulas; admin todo.
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly AnalyticsQuery $analytics,
        private readonly ReportExporter $exporter,
    ) {}

    /** GET /api/v1/classrooms/{id}/analytics */
    public function classroomAnalytics(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $payload = $this->buildAnalyticsPayload($id, $user);

        return response()->json([
            'data' => $payload,
        ]);
    }

    /** GET /api/v1/analytics/overview?classroom_id= */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $classroomId = $request->filled('classroom_id') ? (int) $request->query('classroom_id') : null;
        $payload = $this->buildAnalyticsPayload($classroomId, $user);

        return response()->json([
            'data' => $payload,
        ]);
    }

    /** POST /api/v1/reports — genera PDF sincrónico (n pequeño: 50 aulas) */
    public function generate(Request $request): \Illuminate\Http\Response
    {
        $user = $request->user();

        $data = $request->validate([
            'kind' => ['required', 'in:classroom_performance'],
            'classroom_id' => ['nullable', 'integer'],
        ]);

        $classroomId = isset($data['classroom_id']) && $data['classroom_id'] !== ''
            ? (int) $data['classroom_id']
            : null;

        $payload = $this->buildAnalyticsPayload($classroomId, $user);

        $pdf = ($this->exporter)->pdf($data['kind'], $payload);

        // RB-12: SOLO PDF — sin Excel ni ningún otro formato
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="edugt-reporte-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }

    private function buildAnalyticsPayload(?int $classroomId, \App\Domain\Users\Models\User $user): array
    {
        $classroom = null;
        if ($classroomId !== null) {
            $classroom = Classroom::query()->with('teacher:id,name,email')->findOrFail($classroomId);
            if (! $user->isAdmin()) {
                abort_unless($classroom->teacher_id === $user->id, 403, 'FORBIDDEN');
            }
        }

        // Determinar las aulas y estudiantes incluidos en el reporte
        if ($classroom !== null) {
            $classroomIds = [$classroom->id];
        } elseif ($user->isAdmin()) {
            $classroomIds = Classroom::query()->pluck('id')->all();
        } else {
            $classroomIds = Classroom::query()->where('teacher_id', $user->id)->pluck('id')->all();
        }

        $memberships = \App\Domain\Classrooms\Models\ClassroomStudent::query()
            ->whereIn('classroom_id', $classroomIds)
            ->where('state', 'active')
            ->with(['student', 'classroom:id,name'])
            ->get()
            ->unique('student_id')
            ->values();

        $studentIds = $memberships->pluck('student_id')->all();

        $modules = \App\Domain\Curriculum\Models\Module::query()
            ->where('status', 'published')
            ->with([
                'lessons' => fn ($q) => $q->where('status', 'published')->orderBy('order_index'),
                'quiz',
            ])
            ->orderBy('order_index')
            ->get();

        $completedProgress = ! empty($studentIds)
            ? \App\Domain\Curriculum\Models\LessonProgress::query()
                ->whereIn('user_id', $studentIds)
                ->where('completed', true)
                ->get()
                ->groupBy('user_id')
                ->map(fn ($items) => $items->pluck('lesson_id')->flip())
            : collect();

        $quizAttempts = ! empty($studentIds)
            ? \App\Domain\Assessments\Models\QuizAttempt::query()
                ->whereIn('user_id', $studentIds)
                ->where('status', 'submitted')
                ->get()
            : collect();

        $quizAttemptsByUser = $quizAttempts->groupBy('user_id');

        $diagnostics = ! empty($studentIds)
            ? \App\Domain\Diagnostic\Models\DiagnosticAttempt::query()
                ->whereIn('user_id', $studentIds)
                ->where('status', 'completed')
                ->get()
            : collect();

        $diagnosticsByUser = $diagnostics->groupBy('user_id');

        $xpProfiles = ! empty($studentIds)
            ? \App\Domain\Gamification\Models\UserXpProfile::query()
                ->whereIn('user_id', $studentIds)
                ->get()
                ->keyBy('user_id')
            : collect();

        $badgeCounts = ! empty($studentIds)
            ? \App\Domain\Gamification\Models\UserBadge::query()
                ->whereIn('user_id', $studentIds)
                ->selectRaw('user_id, COUNT(*) as total')
                ->groupBy('user_id')
                ->pluck('total', 'user_id')
            : collect();

        // 1. Resumen por Módulo
        $modulesSummary = $modules->map(function ($mod, $idx) use ($studentIds, $completedProgress, $quizAttempts) {
            $cleanTitle = preg_replace('/^Módulo\s*\d+\s*[:.-]?\s*/iu', '', $mod->title);
            $standardLessons = $mod->lessons->filter(
                fn ($l) => ! $l->requires_quiz_pass && ! str_contains(mb_strtolower($l->title), 'evaluación')
            );

            $studentsCompletedLessons = 0;
            foreach ($studentIds as $sid) {
                $userLess = $completedProgress->get($sid, collect());
                if ($standardLessons->isNotEmpty() && $standardLessons->every(fn ($l) => $userLess->has($l->id))) {
                    $studentsCompletedLessons++;
                }
            }

            $modQuizAttempts = $mod->quiz
                ? $quizAttempts->where('quiz_id', $mod->quiz->id)
                : collect();

            $totalAtt = $modQuizAttempts->count();
            $passedAtt = $modQuizAttempts->where('score', '>=', $mod->quiz->passing_score ?? 70)->count();
            $studentsPassedQuiz = $modQuizAttempts
                ->where('score', '>=', $mod->quiz->passing_score ?? 70)
                ->pluck('user_id')
                ->unique()
                ->count();
            $avgQuizScore = $totalAtt > 0 ? round((float) $modQuizAttempts->avg('score'), 1) : null;

            return [
                'number' => $idx + 1,
                'title' => $cleanTitle,
                'lessons_count' => $standardLessons->count(),
                'students_completed_lessons' => $studentsCompletedLessons,
                'quiz_attempts' => $totalAtt,
                'students_passed_quiz' => $studentsPassedQuiz,
                'avg_quiz_score' => $avgQuizScore,
                'pass_rate' => $totalAtt > 0 ? round(($passedAtt * 100) / $totalAtt, 1) : 0.0,
            ];
        })->all();

        // 2. Nómina detallada de estudiantes
        $trackLabels = [
            'beginner' => 'Básico',
            'intermediate' => 'Intermedio',
            'advanced' => 'Avanzado',
        ];

        $studentsRoster = $memberships->map(function ($m) use (
            $modules,
            $completedProgress,
            $quizAttemptsByUser,
            $diagnosticsByUser,
            $xpProfiles,
            $badgeCounts,
            $trackLabels
        ) {
            $sid = $m->student_id;
            $userLessons = $completedProgress->get($sid, collect());
            $userQuizzes = $quizAttemptsByUser->get($sid, collect());
            $userDiags = $diagnosticsByUser->get($sid, collect());

            $preAttempt = $userDiags->firstWhere('kind', 'pre');
            $postAttempt = $userDiags->firstWhere('kind', 'post');
            $preScore = $preAttempt !== null ? (int) $preAttempt->score_total : null;
            $postScore = $postAttempt !== null ? (int) $postAttempt->score_total : null;
            $preCompleted = $m->student->learning_track !== null || $preAttempt !== null;

            $totalSteps = 0;
            $totalDone = 0;
            $activeStageLabel = null;
            $moduleScores = [];

            foreach ($modules as $idx => $mod) {
                $modNum = $idx + 1;
                $cleanTitle = preg_replace('/^Módulo\s*\d+\s*[:.-]?\s*/iu', '', $mod->title);
                $standardLessons = $mod->lessons->filter(
                    fn ($l) => ! $l->requires_quiz_pass && ! str_contains(mb_strtolower($l->title), 'evaluación')
                );
                $quizLesson = $mod->lessons->first(
                    fn ($l) => $l->requires_quiz_pass || str_contains(mb_strtolower($l->title), 'evaluación')
                );

                $bestQuizScore = $mod->quiz
                    ? $userQuizzes->where('quiz_id', $mod->quiz->id)->max('score')
                    : null;
                $quizPassed = $bestQuizScore !== null && $bestQuizScore >= ($mod->quiz->passing_score ?? 70);

                $moduleScores[] = [
                    'module_number' => $modNum,
                    'score' => $bestQuizScore !== null ? (int) $bestQuizScore : null,
                    'passed' => $quizPassed,
                ];

                $modTotal = $standardLessons->count() + (($mod->quiz || $quizLesson) ? 1 : 0);
                $modDone = 0;

                foreach ($standardLessons as $lesson) {
                    if ($userLessons->has($lesson->id)) {
                        $modDone++;
                    } elseif ($activeStageLabel === null) {
                        $activeStageLabel = "Mód. {$modNum}: {$lesson->title}";
                    }
                }

                if ($mod->quiz || $quizLesson) {
                    if ($quizPassed || ($quizLesson && $userLessons->has($quizLesson->id))) {
                        $modDone++;
                    } elseif ($activeStageLabel === null) {
                        $activeStageLabel = "Mód. {$modNum}: Evaluación final";
                    }
                }

                $totalSteps += $modTotal;
                $totalDone += $modDone;
            }

            $progressPct = $totalSteps > 0 ? (int) round(($totalDone * 100) / $totalSteps) : 0;
            if ($progressPct >= 100) {
                $stageText = 'Completado (100%)';
            } elseif (! $preCompleted) {
                $stageText = 'Pre-test pendiente';
            } else {
                $stageText = $activeStageLabel ?? 'En progreso';
            }

            $xpProfile = $xpProfiles->get($sid);

            return [
                'student_id' => $sid,
                'name' => $m->student->name,
                'email' => $m->student->email,
                'classroom_name' => $m->classroom?->name ?? '—',
                'track_label' => $trackLabels[$m->student->learning_track] ?? ($preCompleted ? 'Diagnosticado' : 'Sin Pre-test'),
                'pretest_score' => $preScore,
                'posttest_score' => $postScore,
                'gain' => ($preScore !== null && $postScore !== null) ? ($postScore - $preScore) : null,
                'completed_lessons' => $totalDone,
                'total_lessons' => $totalSteps,
                'progress_percent' => $progressPct,
                'current_stage_label' => $stageText,
                'module_scores' => $moduleScores,
                'xp_total' => (int) ($xpProfile?->xp_total ?? 0),
                'xp_level' => (int) ($xpProfile?->xp_level ?? 1),
                'badges_count' => (int) ($badgeCounts->get($sid) ?? 0),
            ];
        })->all();

        // 3. Comparativa Pre-test vs Post-test + Ganancia de Hake + 7 Dimensiones
        $prePost = ($this->analytics)->prePostComparison($classroomId);
        $preAvgForHake = $prePost['pre_avg'] ?? ($diagnostics->where('kind', 'pre')->isNotEmpty() ? round((float) $diagnostics->where('kind', 'pre')->avg('score_total'), 1) : null);
        $postAvgForHake = $prePost['post_avg'] ?? ($diagnostics->where('kind', 'post')->isNotEmpty() ? round((float) $diagnostics->where('kind', 'post')->avg('score_total'), 1) : null);

        $hakeG = null;
        $hakeLevel = 'Datos insuficientes (requiere Pre-test y Post-test)';
        if ($preAvgForHake !== null && $postAvgForHake !== null && $preAvgForHake < 100) {
            $hakeG = round(($postAvgForHake - $preAvgForHake) / (100 - $preAvgForHake), 2);
            if ($hakeG >= 0.70) {
                $hakeLevel = 'Ganancia Alta (g ≥ 0.70)';
            } elseif ($hakeG >= 0.30) {
                $hakeLevel = 'Ganancia Media (0.30 ≤ g < 0.70)';
            } else {
                $hakeLevel = 'Ganancia Baja (g < 0.30)';
            }
        }
        $prePost['overall_pre_avg'] = $preAvgForHake;
        $prePost['overall_post_avg'] = $postAvgForHake;
        $prePost['hake_g'] = $hakeG;
        $prePost['hake_level'] = $hakeLevel;

        $dimensionNames = [
            'abstraction' => 'Abstracción',
            'decomposition' => 'Descomposición',
            'pattern_recognition' => 'Reconocimiento de Patrones',
            'algorithms' => ' Pensamiento Algorítmico',
            'variables' => 'Variables y Tipos de Datos',
            'conditionals' => 'Estructuras Condicionales',
            'loops' => 'Estructuras Cíclicas (Bucles)',
        ];

        $dimensionsBreakdown = [];
        foreach ($dimensionNames as $dimKey => $dimLabel) {
            $preRates = [];
            $postRates = [];
            foreach ($diagnostics->where('kind', 'pre') as $att) {
                $ds = $att->dimension_scores[$dimKey] ?? null;
                if ($ds && ($ds['total'] ?? 0) > 0) {
                    $preRates[] = ($ds['correct'] * 100) / $ds['total'];
                }
            }
            foreach ($diagnostics->where('kind', 'post') as $att) {
                $ds = $att->dimension_scores[$dimKey] ?? null;
                if ($ds && ($ds['total'] ?? 0) > 0) {
                    $postRates[] = ($ds['correct'] * 100) / $ds['total'];
                }
            }

            $dimensionsBreakdown[] = [
                'key' => $dimKey,
                'label' => trim($dimLabel),
                'pre_avg' => ! empty($preRates) ? round(array_sum($preRates) / count($preRates), 1) : null,
                'post_avg' => ! empty($postRates) ? round(array_sum($postRates) / count($postRates), 1) : null,
            ];
        }

        // 4. Estudiantes en riesgo con nombres reales y preguntas con mayor tasa de error
        $rawAtRisk = [];
        if ($classroomId !== null) {
            $rawAtRisk = ($this->analytics)->atRiskStudents($classroomId);
        } else {
            foreach ($classroomIds as $cid) {
                foreach (($this->analytics)->atRiskStudents((int) $cid) as $rItem) {
                    $rawAtRisk[$rItem['user_id']] = $rItem;
                }
            }
            $rawAtRisk = array_values($rawAtRisk);
        }

        $usersMap = \App\Domain\Users\Models\User::query()
            ->whereIn('id', collect($rawAtRisk)->pluck('user_id')->all())
            ->get()
            ->keyBy('id');

        $atRiskEnriched = collect($rawAtRisk)->map(function ($r) use ($usersMap) {
            $u = $usersMap->get($r['user_id']);
            return [
                'user_id' => $r['user_id'],
                'name' => $u?->name ?? "Estudiante #{$r['user_id']}",
                'email' => $u?->email ?? '—',
                'attempts' => $r['attempts'],
                'avg_score' => $r['avg_score'],
            ];
        })->all();

        $rawFailed = [];
        if ($classroomId !== null) {
            $rawFailed = ($this->analytics)->topFailedQuestions($classroomId, 5);
        } elseif (! empty($classroomIds)) {
            $rawFailed = ($this->analytics)->topFailedQuestions((int) $classroomIds[0], 5);
        }

        $questionsMap = \App\Domain\Assessments\Models\Question::query()
            ->with('quiz.module')
            ->whereIn('id', collect($rawFailed)->pluck('question_id')->all())
            ->get()
            ->keyBy('id');

        $topFailedEnriched = collect($rawFailed)->map(function ($f) use ($questionsMap) {
            $q = $questionsMap->get($f['question_id']);
            $cleanStatement = $q ? preg_replace('/```[\s\S]*?```/', '[Bloque de código]', $q->statement) : "Pregunta #{$f['question_id']}";
            $cleanStatement = trim(str_replace(['**', '`', "\n"], ['', '', ' '], $cleanStatement));
            return [
                'question_id' => $f['question_id'],
                'module_title' => $q?->quiz?->module?->title ?? 'Evaluación de Módulo',
                'statement' => \Illuminate\Support\Str::limit($cleanStatement, 110),
                'total' => $f['total'],
                'error_rate' => $f['error_rate'],
            ];
        })->all();

        $avgCourseProgress = count($studentsRoster) > 0
            ? round(collect($studentsRoster)->avg('progress_percent'), 1)
            : 0.0;

        return [
            'meta' => [
                'title' => $classroom ? $classroom->name : ($user->isAdmin() ? 'Consolidado Institucional Global' : 'Consolidado de Mis Aulas'),
                'institution' => $classroom?->institution ?: 'Instituto de Educación Básica / Diversificado',
                'grade_level' => $classroom?->grade_level ?: 'Ciclo General',
                'academic_year' => $classroom?->academic_year ?: date('Y'),
                'code' => $classroom?->activeCode()->first()?->code ?: 'Múltiples',
                'teacher_name' => $classroom?->teacher?->name ?: $user->name,
                'teacher_email' => $classroom?->teacher?->email ?: $user->email,
                'students_count' => count($studentsRoster),
                'avg_course_progress' => $avgCourseProgress,
                'generated_at' => now()->timezone('America/Guatemala')->format('d/m/Y H:i'),
            ],
            'metrics' => ($this->analytics)->metricsForClassroom($classroomId),
            'modules_summary' => $modulesSummary,
            'students' => $studentsRoster,
            'pre_post' => $prePost,
            'dimensions' => $dimensionsBreakdown,
            'at_risk' => $atRiskEnriched,
            'top_failed_questions' => $topFailedEnriched,
        ];
    }

    /** POST /api/v1/me/certificate — Genera el Diploma Oficial de Finalización en PDF para el estudiante */
    public function studentCertificate(Request $request): \Illuminate\Http\Response
    {
        $user = $request->user();

        $postAttempt = \App\Domain\Diagnostic\Models\DiagnosticAttempt::query()
            ->where('user_id', $user->id)
            ->where('kind', 'post')
            ->where('status', 'completed')
            ->first();

        abort_unless($postAttempt !== null, 403, 'Debes completar los módulos y la Evaluación Final (Post-test) para obtener tu certificado.');

        $preAttempt = \App\Domain\Diagnostic\Models\DiagnosticAttempt::query()
            ->where('user_id', $user->id)
            ->where('kind', 'pre')
            ->where('status', 'completed')
            ->first();

        $membership = \App\Domain\Classrooms\Models\ClassroomStudent::query()
            ->where('student_id', $user->id)
            ->where('state', 'active')
            ->with('classroom.teacher')
            ->latest('joined_at')
            ->first();

        $classroom = $membership?->classroom;

        $xpProfile = \App\Domain\Gamification\Models\UserXpProfile::query()
            ->where('user_id', $user->id)
            ->first();

        $badgesCount = \App\Domain\Gamification\Models\UserBadge::query()
            ->where('user_id', $user->id)
            ->count();

        $modules = \App\Domain\Curriculum\Models\Module::query()
            ->where('status', 'published')
            ->orderBy('order_index')
            ->pluck('title')
            ->all();

        $preScore = $preAttempt !== null ? (int) $preAttempt->score_total : null;
        $postScore = (int) $postAttempt->score_total;
        $gain = $preScore !== null ? ($postScore - $preScore) : null;

        $rankNames = [
            1 => 'Iniciado',
            2 => 'Aprendiz',
            3 => 'Practicante',
            4 => 'Competente',
            5 => 'Maestro',
        ];
        $level = (int) ($xpProfile?->xp_level ?? 1);
        $rankName = $rankNames[$level] ?? 'Maestro';

        $issuedDate = ($postAttempt->submitted_at ?? now())->timezone('America/Guatemala')->format('d/m/Y');
        $folio = sprintf('EDUGT-%s-%05d', ($postAttempt->submitted_at ?? now())->format('Y'), $user->id);

        $payload = [
            'student_name' => $user->name,
            'student_email' => $user->email,
            'classroom_name' => $classroom?->name ?: 'Programa Autodirigido EduGT',
            'institution' => $classroom?->institution ?: 'Plataforma de Pensamiento Computacional EduGT',
            'grade_level' => $classroom?->grade_level ?: 'Educación Media',
            'academic_year' => $classroom?->academic_year ?: date('Y'),
            'teacher_name' => $classroom?->teacher?->name ?: 'Coordinación Académica EduGT',
            'pre_score' => $preScore,
            'post_score' => $postScore,
            'gain' => $gain,
            'xp_total' => (int) ($xpProfile?->xp_total ?? 0),
            'xp_level' => $level,
            'rank_name' => $rankName,
            'badges_count' => $badgesCount,
            'modules' => $modules,
            'issued_date' => $issuedDate,
            'folio' => $folio,
        ];

        $pdf = ($this->exporter)->pdf('student_certificate', $payload);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="certificado-edugt-'.strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $user->name)).'.pdf"',
        ]);
    }
}
