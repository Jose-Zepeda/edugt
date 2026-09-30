<?php

namespace App\Domain\OfflineSync\Actions;

use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\LessonProgress;
use App\Domain\Users\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/**
 * RF-25..27, RB-13, RB-14 (§3.8):
 *  - Bundle: solo lectura (lecciones + pseudocódigo + imágenes) con versión/hashes.
 *  - Sync: SOLO progreso de lectura; un quiz offline NO existe (se rechaza).
 *  - Merge idempotente por last_visited_at mayor.
 */
final readonly class OfflineSync
{
    /** GET /api/v1/offline/bundle — manifest descargable */
    public function bundle(User $user): array
    {
        $missions = \App\Domain\Curriculum\Models\Mission::query()->published()
            ->with(['modules' => fn ($q) => $q->published()->with(['lessons' => fn ($lq) => $lq->published()])])
            ->orderBy('order_index')->get();

        $lessons = [];
        foreach ($missions as $mission) {
            foreach ($mission->modules as $module) {
                foreach ($module->lessons as $lesson) {
                    $lessons[] = [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'content_markdown' => $lesson->content_markdown,
                        'pseudocode_json' => $lesson->pseudocode_json,
                        'images' => $lesson->images,
                        'estimated_minutes' => $lesson->estimated_minutes,
                        'version_hash' => md5(($lesson->updated_at?->timestamp ?? 0).':'.$lesson->id),
                    ];
                }
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'lessons' => $lessons,
        ];
    }

    /**
     * POST /api/v1/offline/sync — progreso de lectura offline.
     * RB-13: payload con quiz_type o intento → 409, sin excepción.
     * RB-14: solo marca tiempo/visita; XP delta = 0 siempre.
     *
     * @param  list<array{lesson_id: int, time_spent_seconds: int, last_visited_at: string}>  $items
     * @return array{accepted: int, rejected: int, xp_awarded: int}
     */
    public function sync(User $user, array $items): array
    {
        $accepted = 0;
        $rejected = 0;

        foreach ($items as $item) {
            // RB-13: un intento de cuestionario offline jamás se acepta
            if (isset($item['quiz_answers']) || isset($item['quiz_id']) || isset($item['attempt_id'])) {
                $rejected++;
                continue;
            }

            $lesson = Lesson::query()->find($item['lesson_id'] ?? 0);
            if ($lesson === null) {
                $rejected++;
                continue;
            }

            $visitedAt = \Illuminate\Support\Carbon::parse($item['last_visited_at'] ?? now());

            DB::transaction(function () use ($user, $lesson, $item, $visitedAt, &$accepted) {
                $existing = LessonProgress::query()
                    ->where('user_id', $user->id)
                    ->where('lesson_id', $lesson->id)
                    ->first();

                if ($existing === null) {
                    // Primera vez: siempre se acepta
                    LessonProgress::query()->create([
                        'user_id' => $user->id,
                        'lesson_id' => $lesson->id,
                        'started_at' => $visitedAt,
                        'last_visited_at' => $visitedAt,
                        'time_spent_seconds' => max(0, (int) ($item['time_spent_seconds'] ?? 0)),
                    ]);
                } elseif ($visitedAt->gt($existing->last_visited_at ?? $visitedAt->copy()->subSecond())) {
                    // Merge idempotente: solo actualiza si lo offline es MÁS nuevo
                    $existing->forceFill([
                        'last_visited_at' => $visitedAt,
                        'time_spent_seconds' => $existing->time_spent_seconds + max(0, (int) ($item['time_spent_seconds'] ?? 0)),
                    ])->save();
                }

                $accepted++;
                // RB-14: XP delta = 0 — sin recompensas por lectura offline
            });
        }

        return ['accepted' => $accepted, 'rejected' => $rejected, 'xp_awarded' => 0];
    }
}
