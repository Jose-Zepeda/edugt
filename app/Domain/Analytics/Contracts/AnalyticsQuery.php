<?php

namespace App\Domain\Analytics\Contracts;

use App\Domain\Users\Models\User;

/**
 * RF-20/RF-21: métricas calculadas por SQL agregado (§3.7).
 * Contrato que consume Reporting (PDF) y el dashboard docente.
 */
interface AnalyticsQuery
{
    /** Métricas de un aula (o global si classroomId = null). */
    public function metricsForClassroom(?int $classroomId): array;

    /** Preguntas con mayor % de error. */
    public function topFailedQuestions(int $classroomId, int $limit = 10): array;

    /** Estudiantes en riesgo de rezago (tasa_aprobación < 70 % e intentos > 3). */
    public function atRiskStudents(int $classroomId): array;

    /** Comparación pretest vs postest de la cohorte (RF-28). */
    public function prePostComparison(?int $classroomId = null): array;
}
