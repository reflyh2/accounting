<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
        th { background-color: #f5f5f5; }
        .text-right { text-align: right; }
        .section-header { background-color: #f8f9fa; font-weight: bold; }
        .subsection-header { background-color: #ffffff; font-weight: bold; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .indent-1 { padding-left: 20px; }
        .indent-2 { padding-left: 40px; }
        .indent-3 { padding-left: 60px; }
    </style>
</head>
<body>
    <h1 class="text-center">Neraca</h1>
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
                <th>Keterangan</th>
                <th class="text-right">Bulan Lalu</th>
                <th class="text-right">Bulan Ini</th>
            </tr>
        </thead>
        <tbody>
            <!-- Assets Section -->
            <tr class="section-header">
                <td colspan="3">AKTIVA</td>
            </tr>
            
            <!-- Cash & Bank -->
            @foreach($reportData['assets']['cash_bank'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 2 ? 'indent-1' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Accounts Receivable -->
            @foreach($reportData['assets']['receivable'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-1' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Inventory -->
            @foreach($reportData['assets']['inventory'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-1' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Other Current Assets -->
            @foreach($reportData['assets']['other_current'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-1' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Fixed Assets -->
            @foreach($reportData['assets']['fixed_asset'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-1' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Accumulated Depreciation -->
            @foreach($reportData['assets']['accumulated_depreciation'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-1' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Other Assets -->
            @foreach($reportData['assets']['other_asset'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-1' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Total Assets -->
            <tr class="section-header">
                <td>Total Aktiva</td>
                <td class="text-right">{{ number_format($reportData['total_assets']['previous'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($reportData['total_assets']['current'], 0, ',', '.') }}</td>
            </tr>

            <tr class="section-header">
                <td colspan="3">&nbsp;</td>
            </tr>

            <!-- Liabilities Section -->
            <tr class="section-header">
                <td colspan="3">PASIVA</td>
            </tr>

            <tr class="subsection-header">
                <td class="indent-1">Kewajiban</td>
                <td></td>
                <td></td>
            </tr>

            <!-- Accounts Payable -->
            @foreach($reportData['liabilities']['payable'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 2 ? 'indent-2' : 'indent-3' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Other Payables -->
            @foreach($reportData['liabilities']['other_payable'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-2' : 'indent-3' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Short-term Liabilities -->
            @foreach($reportData['liabilities']['short_term'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-2' : 'indent-3' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Long-term Liabilities -->
            @foreach($reportData['liabilities']['long_term'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-2' : 'indent-3' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Total Liabilities -->
            <tr class="subsection-header">
                <td class="indent-1">Total Kewajiban</td>
                <td class="text-right">{{ number_format($reportData['liabilities']['total']['previous'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($reportData['liabilities']['total']['current'], 0, ',', '.') }}</td>
            </tr>

            <!-- Equity Section -->
            @foreach($reportData['equity']['accounts'] as $account)
                <tr>
                    <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-2' : 'indent-3' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <!-- Total Equity -->
            <tr class="subsection-header">
                <td class="indent-1">Total Modal</td>
                <td class="text-right">{{ number_format($reportData['equity']['total']['previous'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($reportData['equity']['total']['current'], 0, ',', '.') }}</td>
            </tr>

            <!-- Total Liabilities & Equity -->
            <tr class="section-header">
                <td>Total Pasiva</td>
                <td class="text-right">{{ number_format($reportData['total_liabilities_equity']['previous'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($reportData['total_liabilities_equity']['current'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
