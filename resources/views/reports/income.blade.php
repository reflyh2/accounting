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
   <h1 class="text-center">Laba Rugi</h1>
   <p class="text-center">{{ date('d/m/Y', strtotime($filters['start_date'])) }} s/d {{ date('d/m/Y', strtotime($filters['end_date'])) }}</p>
   
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
            <th class="text-right">Tahun Berjalan</th>
         </tr>
      </thead>
      <tbody>
         <!-- Revenue Section -->
         <tr class="section-header">
            <td colspan="4">PENDAPATAN</td>
         </tr>
         @foreach($reportData['revenue']['revenue']['accounts'] as $account)
            <tr>
               <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-2' : 'indent-1' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['ytd'], 0, ',', '.') }}</td>
            </tr>
         @endforeach
         @foreach($reportData['revenue']['cogs']['accounts'] as $account)
            <tr>
               <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-2' : 'indent-1' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['ytd'], 0, ',', '.') }}</td>
            </tr>
         @endforeach
         <tr class="section-header">
            <td>Laba Kotor</td>
            <td class="text-right">{{ number_format($reportData['revenue']['gross_profit']['previous'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['revenue']['gross_profit']['current'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['revenue']['gross_profit']['ytd'], 0, ',', '.') }}</td>
         </tr>

         <!-- Cash Costs Section -->
         <tr class="section-header">
            <td colspan="4">BIAYA KAS</td>
         </tr>
         <tr class="subsection-header">
            <td class="indent-1" colspan="4">Biaya Operasional:</td>
         </tr>
         @foreach($reportData['cash_costs']['operational']['accounts'] as $account)
            <tr>
               <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 2 ? 'indent-3' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['ytd'], 0, ',', '.') }}</td>
            </tr>
         @endforeach
         <tr class="font-bold">
            <td class="indent-1">Total Biaya Kas Operasional</td>
            <td class="text-right">{{ number_format($reportData['cash_costs']['operational']['total']['previous'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['cash_costs']['operational']['total']['current'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['cash_costs']['operational']['total']['ytd'], 0, ',', '.') }}</td>
         </tr>

         <!-- Non-operational Section -->
         <tr class="subsection-header">
            <td class="indent-1" colspan="4">Biaya (Pendapatan) Non-operasional:</td>
         </tr>
         @foreach($reportData['cash_costs']['non_operational']['other_income'] as $account)
            <tr>
               <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-3' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format(($account['balances']['previous'] > 0 ? '-' : '') . $account['balances']['previous'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format(($account['balances']['current'] > 0 ? '-' : '') . $account['balances']['current'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format(($account['balances']['ytd'] > 0 ? '-' : '') . $account['balances']['ytd'], 0, ',', '.') }}</td>
            </tr>
         @endforeach
         @foreach($reportData['cash_costs']['non_operational']['other_expenses'] as $account)
            <tr>
               <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 1 ? 'indent-3' : 'indent-2' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['ytd'], 0, ',', '.') }}</td>
            </tr>
         @endforeach
         <tr class="font-bold">
            <td class="indent-1">Total Biaya Kas Non-operasional</td>
            <td class="text-right">{{ number_format($reportData['cash_costs']['non_operational']['total']['previous'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['cash_costs']['non_operational']['total']['current'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['cash_costs']['non_operational']['total']['ytd'], 0, ',', '.') }}</td>
         </tr>
         <tr class="section-header">
            <td>Total Biaya Kas</td>
            <td class="text-right">{{ number_format($reportData['cash_costs']['total']['previous'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['cash_costs']['total']['current'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['cash_costs']['total']['ytd'], 0, ',', '.') }}</td>
         </tr>

         <!-- Non-cash Costs Section -->
         <tr class="section-header">
            <td colspan="4">BIAYA NON-KAS</td>
         </tr>
         @foreach($reportData['non_cash_costs']['depreciation'] as $account)
            <tr>
               <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 2 ? 'indent-2' : 'indent-1' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['ytd'], 0, ',', '.') }}</td>
            </tr>
         @endforeach
         @foreach($reportData['non_cash_costs']['amortization'] as $account)
            <tr>
               <td class="{{ $filters['report_type'] == 'detailed' && $account['account']['level'] === 2 ? 'indent-2' : 'indent-1' }} {{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? 'font-bold' : '' }}">{{ $account['account']['code'] }} - {{ $account['account']['name'] }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['previous'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['current'], 0, ',', '.') }}</td>
               <td class="text-right">{{ $filters['report_type'] == 'detailed' && $account['account']['is_parent'] ? '' : number_format($account['balances']['ytd'], 0, ',', '.') }}</td>
            </tr>
         @endforeach
         <tr class="section-header">
            <td>Total Biaya Non-kas</td>
            <td class="text-right">{{ number_format($reportData['non_cash_costs']['total']['previous'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['non_cash_costs']['total']['current'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['non_cash_costs']['total']['ytd'], 0, ',', '.') }}</td>
         </tr>

         <!-- Summary Section -->
         <tr class="section-header">
            <td>Total Biaya</td>
            <td class="text-right">{{ number_format($reportData['total_cost']['previous'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['total_cost']['current'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['total_cost']['ytd'], 0, ',', '.') }}</td>
         </tr>
         <tr class="section-header">
            <td>Laba Bersih</td>
            <td class="text-right">{{ number_format($reportData['net_profit']['previous'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['net_profit']['current'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($reportData['net_profit']['ytd'], 0, ',', '.') }}</td>
         </tr>
      </tbody>
   </table>
</body>
</html>