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
  <title>Mutasi Piutang Luar</title>
  <meta charset="utf-8">
</head>
<body>
  <h1 class="text-center">Mutasi Piutang Luar</h1>
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
        <th>Partner</th>
        <th class="text-right">Saldo Awal</th>
        <th class="text-right">Penambahan</th>
        <th class="text-right">Pembayaran</th>
        <th class="text-right">Saldo Akhir</th>
      </tr>
    </thead>
    <tbody>
      @foreach($reportData['rows'] as $row)
        <tr>
          <td>{{ $row['partner']['name'] }}</td>
          <td class="text-right">{{ number_format($row['opening'], 0, ',', '.') }}</td>
          <td class="text-right">{{ number_format($row['additions'], 0, ',', '.') }}</td>
          <td class="text-right">{{ number_format($row['payments'], 0, ',', '.') }}</td>
          <td class="text-right">{{ number_format($row['closing'], 0, ',', '.') }}</td>
        </tr>
      @endforeach
      <tr>
        <td class="font-bold">TOTAL</td>
        <td class="text-right font-bold">{{ number_format($reportData['totals']['opening'], 0, ',', '.') }}</td>
        <td class="text-right font-bold">{{ number_format($reportData['totals']['additions'], 0, ',', '.') }}</td>
        <td class="text-right font-bold">{{ number_format($reportData['totals']['payments'], 0, ',', '.') }}</td>
        <td class="text-right font-bold">{{ number_format($reportData['totals']['closing'], 0, ',', '.') }}</td>
      </tr>
    </tbody>
  </table>
</body>
</html>


