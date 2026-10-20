<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Classrooms\Actions\ManageClassrooms;
use App\Domain\Classrooms\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RF-19, RF-20: aulas para docentes; join para estudiantes.
 */
final class ClassroomController extends Controller
{
    public function __construct(private readonly ManageClassrooms $manage) {}

    /** POST /api/v1/classrooms (teacher activo) */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isTeacher() && $user->status === 'active', 403, 'TEACHER_PENDING_APPROVAL');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:160'],
            'grade_level' => ['nullable', 'string', 'max:80'],
            'academic_year' => ['required', 'string', 'max:9'],
        ]);

        $classroom = ($this->manage)->create($user, $data);

        return response()->json([
            'data' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'code' => $classroom->activeCode()->first()?->code,
            ],
        ], 201);
    }

    /** GET /api/v1/classrooms (aulas del docente autenticado o aulas inscritas del estudiante) */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isStudent()) {
            $classrooms = Classroom::query()
                ->whereHas('students', fn ($q) => $q->where('student_id', $user->id)->where('state', 'active'))
                ->with(['teacher:id,name,email'])
                ->get();

            return response()->json(['data' => $classrooms->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'institution' => $c->institution,
                'grade_level' => $c->grade_level,
                'academic_year' => $c->academic_year,
                'teacher_name' => $c->teacher?->name,
            ])]);
        }

        $classrooms = Classroom::query()
            ->where('teacher_id', $user->id)
            ->withCount(['students'])
            ->get();

        return response()->json(['data' => $classrooms->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'institution' => $c->institution,
            'grade_level' => $c->grade_level,
            'academic_year' => $c->academic_year,
            'students_count' => $c->students_count,
            'code' => $c->activeCode()->first()?->code,
        ])]);
    }

    /** PUT /api/v1/classrooms/{id} (docente dueño o admin) */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->findOrFail($id);

        abort_unless($user->isAdmin() || $classroom->teacher_id === $user->id, 403, 'FORBIDDEN');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:160'],
            'grade_level' => ['nullable', 'string', 'max:80'],
            'academic_year' => ['required', 'string', 'max:9'],
        ]);

        $classroom->update($data);

        return response()->json([
            'data' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'institution' => $classroom->institution,
                'grade_level' => $classroom->grade_level,
                'academic_year' => $classroom->academic_year,
                'code' => $classroom->activeCode()->first()?->code,
            ],
            'message' => 'Aula actualizada correctamente.',
        ]);
    }

    /** DELETE /api/v1/classrooms/{id} (docente dueño o admin) */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->findOrFail($id);

        abort_unless($user->isAdmin() || $classroom->teacher_id === $user->id, 403, 'FORBIDDEN');

        $classroom->delete();

        return response()->json(['message' => 'Aula eliminada correctamente.']);
    }

    /** POST /api/v1/classrooms/{id}/codes/regenerate (docente dueño) */
    public function regenerateCode(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->where('teacher_id', $user->id)->findOrFail($id);

        $code = ($this->manage)->generateCode($classroom);

        return response()->json(['code' => $code->code]);
    }

    /** POST /api/v1/classrooms/join {code} (student) */
    public function join(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        $membership = ($this->manage)->join($user, $data['code']);

        return response()->json([
            'classroom_id' => $membership->classroom_id,
            'state' => $membership->state,
        ], 201);
    }

    /** GET /api/v1/classrooms/{id}/students (docente dueño o admin) */
    public function students(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->findOrFail($id);

        abort_unless($user->isAdmin() || $classroom->teacher_id === $user->id, 403, 'FORBIDDEN');

        $rows = $classroom->students()->with('student')->get();
        $studentIds = $rows->pluck('student_id')->all();

        if (empty($studentIds)) {
            return response()->json(['data' => []]);
        }

        $modules = \App\Domain\Curriculum\Models\Module::query()
            ->where('status', 'published')
            ->with([
                'lessons' => fn ($q) => $q->where('status', 'published')->orderBy('order_index'),
                'quiz',
            ])
            ->orderBy('order_index')
            ->get();

        $completedProgress = \App\Domain\Curriculum\Models\LessonProgress::query()
            ->whereIn('user_id', $studentIds)
            ->where('completed', true)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($items) => $items->pluck('lesson_id')->flip());

        $quizAttempts = \App\Domain\Assessments\Models\QuizAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->where('status', 'submitted')
            ->get()
            ->groupBy('user_id');

        $diagnostics = \App\Domain\Diagnostic\Models\DiagnosticAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->where('status', 'completed')
            ->get()
            ->groupBy('user_id');

        $xpProfiles = \App\Domain\Gamification\Models\UserXpProfile::query()
            ->whereIn('user_id', $studentIds)
            ->get()
            ->keyBy('user_id');

        $badgeCounts = \App\Domain\Gamification\Models\UserBadge::query()
            ->whereIn('user_id', $studentIds)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return response()->json(['data' => $rows->map(function ($m) use (
            $modules,
            $completedProgress,
            $quizAttempts,
            $diagnostics,
            $xpProfiles,
            $badgeCounts
        ) {
            $sid = $m->student_id;
            $userLessons = $completedProgress->get($sid, collect());
            $userQuizzes = $quizAttempts->get($sid, collect());
            $userDiags = $diagnostics->get($sid, collect());

            $preAttempt = $userDiags->firstWhere('kind', 'pre');
            $postAttempt = $userDiags->firstWhere('kind', 'post');
            $pretestCompleted = $m->student->learning_track !== null || $preAttempt !== null;

            $totalSteps = 0;
            $totalDone = 0;
            $activeStage = null;
            $modulesProgress = [];

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

                $modTotal = $standardLessons->count() + (($mod->quiz || $quizLesson) ? 1 : 0);
                $modDone = 0;

                foreach ($standardLessons as $lesson) {
                    $isDone = $userLessons->has($lesson->id);
                    if ($isDone) {
                        $modDone++;
                    } elseif ($activeStage === null) {
                        $activeStage = [
                            'module_number' => $modNum,
                            'module_title' => $cleanTitle,
                            'step_title' => $lesson->title,
                            'is_quiz' => false,
                        ];
                    }
                }

                if ($mod->quiz || $quizLesson) {
                    $isQuizStepDone = $quizPassed || ($quizLesson && $userLessons->has($quizLesson->id));
                    if ($isQuizStepDone) {
                        $modDone++;
                    } elseif ($activeStage === null) {
                        $activeStage = [
                            'module_number' => $modNum,
                            'module_title' => $cleanTitle,
                            'step_title' => 'Evaluación del Módulo',
                            'is_quiz' => true,
                        ];
                    }
                }

                $totalSteps += $modTotal;
                $totalDone += $modDone;

                $modulesProgress[] = [
                    'module_number' => $modNum,
                    'title' => $cleanTitle,
                    'done' => $modDone,
                    'total' => $modTotal,
                    'percent' => $modTotal > 0 ? (int) round(($modDone * 100) / $modTotal) : 0,
                    'quiz_passed' => $quizPassed,
                    'best_quiz_score' => $bestQuizScore !== null ? (int) $bestQuizScore : null,
                ];
            }

            $progressPercent = $totalSteps > 0 ? (int) round(($totalDone * 100) / $totalSteps) : 0;
            $xpProfile = $xpProfiles->get($sid);

            return [
                'student_id' => $sid,
                'name' => $m->student->name,
                'email' => $m->student->email,
                'avatar_url' => $m->student->avatar_url,
                'learning_track' => $m->student->learning_track,
                'joined_at' => $m->joined_at?->toIso8601String(),
                'state' => $m->state,
                'pretest_completed' => $pretestCompleted,
                'pretest_score' => $preAttempt !== null ? (int) $preAttempt->score_total : null,
                'posttest_completed' => $postAttempt !== null,
                'posttest_score' => $postAttempt !== null ? (int) $postAttempt->score_total : null,
                'xp_total' => (int) ($xpProfile?->xp_total ?? 0),
                'xp_level' => (int) ($xpProfile?->xp_level ?? 1),
                'badges_count' => (int) ($badgeCounts->get($sid) ?? 0),
                'completed_lessons' => $totalDone,
                'total_lessons' => $totalSteps,
                'progress_percent' => $progressPercent,
                'current_stage' => $pretestCompleted ? $activeStage : null,
                'modules_progress' => $modulesProgress,
            ];
        })]);
    }

    /** POST /api/v1/classrooms/{id}/students/{studentId}/state {state} */
    public function setStudentState(Request $request, int $id, int $studentId): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate(['state' => ['required', 'in:active,archived,removed']]);

        $membership = ($this->manage)->setState($user, $id, $studentId, $data['state']);

        return response()->json(['state' => $membership->state]);
    }
}
