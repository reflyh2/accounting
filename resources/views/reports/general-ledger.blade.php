<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        .text-right { text-align: right; }
        .account-header { margin: 20px 0 10px; font-weight: bold; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .my-0 { margin-top: 0; margin-bottom: 0; }
        .my-2 { margin-top: 0.5em; margin-bottom: 0.5em; }
        .mt-2 { margin-top: 0.5em; }
        .mb-0 { margin-bottom: 0; }
    </style>
</head>
<body>
   <h1 class="text-center my-0">Buku Besar</h1>
   <p class="text-center my-0">{{ date('d/m/Y', strtotime($filters['start_date'])) }} s/d {{ date('d/m/Y', strtotime($filters['end_date'])) }}</p>
   
   @if(!empty($filters['company_id']) || empty($filters['branch_id']))
      <p class="text-center mt-2 mb-0">
            Perusahaan: {{ !empty($filters['company_id']) ? \App\Models\Company::whereIn('id', $filters['company_id'])->pluck('name')->implode(', ') : (!empty($filters['branch_id']) ? '' : 'Semua Perusahaan') }}
      </p>
   @endif
    
   <p class="text-center my-0">
      Cabang: {{ !empty($filters['branch_id']) ? \App\Models\Branch::whereIn('id', $filters['branch_id'])->pluck('name')->implode(', ') : 'Semua Cabang' }}
   </p>

   @foreach($ledgerData as $data)
      <div class="account-header">{{ $data['account']['code'] }} - {{ $data['account']['name'] }}</div>
      <table>
         <thead>
               <tr>
                  <th>Tanggal</th>
                  <th>No. Jurnal</th>
                  <th>Keterangan</th>
                  <th class="text-right">Debet</th>
                  <th class="text-right">Kredit</th>
                  <th class="text-right">Saldo</th>
               </tr>
         </thead>
         <tbody>
               <tr>
                  <td colspan="5" class="font-bold">Saldo Awal</td>
                  <td class="text-right font-bold">{{ number_format($data['opening_balance'], 0, ',', '.') }}</td>
               </tr>

               @php
                  $balance = $data['opening_balance'];
               @endphp

               @foreach($data['mutations'] as $mutation)
                  @php
                     if ($data['account']['balance_type'] === 'debit') {
                           $balance += $mutation['primary_currency_debit'] - $mutation['primary_currency_credit'];
                     } else {
                           $balance += $mutation['primary_currency_credit'] - $mutation['primary_currency_debit'];
                     }
                  @endphp
                  <tr>
                     <td>{{ date('d/m/Y', strtotime($mutation['journal']['date'])) }}</td>
                     <td>{{ $mutation['journal']['journal_number'] }}</td>
                     <td>{{ $mutation['journal']['description'] }}</td>
                     <td class="text-right">
                           {{ $mutation['primary_currency_debit'] > 0 ? number_format($mutation['primary_currency_debit'], 0, ',', '.') : '-' }}
                     </td>
                     <td class="text-right">
                           {{ $mutation['primary_currency_credit'] > 0 ? number_format($mutation['primary_currency_credit'], 0, ',', '.') : '-' }}
                     </td>
                     <td class="text-right">{{ number_format($balance, 0, ',', '.') }}</td>
                  </tr>
               @endforeach

               <tr>
                  <td colspan="3" class="font-bold">Saldo Akhir</td>
                  <td class="text-right font-bold">
                     {{ number_format(collect($data['mutations'])->sum('primary_currency_debit'), 0, ',', '.') }}
                  </td>
                  <td class="text-right font-bold">
                     {{ number_format(collect($data['mutations'])->sum('primary_currency_credit'), 0, ',', '.') }}
                  </td>
                  <td class="text-right font-bold">
                     {{ number_format($data['ending_balance'], 0, ',', '.') }}
                  </td>
               </tr>
         </tbody>
      </table>
   @endforeach
</body>
</html>