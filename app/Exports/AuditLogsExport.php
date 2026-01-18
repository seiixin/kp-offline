<?php

namespace App\Exports;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AuditLogsExport implements FromCollection, WithHeadings
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        return AuditLog::with('actor:id,name,email')
            ->latest()
            ->get()
            ->map(function ($log) {
                return [
                    'Date'        => $log->created_at->toDateTimeString(),
                    'Actor'       => $log->actor?->name ?? 'System',
                    'Email'       => $log->actor?->email,
                    'Action'      => $log->action,
                    'Entity Type' => $log->entity_type,
                    'Entity ID'   => $log->entity_id,
                    'Details'     => json_encode($log->detail_json),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Date',
            'Actor',
            'Email',
            'Action',
            'Entity Type',
            'Entity ID',
            'Details',
        ];
    }
}
