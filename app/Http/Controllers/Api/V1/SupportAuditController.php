<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RF-23 (bitácora — admin) y RF-24 (reporte de errores/sugerencias).
 */
final class SupportAuditController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** POST /api/v1/support/tickets — cualquier usuario autenticado reporta */
    public function createTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:error,suggestion'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'module' => ['nullable', 'string', 'max:80'],
        ]);

        $ticket = SupportTicket::query()->create([
            'reporter_id' => $request->user()->id,
            'kind' => $data['kind'],
            'description' => $data['description'],
            'module' => $data['module'] ?? null,
            'status' => 'open',
        ]);

        return response()->json(['id' => $ticket->id, 'status' => $ticket->status], 201);
    }

    /** GET /api/v1/admin/support-tickets?status= */
    public function listTickets(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Solo administradores.');

        $query = SupportTicket::query()->with('reporter:id,name,email');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->orderByDesc('created_at')->get()]);
    }

    /** POST /api/v1/admin/support-tickets/{id}/status {status} */
    public function setTicketStatus(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin->isAdmin(), 403, 'Solo administradores.');

        $data = $request->validate(['status' => ['required', 'in:open,in_review,resolved']]);

        $ticket = SupportTicket::query()->findOrFail($id);
        $ticket->forceFill(['status' => $data['status']])->save();

        $this->audit->log(
            user: $admin,
            action: 'support.status_changed',
            targetType: 'support_ticket',
            targetId: $ticket->id,
            context: ['new_status' => $data['status']],
        );

        return response()->json(['status' => $ticket->status]);
    }

    /** GET /api/v1/admin/audit-logs */
    public function auditLogs(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Solo administradores.');

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit((int) $request->query('limit', 100))
            ->get(['id', 'user_id', 'action', 'target_type', 'target_id', 'context', 'ip', 'created_at']);

        return response()->json(['data' => $logs]);
    }
}
