<?php

namespace App\Domain\Assessments\Services;

use App\Domain\Assessments\Models\Quiz;
use App\Domain\Assessments\Models\QuizAttempt;

/**
 * RF-12/RB-10: la calificación se calcula SOLO en el servidor.
 * La respuesta correcta jamás sale en el payload inicial; el feedback
 * inmediato (RF-13) se entrega por respuesta enviada.
 */
final class QuizGrader
{
    /**
     * Calcula score 0-100 en servidor a partir de las respuestas registradas.
     * @return array{score: int, correct: int, total: int, passed: bool}
     */
    public function grade(QuizAttempt $attempt): array
    {
        $quiz = Quiz::query()->with('questions')->find($attempt->quiz_id);

        $total = $quiz->questions->count();
        if ($total === 0) {
            return ['score' => 0, 'correct' => 0, 'total' => 0, 'passed' => false];
        }

        $correct = $attempt->answers()->where('is_correct', true)->count();

        $score = (int) round($correct * 100 / $total);

        return [
            'score' => $score,
            'correct' => $correct,
            'total' => $total,
            'passed' => $score >= $quiz->passing_score, // RB-05: 70/100
        ];
    }
}
