<?php

namespace App\Exports;

use App\Models\AuditLog;
use App\Services\MongoEconomyService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AuditLogsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected Request $request;
    protected MongoEconomyService $mongo;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->mongo   = app(MongoEconomyService::class);
    }

    /* =====================================================
     | DATA SOURCE
     ===================================================== */
    public function collection()
    {
        $query = AuditLog::with('actor:id,name,email')->latest();

        if ($this->request->filled('q')) {
            $q = trim($this->request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('action', 'like', "%{$q}%")
                    ->orWhere('entity_type', 'like', "%{$q}%")
                    ->orWhere('detail_json', 'like', "%{$q}%");
            });
        }

        if ($this->request->filled('entity_type')) {
            $query->where('entity_type', $this->request->entity_type);
        }

        return $query->get();
    }

    /* =====================================================
     | ROW MAPPING (HUMAN READABLE)
     ===================================================== */
    public function map($log): array
    {
        $d = $log->detail_json ?? [];

        /* ===============================
         | RESOLVE PLAYER NAME (MONGO)
         =============================== */
        $playerName = null;

        if (!empty($d['mongo_user_id'])) {
            $player = $this->mongo->getUserBasicByMongoId($d['mongo_user_id']);
            if ($player) {
                $playerName = $player['full_name']
                    ?? $player['username']
                    ?? null;
            }
        }

        return [
            $log->created_at->format('Y-m-d H:i:s'),
            $log->actor->name ?? 'System',
            $log->actor->email ?? null,

            $this->prettyAction($log->action),
            ucwords(str_replace('_', ' ', $log->entity_type)),
            $log->entity_id,

            // Human-readable player
            $playerName,

            // Financial columns
            $d['diamonds'] ?? null,
            $d['coins'] ?? null,
            $d['usd'] ?? null,
            $d['php'] ?? null,
        ];
    }

    /* =====================================================
     | HEADERS
     ===================================================== */
    public function headings(): array
    {
        return [
            'Date & Time',
            'Actor',
            'Email',
            'Action',
            'Entity Type',
            'Entity ID',

            'Player Name',

            'Diamonds',
            'Coins',
            'USD Amount',
            'PHP Amount',
        ];
    }

    /* =====================================================
     | ACTION LABEL CLEANUP
     ===================================================== */
    private function prettyAction(string $action): string
    {
        if (str_contains($action, 'withdrawal')) {
            return 'Player Withdrawal';
        }

        if (str_contains($action, 'recharge')) {
            return 'Offline Recharge';
        }

        return ucwords(str_replace('_', ' ', $action));
    }
}
