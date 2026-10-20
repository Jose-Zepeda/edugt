<?php

namespace App\Domain\Reporting\Contracts;

use App\Domain\Users\Models\User;

/**
 * RF-22/RB-12: reportes EXCLUSIVAMENTE en PDF.
 */
interface ReportExporter
{
    /**
     * Genera un reporte PDF y devuelve el contenido binario.
     *
     * @param  string  $kind  classroom_performance|student_detail|pre_post_comparison
     * @param  array<string, mixed>  $payload  datos ya calculados por Analytics
     */
    public function pdf(string $kind, array $payload): string;
}
