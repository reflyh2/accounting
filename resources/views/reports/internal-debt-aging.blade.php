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
        .section-title { font-size: 12pt; font-weight: bold; margin: 10px 0; }
        .text-green { color: #16a34a; }
        .text-red { color: #dc2626; }
    </style>
    <title>Umur Hutang/Piutang Internal</title>
    <meta charset="utf-8">
</head>
<body>
    <h1 class="text-center">Umur Hutang/Piutang Internal</h1>
    <p class="text-center">Per {{ date('d/m/Y', strtotime($filters['end_date'])) }}</p>
    
    @if(!empty($filters['company_id']) || empty($filters['branch_id']))
        <p class="text-center">
            Perusahaan: {{ !empty($filters['company_id']) ? \App\Models\Company::whereIn('id', $filters['company_id'])->pluck('name')->implode(', ') : (!empty($filters['branch_id']) ? '' : 'Semua Perusahaan') }}
        </p>
    @endif
    
    <p class="text-center">
        Cabang: {{ !empty($filters['branch_id']) ? \App\Models\Branch::whereIn('id', $filters['branch_id'])->pluck('name')->implode(', ') : 'Semua Cabang' }}
    </p>

    {{-- Receivables FIRST (green) --}}
    <div class="section-title">Piutang Internal</div>
    <table>
        <thead>
            <tr>
                <th>Cabang</th>
                <th class="text-right">Belum Jatuh Tempo</th>
                <th class="text-right">1-30 Hari</th>
                <th class="text-right">31-60 Hari</th>
                <th class="text-right">61-90 Hari</th>
                <th class="text-right">91+ Hari</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData['receivables']['rows'] as $row)
                <tr>
                    <td>
                        @php
                            $companyName = '';
                            if (is_array($row['branch'] ?? null)) {
                                $companyName = $row['branch']['branch_group']['company']['name'] ?? '';
                                $branchName = $row['branch']['name'] ?? '';
                            } else {
                                $companyName = $row['branch']->branchGroup->company->name ?? '';
                                $branchName = $row['branch']->name ?? '';
                            }
                        @endphp
                        {{ trim(($companyName ? $companyName . ' - ' : '') . $branchName) }}
                    </td>
                    <td class="text-right text-green">{{ number_format($row['not_yet_due'], 0, ',', '.') }}</td>
                    <td class="text-right text-green">{{ number_format($row['days_1_30'], 0, ',', '.') }}</td>
                    <td class="text-right text-green">{{ number_format($row['days_31_60'], 0, ',', '.') }}</td>
                    <td class="text-right text-green">{{ number_format($row['days_61_90'], 0, ',', '.') }}</td>
                    <td class="text-right text-green">{{ number_format($row['days_91_plus'], 0, ',', '.') }}</td>
                    <td class="text-right text-green">{{ number_format($row['total'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="font-bold">TOTAL</td>
                <td class="text-right font-bold text-green">{{ number_format($reportData['receivables']['totals']['not_yet_due'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-green">{{ number_format($reportData['receivables']['totals']['days_1_30'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-green">{{ number_format($reportData['receivables']['totals']['days_31_60'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-green">{{ number_format($reportData['receivables']['totals']['days_61_90'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-green">{{ number_format($reportData['receivables']['totals']['days_91_plus'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-green">{{ number_format($reportData['receivables']['totals']['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Payables SECOND (red) --}}
    <div class="section-title">Hutang Internal</div>
    <table>
        <thead>
            <tr>
                <th>Cabang</th>
                <th class="text-right">Belum Jatuh Tempo</th>
                <th class="text-right">1-30 Hari</th>
                <th class="text-right">31-60 Hari</th>
                <th class="text-right">61-90 Hari</th>
                <th class="text-right">91+ Hari</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData['payables']['rows'] as $row)
                <tr>
                    <td>
                        @php
                            $companyName = '';
                            if (is_array($row['branch'] ?? null)) {
                                $companyName = $row['branch']['branch_group']['company']['name'] ?? '';
                                $branchName = $row['branch']['name'] ?? '';
                            } else {
                                $companyName = $row['branch']->branchGroup->company->name ?? '';
                                $branchName = $row['branch']->name ?? '';
                            }
                        @endphp
                        {{ trim(($companyName ? $companyName . ' - ' : '') . $branchName) }}
                    </td>
                    <td class="text-right text-red">{{ number_format($row['not_yet_due'], 0, ',', '.') }}</td>
                    <td class="text-right text-red">{{ number_format($row['days_1_30'], 0, ',', '.') }}</td>
                    <td class="text-right text-red">{{ number_format($row['days_31_60'], 0, ',', '.') }}</td>
                    <td class="text-right text-red">{{ number_format($row['days_61_90'], 0, ',', '.') }}</td>
                    <td class="text-right text-red">{{ number_format($row['days_91_plus'], 0, ',', '.') }}</td>
                    <td class="text-right text-red">{{ number_format($row['total'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="font-bold">TOTAL</td>
                <td class="text-right font-bold text-red">{{ number_format($reportData['payables']['totals']['not_yet_due'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-red">{{ number_format($reportData['payables']['totals']['days_1_30'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-red">{{ number_format($reportData['payables']['totals']['days_31_60'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-red">{{ number_format($reportData['payables']['totals']['days_61_90'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-red">{{ number_format($reportData['payables']['totals']['days_91_plus'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold text-red">{{ number_format($reportData['payables']['totals']['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Combined --}}
    <div class="section-title">Gabungan (Piutang - Hutang)</div>
    <table>
        <thead>
            <tr>
                <th>Cabang</th>
                <th class="text-right">Belum Jatuh Tempo</th>
                <th class="text-right">1-30 Hari</th>
                <th class="text-right">31-60 Hari</th>
                <th class="text-right">61-90 Hari</th>
                <th class="text-right">91+ Hari</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData['combined']['rows'] as $row)
                <tr>
                    <td>
                        @php
                            $companyName = '';
                            if (is_array($row['branch'] ?? null)) {
                                $companyName = $row['branch']['branch_group']['company']['name'] ?? '';
                                $branchName = $row['branch']['name'] ?? '';
                            } else {
                                $companyName = $row['branch']->branchGroup->company->name ?? '';
                                $branchName = $row['branch']->name ?? '';
                            }
                        @endphp
                        {{ trim(($companyName ? $companyName . ' - ' : '') . $branchName) }}
                    </td>
                    <td class="text-right {{ ($row['not_yet_due'] ?? 0) > 0 ? 'text-green' : (($row['not_yet_due'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($row['not_yet_due'], 0, ',', '.') }}</td>
                    <td class="text-right {{ ($row['days_1_30'] ?? 0) > 0 ? 'text-green' : (($row['days_1_30'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($row['days_1_30'], 0, ',', '.') }}</td>
                    <td class="text-right {{ ($row['days_31_60'] ?? 0) > 0 ? 'text-green' : (($row['days_31_60'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($row['days_31_60'], 0, ',', '.') }}</td>
                    <td class="text-right {{ ($row['days_61_90'] ?? 0) > 0 ? 'text-green' : (($row['days_61_90'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($row['days_61_90'], 0, ',', '.') }}</td>
                    <td class="text-right {{ ($row['days_91_plus'] ?? 0) > 0 ? 'text-green' : (($row['days_91_plus'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($row['days_91_plus'], 0, ',', '.') }}</td>
                    <td class="text-right {{ ($row['total'] ?? 0) > 0 ? 'text-green' : (($row['total'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($row['total'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="font-bold">TOTAL</td>
                <td class="text-right font-bold {{ ($reportData['combined']['totals']['not_yet_due'] ?? 0) > 0 ? 'text-green' : (($reportData['combined']['totals']['not_yet_due'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($reportData['combined']['totals']['not_yet_due'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold {{ ($reportData['combined']['totals']['days_1_30'] ?? 0) > 0 ? 'text-green' : (($reportData['combined']['totals']['days_1_30'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($reportData['combined']['totals']['days_1_30'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold {{ ($reportData['combined']['totals']['days_31_60'] ?? 0) > 0 ? 'text-green' : (($reportData['combined']['totals']['days_31_60'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($reportData['combined']['totals']['days_31_60'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold {{ ($reportData['combined']['totals']['days_61_90'] ?? 0) > 0 ? 'text-green' : (($reportData['combined']['totals']['days_61_90'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($reportData['combined']['totals']['days_61_90'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold {{ ($reportData['combined']['totals']['days_91_plus'] ?? 0) > 0 ? 'text-green' : (($reportData['combined']['totals']['days_91_plus'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($reportData['combined']['totals']['days_91_plus'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right font-bold {{ ($reportData['combined']['totals']['total'] ?? 0) > 0 ? 'text-green' : (($reportData['combined']['totals']['total'] ?? 0) < 0 ? 'text-red' : '') }}">{{ number_format($reportData['combined']['totals']['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>


