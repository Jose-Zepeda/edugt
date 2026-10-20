<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Curriculum\Actions\CompleteLesson;
use App\Domain\Curriculum\Contracts\UnlockEvaluator;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\Mission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RF-8, RF-9, RF-10: misiones con árbol de desbloqueo por usuario.
 */
final class CurriculumController extends Controller
{
    public function __construct(
        private readonly UnlockEvaluator $unlockEvaluator,
        private readonly CompleteLesson $completeLesson,
    ) {}

    /** GET /api/v1/me/missions — árbol con locked/unlocked/completed */
    public function missions(Request $request): JsonResponse
    {
        $user = $request->user();

        $missions = Mission::query()->published()
            ->with('modules.lessons')
            ->orderBy('order_index')
            ->get();

        $lessonIds = $missions->flatMap->modules->flatMap->lessons->pluck('id');
        $progressMap = \App\Domain\Curriculum\Models\LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        /** @var Lesson $lesson */
        $lessonsIndex = $missions->flatMap->modules->flatMap->lessons->mapWithKeys(
            fn ($lesson) => [$lesson->id => $lesson],
        );

        $data = $missions->map(function (Mission $mission) use ($progressMap, $lessonsIndex, $user) {
            return [
                'id' => $mission->id,
                'slug' => $mission->slug,
                'title' => $mission->title,
                'description' => $mission->description,
                'xp_reward' => $mission->xp_reward,
                'modules' => $mission->modules->map(function ($module) use ($progressMap, $lessonsIndex, $user) {
                    $quizPassed = $this->unlockEvaluator->hasPassedModuleQuiz($user, $module);

                    return [
                        'id' => $module->id,
                        'title' => $module->title,
                        'required_level' => $module->required_level,
                        'status' => $module->status,
                        'quiz_passed' => $quizPassed,
                        'lessons' => $module->lessons->map(function (Lesson $lesson) use ($progressMap, $lessonsIndex, $user, $quizPassed) {
                            $progress = $progressMap->get($lesson->id);
                            $lessonModel = $lessonsIndex[$lesson->id];
                            $fullLesson = $lessonModel ?? $lesson;

                            return [
                                'id' => $lesson->id,
                                'title' => $fullLesson->title,
                                'locked' => ! $this->unlockEvaluator->canAccess($user, $fullLesson),
                                'completed' => ($progress?->completed ?? false)
                                    || ($fullLesson->requires_quiz_pass && $quizPassed),
                                'time_spent_seconds' => $progress?->time_spent_seconds ?? 0,
                                'estimated_minutes' => $fullLesson->estimated_minutes,
                                'xp_reward' => $fullLesson->xp_reward,
                                'requires_quiz_pass' => $fullLesson->requires_quiz_pass,
                            ];
                        }),
                        // RFC-11: id del quiz del módulo (el FE arma el enlace a la evaluación)
                        'quiz_id' => $module->quiz()->value('id'),
                        'lessons_completed' => $module->lessons->filter(
                            fn ($lesson) => $progressMap->get($lesson->id)?->completed,
                        )->count(),
                    ];
                }),
            ];
        });

        $allModules = $data->flatMap(fn ($m) => $m['modules']);
        $allModulesCompleted = $allModules->isNotEmpty() && $allModules->every(function ($mod) {
            $lessonsAllDone = collect($mod['lessons'])->every(fn ($l) => (bool) $l['completed']);
            $quizDone = $mod['quiz_id'] ? (bool) $mod['quiz_passed'] : true;
            return $lessonsAllDone && $quizDone;
        });

        $diagAttempts = \App\Domain\Diagnostic\Models\DiagnosticAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->get()
            ->keyBy('kind');

        $preAttempt = $diagAttempts->get('pre');
        $postAttempt = $diagAttempts->get('post');
        $preCompleted = $preAttempt !== null || $user->learning_track !== null;
        $postCompleted = $postAttempt !== null;

        $dimensionLabels = [
            'abstraction' => 'Abstracción',
            'decomposition' => 'Descomposición',
            'pattern_recognition' => 'Reconocimiento de Patrones',
            'algorithms' => 'Algoritmos',
            'variables' => 'Variables',
            'conditionals' => 'Condicionales',
            'loops' => 'Ciclos / Bucles',
        ];

        $dimensionsComparison = [];
        foreach ($dimensionLabels as $dimKey => $dimLabel) {
            $preDim = $preAttempt?->dimension_scores[$dimKey] ?? null;
            $postDim = $postAttempt?->dimension_scores[$dimKey] ?? null;

            $prePct = ($preDim && ($preDim['total'] ?? 0) > 0)
                ? (int) round((($preDim['correct'] ?? 0) * 100) / $preDim['total'])
                : null;
            $postPct = ($postDim && ($postDim['total'] ?? 0) > 0)
                ? (int) round((($postDim['correct'] ?? 0) * 100) / $postDim['total'])
                : null;

            $dimensionsComparison[] = [
                'key' => $dimKey,
                'label' => $dimLabel,
                'pre_score' => $prePct,
                'post_score' => $postPct,
                'delta' => ($prePct !== null && $postPct !== null) ? ($postPct - $prePct) : null,
            ];
        }

        $preScore = $preAttempt !== null ? (int) $preAttempt->score_total : null;
        $postScore = $postAttempt !== null ? (int) $postAttempt->score_total : null;

        return response()->json([
            'data' => $data,
            'diagnostic_status' => [
                'pre_completed' => $preCompleted,
                'pre_score' => $preScore,
                'all_modules_completed' => $allModulesCompleted,
                'post_unlocked' => $preCompleted && $allModulesCompleted,
                'post_completed' => $postCompleted,
                'post_score' => $postScore,
                'post_submitted_at' => $postAttempt?->submitted_at?->timezone('America/Guatemala')->format('d/m/Y'),
                'gain' => ($preScore !== null && $postScore !== null) ? ($postScore - $preScore) : null,
                'certificate_available' => $allModulesCompleted && $postCompleted,
                'certificate_folio' => ($allModulesCompleted && $postCompleted)
                    ? sprintf('EDUGT-%s-%05d', ($postAttempt->submitted_at ?? now())->format('Y'), $user->id)
                    : null,
                'dimensions' => $dimensionsComparison,
            ],
        ]);
    }

    /** GET /api/v1/lessons/{id} */
    public function showLesson(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $lesson = Lesson::query()->with('module')->findOrFail($id);

        abort_unless($this->unlockEvaluator->canAccess($user, $lesson), 403, 'LOCKED_CONTENT');

        // Marca visita
        $this->completeLesson->start($user, $lesson);

        return response()->json([
            'data' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'content_markdown' => $lesson->content_markdown,
                'pseudocode_json' => $lesson->pseudocode_json,
                'images' => $lesson->images,
                'estimated_minutes' => $lesson->estimated_minutes,
                'xp_reward' => $lesson->xp_reward,
                'completed' => \App\Domain\Curriculum\Models\LessonProgress::query()
                    ->where('user_id', $user->id)
                    ->where('lesson_id', $lesson->id)
                    ->where('completed', true)
                    ->exists(),
            ],
        ]);
    }
}
