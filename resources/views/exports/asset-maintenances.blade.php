<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Daftar Pemeliharaan Aset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin-bottom: 0;
        }

        .number-cell {
            text-align: right;
        }

        .status-draft {
            color: #92400E;
        }

        .status-completed {
            color: #065F46;
        }

        .status-cancelled {
            color: #991B1B;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Daftar Pemeliharaan Aset</h1>
        <p>Tanggal: {{ date('d/m/Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Aset</th>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Deskripsi</th>
                <th>Vendor</th>
                <th>Total Biaya</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($maintenances as $maintenance)
                <tr>
                    <td>{{ $maintenance->code }}</td>
                    <td>{{ $maintenance->asset->name ?? '-' }}</td>
                    <td>{{ $maintenance->maintenance_date ? date('d/m/Y', strtotime($maintenance->maintenance_date)) : '-' }}
                    </td>
                    <td>{{ $maintenanceTypes[$maintenance->maintenance_type] ?? $maintenance->maintenance_type }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($maintenance->description, 30) }}</td>
                    <td>{{ $maintenance->vendor->name ?? '-' }}</td>
                    <td class="number-cell">{{ number_format($maintenance->total_cost, 2) }}</td>
                    <td class="status-{{ $maintenance->status }}">
                        {{ $statusOptions[$maintenance->status] ?? $maintenance->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>