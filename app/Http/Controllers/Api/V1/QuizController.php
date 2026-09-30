<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assessments\Actions\TakeQuiz;
use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RF-11..RF-16: quizzes Card-Flow.
 */
final class QuizController extends Controller
{
    public function __construct(private readonly TakeQuiz $takeQuiz) {}

    /** GET /api/v1/quizzes/{id} — sin respuestas correctas (RB-10) */
    public function show(Request $request, int $id): JsonResponse
    {
        $quiz = Quiz::query()->with('questions.options')->findOrFail($id);

        return response()->json(['data' => ($this->takeQuiz)->show($quiz)]);
    }

    /** POST /api/v1/quizzes/{id}/attempts */
    public function start(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $quiz = Quiz::query()->findOrFail($id);

        $attempt = ($this->takeQuiz)->start($user, $quiz);

        return response()->json([
            'attempt_id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
        ], 201);
    }

    /** POST /api/v1/quizzes/{id}/attempts/{aid}/answers */
    public function answer(Request $request, int $id, int $aid): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_id' => ['required', 'integer'],
        ]);

        $attempt = QuizAttempt::query()->where('user_id', $user->id)->findOrFail($aid);

        $result = ($this->takeQuiz)->answer($user, $attempt, $data['question_id'], $data['option_id']);

        return response()->json($result);
    }

    /** POST /api/v1/quizzes/{id}/attempts/{aid}/submit */
    public function submit(Request $request, int $id, int $aid): JsonResponse
    {
        $user = $request->user();

        $attempt = QuizAttempt::query()->where('user_id', $user->id)->findOrFail($aid);

        $result = ($this->takeQuiz)->submit($user, $attempt);

        return response()->json($result);
    }

    /** GET /api/v1/quizzes/{id}/attempts — historial (RF-14) */
    public function history(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $quiz = Quiz::query()->findOrFail($id);

        return response()->json(['data' => ($this->takeQuiz)->history($user, $quiz)]);
    }
}
