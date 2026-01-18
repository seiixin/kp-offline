<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ADMIN — AUDIT LOGS
 */
class AuditLogsController extends Controller
{
    /* =====================================================
     | GET /admin/audit-logs
     | LIST + SEARCH + FILTER
     ===================================================== */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'           => ['nullable', 'string', 'max:120'],
            'entity_type' => ['nullable', 'string', 'max:80'],
            'action'      => ['nullable', 'string', 'max:120'],
            'per'         => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = AuditLog::query()
            ->with('actor:id,name,email')
            ->latest();

        /* ===============================
         | SEARCH
         =============================== */
        if (!empty($validated['q'])) {
            $q = trim($validated['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('action', 'like', "%{$q}%")
                    ->orWhere('entity_type', 'like', "%{$q}%")
                    ->orWhere('detail_json', 'like', "%{$q}%");
            });
        }

        /* ===============================
         | FILTERS
         =============================== */
        if (!empty($validated['entity_type'])) {
            $query->where('entity_type', $validated['entity_type']);
        }

        if (!empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        $page = $query->paginate($validated['per'] ?? 20);

        $rows = collect($page->items())->map(function ($log) {
            return [
                'id'          => $log->id,
                'when'        => $log->created_at->toDateTimeString(),
                'actor'       => $log->actor?->name ?? 'System',
                'actor_email' => $log->actor?->email,
                'action'      => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id'   => $log->entity_id,
                'detail'      => $log->detail_json,
            ];
        });

        return response()->json([
            'data' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'data'         => $rows,
            ],
        ]);
    }

    /* =====================================================
     | GET /admin/audit-logs/export/excel
     ===================================================== */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $fileName = 'audit_logs_' . now()->format('Ymd_His') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AuditLogsExport($request),
            $fileName
        );
    }

    /* =====================================================
     | GET /admin/audit-logs/export/pdf
     ===================================================== */
    public function exportPdf(Request $request)
    {
        $logs = $this->buildExportQuery($request)->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'admin.audit_logs.pdf',
            ['logs' => $logs]
        )->setPaper('a4', 'landscape');

        return $pdf->download(
            'audit_logs_' . now()->format('Ymd_His') . '.pdf'
        );
    }

    /* =====================================================
     | SHARED EXPORT QUERY
     ===================================================== */
    private function buildExportQuery(Request $request)
    {
        $query = AuditLog::query()
            ->with('actor:id,name,email')
            ->latest();

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('action', 'like', "%{$q}%")
                    ->orWhere('entity_type', 'like', "%{$q}%")
                    ->orWhere('detail_json', 'like', "%{$q}%");
            });
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        return $query;
    }
}
