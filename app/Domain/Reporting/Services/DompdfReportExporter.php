<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Contracts\ReportExporter;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;

/**
 * Único módulo autorizado a conocer Dompdf (§3.7).
 * RB-12: sin Excel/XLSX — solo PDF.
 */
final class DompdfReportExporter implements ReportExporter
{
    public function pdf(string $kind, array $payload): string
    {
        $view = match ($kind) {
            'classroom_performance' => 'reports.classroom_performance',
            'student_detail' => 'reports.student_detail',
            'pre_post_comparison' => 'reports.pre_post_comparison',
            default => abort(422, 'Tipo de reporte inválido.'),
        };

        return PdfFacade::loadView($view, ['payload' => $payload])
            ->setPaper('letter')
            ->output();
    }
}
