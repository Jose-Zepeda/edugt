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
                    return [
                        'id' => $module->id,
                        'title' => $module->title,
                        'required_level' => $module->required_level,
                        'status' => $module->status,
                        'lessons' => $module->lessons->map(function (Lesson $lesson) use ($progressMap, $lessonsIndex, $user) {
                            $progress = $progressMap->get($lesson->id);
                            $lessonModel = $lessonsIndex[$lesson->id];
                            $fullLesson = $lessonModel ?? $lesson;

                            return [
                                'id' => $lesson->id,
                                'title' => $fullLesson->title,
                                'locked' => ! $this->unlockEvaluator->canAccess($user, $fullLesson),
                                'completed' => $progress?->completed ?? false,
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

        return response()->json(['data' => $data]);
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
