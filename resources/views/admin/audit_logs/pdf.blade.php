<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
        }

        h2 {
            margin-bottom: 6px;
        }

        .meta {
            font-size: 9px;
            color: #555;
            margin-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th {
            background: #f2f2f2;
            font-weight: bold;
            text-align: left;
            padding: 6px;
            border: 1px solid #ccc;
            font-size: 9px;
        }

        td {
            padding: 6px;
            border: 1px solid #ddd;
            vertical-align: top;
            word-wrap: break-word;
        }

        .actor {
            font-weight: bold;
        }

        .action {
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            border-radius: 4px;
            background: #e5e7eb;
        }

        .detail-line {
            margin-bottom: 2px;
        }

        .money {
            font-weight: bold;
        }

        .muted {
            font-size: 9px;
            color: #666;
        }
    </style>
</head>
<body>

<h2>Audit Logs</h2>

<div class="meta">
    Generated at {{ now()->format('Y-m-d H:i:s') }}
</div>

<table>
    <thead>
        <tr>
            <th width="14%">Date</th>
            <th width="14%">Actor</th>
            <th width="18%">Action</th>
            <th width="14%">Entity</th>
            <th width="10%">ID</th>
            <th width="30%">Details</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($logs as $log)
            @php
                $d = $log->detail_json ?? [];
            @endphp

            <tr>
                <td>
                    {{ $log->created_at->format('Y-m-d') }}<br>
                    <span class="muted">{{ $log->created_at->format('H:i:s') }}</span>
                </td>

                <td class="actor">
                    {{ $log->actor->name ?? 'System' }}
                </td>

                <td class="action">
                    @if (str_contains($log->action, 'withdrawal'))
                        Player Withdrawal
                    @elseif (str_contains($log->action, 'recharge'))
                        Offline Recharge
                    @else
                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                    @endif
                </td>

                <td>
                    <span class="badge">
                        {{ ucwords(str_replace('_', ' ', $log->entity_type)) }}
                    </span>
                </td>

                <td>
                    #{{ $log->entity_id }}
                </td>

                <td>
                    {{-- WITHDRAWAL --}}
                    @if ($log->entity_type === 'offline_withdrawal')
                        <div class="detail-line">
                            Diamonds: <strong>{{ number_format($d['diamonds'] ?? 0) }}</strong>
                        </div>
                        <div class="detail-line money">
                            Payout: ₱{{ number_format(($d['php'] ?? 0), 2) }}
                        </div>
                    @if (!empty($d['mongo_user_id']) && !empty($players[$d['mongo_user_id']]))
                        <div class="detail-line muted">
                            Player: {{ $players[$d['mongo_user_id']] }}
                        </div>
                    @endif


                    {{-- RECHARGE --}}
                    @elseif ($log->entity_type === 'offline_recharge')
                        <div class="detail-line">
                            Coins: <strong>{{ number_format($d['coins'] ?? 0) }}</strong>
                        </div>
                        <div class="detail-line money">
                            PHP Value: ₱{{ number_format(($d['php'] ?? 0), 2) }}
                        </div>

                    {{-- FALLBACK --}}
                    @else
                        <pre class="muted">{{ json_encode($d, JSON_PRETTY_PRINT) }}</pre>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
