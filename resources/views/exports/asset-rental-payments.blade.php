<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daftar Pembayaran Sewa Aset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <h1>Daftar Pembayaran Sewa Aset</h1>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Aset</th>
                <th>Perusahaan</th>
                <th>Grup Cabang</th>
                <th>Cabang</th>
                <th>Tanggal Bayar</th>
                <th>Jumlah</th>
                <th>Periode Mulai</th>
                <th>Periode Selesai</th>
                <th>Status</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->id }}</td>
                    <td>{{ $payment->asset->name }}</td>
                    <td>{{ $payment->asset->branch->branchGroup->company->name }}</td>
                    <td>{{ $payment->asset->branch->branchGroup->name }}</td>
                    <td>{{ $payment->asset->branch->name }}</td>
                    <td>{{ $payment->payment_date ? date('d/m/Y', strtotime($payment->payment_date)) : '-' }}</td>
                    <td class="text-right">{{ number_format($payment->amount, 2, ',', '.') }}</td>
                    <td>{{ date('d/m/Y', strtotime($payment->period_start)) }}</td>
                    <td>{{ date('d/m/Y', strtotime($payment->period_end)) }}</td>
                    <td>{{ match($payment->status) {
                        'pending' => 'Menunggu',
                        'paid' => 'Lunas',
                        'overdue' => 'Terlambat',
                        default => $payment->status
                    } }}</td>
                    <td>{{ $payment->notes }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html> 