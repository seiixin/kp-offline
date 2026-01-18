<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px; }
        th { background: #eee; }
    </style>
</head>
<body>

<h3>Audit Logs</h3>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Entity ID</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($logs as $log)
            <tr>
                <td>{{ $log->created_at }}</td>
                <td>{{ $log->actor->name ?? 'System' }}</td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->entity_type }}</td>
                <td>{{ $log->entity_id }}</td>
                <td>{{ json_encode($log->detail_json) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
