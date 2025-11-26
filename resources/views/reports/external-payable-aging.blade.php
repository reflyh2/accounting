<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
        th { background-color: #f5f5f5; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
    <title>Umur Hutang Luar</title>
    <meta charset="utf-8">
</head>
<body>
    <h1 class="text-center">Umur Hutang Luar</h1>
    <p class="text-center">Per {{ date('d/m/Y', strtotime($filters['end_date'])) }}</p>
    
    @if(!empty($filters['company_id']) || empty($filters['branch_id']))
        <p class="text-center">
            Perusahaan: {{ !empty($filters['company_id']) ? \App\Models\Company::whereIn('id', $filters['company_id'])->pluck('name')->implode(', ') : (!empty($filters['branch_id']) ? '' : 'Semua Perusahaan') }}
        </p>
    @endif
    
    <p class="text-center">
        Cabang: {{ !empty($filters['branch_id']) ? \App\Models\Branch::whereIn('id', $filters['branch_id'])->pluck('name')->implode(', ') : 'Semua Cabang' }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Partner</th>
                <th class="text-right">Belum Jatuh Tempo</th>
                <th class="text-right">1-30 Hari</th>
                <th class="text-right">31-60 Hari</th>
                <th class="text-right">61-90 Hari</th>
                <th class="text-right">91+ Hari</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData['rows'] as $row)
                <tr>
                    <td>{{ $row['partner']['name'] }}</td>
                    <td class="text-right">{{ number_format($row['not_yet_due'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['days_1_30'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['days_31_60'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['days_61_90'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['days_91_plus'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['total'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="font-bold">TOTAL</td>
                <td class="text-right font-bold">{{ number_format($reportData['totals']['not_yet_due'], 0, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format($reportData['totals']['days_1_30'], 0, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format($reportData['totals']['days_31_60'], 0, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format($reportData['totals']['days_61_90'], 0, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format($reportData['totals']['days_91_plus'], 0, ',', '.') }}</td>
                <td class="text-right font-bold">{{ number_format($reportData['totals']['total'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>


