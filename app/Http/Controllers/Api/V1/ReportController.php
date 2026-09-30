<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analytics\Contracts\AnalyticsQuery;
use App\Domain\Reporting\Contracts\ReportExporter;
use App\Domain\Classrooms\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RF-20, RF-21, RF-22: analítica docente y reportes PDF.
 * RB-09: docente solo ve SUS aulas; admin todo.
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly AnalyticsQuery $analytics,
        private readonly ReportExporter $exporter,
    ) {}

    /** GET /api/v1/classrooms/{id}/analytics */
    public function classroomAnalytics(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->findOrFail($id);

        abort_unless($user->isAdmin() || $classroom->teacher_id === $user->id, 403, 'FORBIDDEN');

        $metrics = ($this->analytics)->metricsForClassroom($id);

        return response()->json([
            'data' => [
                'metrics' => $metrics,
                'at_risk' => ($this->analytics)->atRiskStudents($id),
                'top_failed_questions' => ($this->analytics)->topFailedQuestions($id),
                'pre_post' => ($this->analytics)->prePostComparison($id),
            ],
        ]);
    }

    /** POST /api/v1/reports — genera PDF sincrónico (n pequeño: 50 aulas) */
    public function generate(Request $request): \Illuminate\Http\Response
    {
        $user = $request->user();

        $data = $request->validate([
            'kind' => ['required', 'in:classroom_performance'],
            'classroom_id' => ['nullable', 'integer'],
        ]);

        $classroomId = $data['classroom_id'] ?? null;

        if ($classroomId !== null && ! $user->isAdmin()) {
            $classroom = Classroom::query()->findOrFail($classroomId);
            abort_unless($classroom->teacher_id === $user->id, 403, 'FORBIDDEN');
        }

        $payload = [
            'metrics' => ($this->analytics)->metricsForClassroom($classroomId),
            'at_risk' => $classroomId !== null ? ($this->analytics)->atRiskStudents($classroomId) : [],
            'pre_post' => ($this->analytics)->prePostComparison($classroomId),
        ];

        $pdf = ($this->exporter)->pdf($data['kind'], $payload);

        // RB-12: SOLO PDF — sin Excel ni ningún otro formato
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="edugt-reporte-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }
}
