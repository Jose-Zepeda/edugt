<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Contracts\AnalyticsQuery;
use Illuminate\Support\Facades\DB;

/**
 * §3.7: consultas SQL agregadas, sin tablas de métricas propias.
 * La definición de riesgo vive aquí (no en el FE).
 */
final class AnalyticsQueryDb implements AnalyticsQuery
{
    public function metricsForClassroom(?int $classroomId): array
    {
        $studentIds = $classroomId !== null
            ? DB::table('classroom_students')->where('classroom_id', $classroomId)->where('state', 'active')->pluck('student_id')
            : null;

        $attempts = DB::table('quiz_attempts')
            ->where('status', 'submitted')
            ->when($studentIds !== null, fn ($q) => $q->whereIn('user_id', $studentIds));

        $total = (clone $attempts)->count();
        $passed = (clone $attempts)->where('score', '>=', 70)->count();
        $avgScore = (clone $attempts)->avg('score');
        $avgAttemptsPerStudent = $total > 0
            ? DB::table('quiz_attempts')->where('status', 'submitted')->when($studentIds !== null, fn ($q) => $q->whereIn('user_id', $studentIds))->distinct()->count('user_id') > 0
                ? round($total / max(1, (clone $attempts)->distinct()->count('user_id')), 2)
                : 0.0
            : 0.0;

        // Dificultad por módulo: tasa de aprobación del quiz del módulo
        $moduleDifficulty = DB::table('quizzes as qz')
            ->join('quiz_attempts as qa', 'qa.quiz_id', '=', 'qz.id')
            ->where('qa.status', 'submitted')
            ->when($studentIds !== null, fn ($q) => $q->whereIn('qa.user_id', $studentIds))
            ->when($classroomId !== null, fn ($q) => $q->join('modules as m', 'm.id', '=', 'qz.module_id')
                ->where('m.mission_id', '>', 0))
            ->groupBy('qz.module_id')
            ->selectRaw('qz.module_id, COUNT(*) as attempts, AVG(qa.score) as avg_score, SUM(CASE WHEN qa.score >= 70 THEN 1 ELSE 0 END) as passed')
            ->orderBy('avg_score')
            ->get();

        // Retención: usuarios con actividad (lesson_progress o quiz_attempts) en los últimos 14 días / total activos
        $activeStudents = $studentIds !== null ? $studentIds->count() : DB::table('users')->where('role', 'student')->where('status', 'active')->count();
        $recentlyActive = DB::table('lesson_progress')
            ->when($studentIds !== null, fn ($q) => $q->whereIn('user_id', $studentIds))
            ->where('last_visited_at', '>=', now()->subDays(14))
            ->distinct()->count('user_id');

        return [
            'total_attempts' => $total,
            'passed_attempts' => $passed,
            'pass_rate' => $total > 0 ? round($passed * 100 / $total, 1) : 0.0,
            'avg_score' => $avgScore !== null ? round((float) $avgScore, 1) : null,
            'avg_attempts_per_student' => $avgAttemptsPerStudent,
            'module_difficulty' => $moduleDifficulty->map(fn ($m) => [
                'module_id' => $m->module_id,
                'attempts' => (int) $m->attempts,
                'avg_score' => round((float) $m->avg_score, 1),
                'pass_rate' => $m->attempts > 0 ? round($m->passed * 100 / $m->attempts, 1) : 0.0,
            ]),
            'retention_rate' => $activeStudents > 0 ? round($recentlyActive * 100 / $activeStudents, 1) : 0.0,
        ];
    }

    public function topFailedQuestions(int $classroomId, int $limit = 10): array
    {
        $studentIds = DB::table('classroom_students')->where('classroom_id', $classroomId)->where('state', 'active')->pluck('student_id');

        return DB::table('attempt_answers as aa')
            ->join('quiz_attempts as qa', 'qa.id', '=', 'aa.quiz_attempt_id')
            ->whereIn('qa.user_id', $studentIds)
            ->groupBy('aa.question_id')
            ->selectRaw('aa.question_id, COUNT(*) as total, SUM(CASE WHEN aa.is_correct = 1 THEN 1 ELSE 0 END) as correct')
            ->orderByRaw('(total - correct) / total DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'question_id' => $r->question_id,
                'total' => (int) $r->total,
                'error_rate' => $r->total > 0 ? round((($r->total - $r->correct) / $r->total) * 100, 1) : 0.0,
            ])
            ->all();
    }

    public function atRiskStudents(int $classroomId): array
    {
        // Regla §3.7: tasa_aprobación < 70 % E intentos_promedio > 3 en al menos un quiz
        $studentIds = DB::table('classroom_students')->where('classroom_id', $classroomId)->where('state', 'active')->pluck('student_id');

        return DB::table('quiz_attempts')
            ->whereIn('user_id', $studentIds)
            ->where('status', 'submitted')
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as attempts, AVG(score) as avg_score')
            ->havingRaw('AVG(score) < 70 AND COUNT(*) > 3')
            ->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id,
                'attempts' => (int) $r->attempts,
                'avg_score' => round((float) $r->avg_score, 1),
            ])
            ->all();
    }

    public function prePostComparison(?int $classroomId = null): array
    {
        $studentIds = $classroomId !== null
            ? DB::table('classroom_students')->where('classroom_id', $classroomId)->where('state', 'active')->pluck('student_id')
            : null;

        $query = fn (string $kind) => DB::table('diagnostic_attempts')
            ->where('kind', $kind)
            ->where('status', 'completed')
            ->when($studentIds !== null, fn ($q) => $q->whereIn('user_id', $studentIds))
            ->pluck('score_total', 'user_id');

        $pre = $query('pre');
        $post = $query('post');

        $paired = $pre->keys()->intersect($post->keys());
        $pairedPre = $pre->only($paired);
        $pairedPost = $post->only($paired);

        return [
            // Pares completos (base del Wilcoxon, RF-28): pre y post de la misma cohorte
            'paired_students' => $paired->count(),
            'pre_students' => $pre->count(),
            'post_students' => $post->count(),
            'pre_avg' => $paired->isNotEmpty() ? round($pairedPre->avg(), 1) : null,
            'post_avg' => $paired->isNotEmpty() ? round($pairedPost->avg(), 1) : null,
            'mean_gain' => $paired->isNotEmpty() ? round($pairedPost->avg() - $pairedPre->avg(), 1) : null,
        ];
    }
}
