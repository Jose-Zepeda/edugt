<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Gamification\Contracts\BadgeAwarder;
use App\Domain\Gamification\Contracts\StreakService;
use App\Domain\Gamification\Contracts\XpLedger;
use App\Domain\Lab\Models\LabAttempt;
use App\Domain\Lab\Models\LabChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Módulo de Laboratorio Algorítmico y Prueba de Escritorio (Sandbox de Pseudocódigo).
 * Cumple RB-10: la solución canónica jamás se expone al navegador; el servidor evalúa y otorga XP.
 */
final class LabController extends Controller
{
    public function __construct(
        private readonly XpLedger $xpLedger,
        private readonly StreakService $streak,
        private readonly BadgeAwarder $badgeAwarder,
    ) {}

    /** GET /api/v1/lab/challenges */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $challenges = LabChallenge::query()
            ->where('status', 'published')
            ->with('module:id,title,order_index')
            ->orderBy('order_index')
            ->get();

        $attempts = LabAttempt::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('lab_challenge_id');

        $completedCount = 0;
        $totalXpEarned = 0;

        $items = $challenges->map(function (LabChallenge $ch) use ($attempts, &$completedCount, &$totalXpEarned) {
            /** @var LabAttempt|null $att */
            $att = $attempts->get($ch->id);
            $isCompleted = (bool) ($att?->completed ?? false);
            $xpAwarded = (int) ($att?->xp_awarded ?? 0);

            if ($isCompleted) {
                $completedCount++;
            }
            $totalXpEarned += $xpAwarded;

            $cleanModTitle = $ch->module
                ? preg_replace('/^Módulo\s*\d+\s*[:.-]?\s*/iu', '', $ch->module->title)
                : 'Algoritmos Generales';

            return [
                'id' => $ch->id,
                'slug' => $ch->slug,
                'module_id' => $ch->module_id,
                'module_number' => $ch->module ? ($ch->module->order_index + 1) : 1,
                'module_title' => trim($cleanModTitle),
                'title' => $ch->title,
                'description' => $ch->description,
                'dimension' => $ch->dimension,
                'difficulty' => $ch->difficulty,
                'xp_reward' => $ch->xp_reward,
                'order_index' => $ch->order_index,
                'simulator' => $ch->simulator_json,
                'challenge' => $ch->challenge_json,
                'completed' => $isCompleted,
                'attempts_count' => (int) ($att?->attempts_count ?? 0),
                'best_score' => (int) ($att?->best_score ?? 0),
                'xp_awarded' => $xpAwarded,
                'completed_at' => $att?->completed_at?->toIso8601String(),
            ];
        })->all();

        $totalChallenges = count($items);
        $progressPct = $totalChallenges > 0
            ? (int) round(($completedCount * 100) / $totalChallenges)
            : 0;

        return response()->json([
            'summary' => [
                'total_challenges' => $totalChallenges,
                'completed_challenges' => $completedCount,
                'total_xp_earned' => $totalXpEarned,
                'progress_percent' => $progressPct,
            ],
            'data' => $items,
        ]);
    }

    /** POST /api/v1/lab/challenges/{id}/submit */
    public function submit(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'block_order' => ['required', 'array', 'min:1'],
            'block_order.*' => ['required', 'string'],
            'trace_option' => ['required', 'string'],
        ]);

        $challenge = LabChallenge::query()
            ->where('status', 'published')
            ->findOrFail($id);

        $solution = $challenge->solution_json ?? [];
        $correctOrder = array_values($solution['correct_block_order'] ?? []);
        $correctTrace = (string) ($solution['correct_trace_option'] ?? '');

        $submittedOrder = array_values($data['block_order']);
        $submittedTrace = (string) $data['trace_option'];

        $orderOk = ($submittedOrder === $correctOrder);
        $traceOk = ($submittedTrace === $correctTrace);
        $passed = ($orderOk && $traceOk);
        $score = ($orderOk ? 50 : 0) + ($traceOk ? 50 : 0);

        $attempt = LabAttempt::query()->firstOrNew([
            'user_id' => $user->id,
            'lab_challenge_id' => $challenge->id,
        ]);

        $alreadyCompleted = (bool) $attempt->completed;
        $newAttemptsCount = ((int) $attempt->attempts_count) + 1;
        $xpAwardedNow = 0;

        $attempt->attempts_count = $newAttemptsCount;
        $attempt->best_score = max((int) $attempt->best_score, $score);

        if ($passed && ! $alreadyCompleted) {
            $xpAwardedNow = max(20, (int) floor($challenge->xp_reward * pow(0.8, max(0, $newAttemptsCount - 1))));
            $attempt->completed = true;
            $attempt->xp_awarded = $xpAwardedNow;
            $attempt->completed_at = now();
        }

        $attempt->save();

        $badgesAwarded = [];
        if ($xpAwardedNow > 0) {
            $this->xpLedger->award(
                $user->id,
                $xpAwardedNow,
                'lab',
                "Reto de Laboratorio superado: {$challenge->title}",
                ['lab_challenge_id' => $challenge->id, 'attempt_number' => $newAttemptsCount],
            );
            $this->streak->markActivity($user->id);
            $badgesAwarded = $this->badgeAwarder->evaluate('lab_completed', ['user' => $user]);
        } elseif ($passed) {
            $badgesAwarded = $this->badgeAwarder->evaluate('lab_completed', ['user' => $user]);
        }

        if ($passed) {
            $feedback = $solution['explanation_success']
                ?? '¡Excelente trabajo! Ordenaste la estructura lógica sin errores y dedujiste con exactitud el valor final en memoria.';
        } elseif (! $orderOk && ! $traceOk) {
            $feedback = ($solution['explanation_error_order'] ?? 'Revisa el orden lógico de las instrucciones del algoritmo.')
                .' Además, '.lcfirst($solution['explanation_error_trace'] ?? 'verifica en el simulador paso a paso cómo cambia el valor final de la variable en memoria.');
        } elseif (! $orderOk) {
            $feedback = $solution['explanation_error_order']
                ?? 'Tu predicción de la prueba de escritorio es correcta, pero el orden de los bloques de pseudocódigo tiene una instrucción fuera de secuencia.';
        } else {
            $feedback = $solution['explanation_error_trace']
                ?? '¡El orden de los bloques del algoritmo es correcto! Sin embargo, el valor final seleccionado en la prueba de escritorio no coincide con el estado final en memoria.';
        }

        return response()->json([
            'passed' => $passed,
            'order_ok' => $orderOk,
            'trace_ok' => $traceOk,
            'score' => $score,
            'xp_awarded_now' => $xpAwardedNow,
            'total_xp_for_challenge' => (int) $attempt->xp_awarded,
            'attempts_count' => $newAttemptsCount,
            'feedback' => $feedback,
            'badges_awarded' => $badgesAwarded,
        ]);
    }
}
