<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Detail Pemeliharaan Aset - {{ $maintenance->code }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin-bottom: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            width: 30%;
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .number-cell {
            text-align: right;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-draft {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .status-completed {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-cancelled {
            background-color: #FEE2E2;
            color: #991B1B;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Detail Pemeliharaan Aset</h1>
        <p>{{ $maintenance->code }}</p>
        <p>Tanggal Cetak: {{ date('d/m/Y') }}</p>
    </div>

    <div class="section">
        <div class="section-title">Informasi Pemeliharaan</div>
        <table>
            <tr>
                <th>Kode Pemeliharaan</th>
                <td>{{ $maintenance->code }}</td>
            </tr>
            <tr>
                <th>Tanggal</th>
                <td>{{ $maintenance->maintenance_date ? date('d/m/Y', strtotime($maintenance->maintenance_date)) : '-' }}
                </td>
            </tr>
            <tr>
                <th>Jenis Pemeliharaan</th>
                <td>{{ $maintenanceTypes[$maintenance->maintenance_type] }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <span class="status-badge status-{{ $maintenance->status }}">
                        {{ $statusOptions[$maintenance->status] }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Aset & Lokasi</div>
        <table>
            <tr>
                <th>Aset</th>
                <td>{{ $maintenance->asset->code }} - {{ $maintenance->asset->name }}</td>
            </tr>
            <tr>
                <th>Perusahaan</th>
                <td>{{ $maintenance->company->name }}</td>
            </tr>
            <tr>
                <th>Cabang</th>
                <td>{{ $maintenance->branch->name }}</td>
            </tr>
            <tr>
                <th>Vendor</th>
                <td>{{ $maintenance->vendor ? $maintenance->vendor->name : '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Rincian Biaya</div>
        <table>
            <tr>
                <th>Biaya Tenaga Kerja</th>
                <td class="number-cell">{{ number_format($maintenance->labor_cost, 2) }}</td>
            </tr>
            <tr>
                <th>Biaya Suku Cadang</th>
                <td class="number-cell">{{ number_format($maintenance->parts_cost, 2) }}</td>
            </tr>
            <tr>
                <th>Biaya Eksternal</th>
                <td class="number-cell">{{ number_format($maintenance->external_cost, 2) }}</td>
            </tr>
            <tr>
                <th style="font-weight: bold;">Total Biaya</th>
                <td class="number-cell" style="font-weight: bold;">{{ number_format($maintenance->total_cost, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Deskripsi Pekerjaan</div>
        <p>{{ $maintenance->description ?: '-' }}</p>
    </div>

    @if($maintenance->notes)
        <div class="section">
            <div class="section-title">Catatan</div>
            <p>{{ $maintenance->notes }}</p>
        </div>
    @endif
</body>

</html>