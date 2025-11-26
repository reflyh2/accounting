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
    .my-0 { margin-top: 0; margin-bottom: 0; }
    .my-2 { margin-top: 0.5em; margin-bottom: 0.5em; }
  </style>
  <meta charset="utf-8">
  <title>Kartu Hutang</title>
  </head>
<body>
  <h1 class="text-center my-0">Kartu Hutang</h1>
  <p class="text-center my-0">{{ date('d/m/Y', strtotime($filters['start_date'])) }} s/d {{ date('d/m/Y', strtotime($filters['end_date'])) }}</p>

  @if(!empty($filters['company_id']) || empty($filters['branch_id']))
    <p class="text-center my-2">
      Perusahaan: {{ !empty($filters['company_id']) ? \App\Models\Company::whereIn('id', $filters['company_id'])->pluck('name')->implode(', ') : (!empty($filters['branch_id']) ? '' : 'Semua Perusahaan') }}
    </p>
  @endif
  <p class="text-center my-0">
    Cabang: {{ !empty($filters['branch_id']) ? \App\Models\Branch::whereIn('id', $filters['branch_id'])->pluck('name')->implode(', ') : 'Semua Cabang' }}
  </p>

  @foreach($cardData as $card)
    <h3 class="my-2">{{ $card['partner']['name'] }}</h3>
    <table>
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Dokumen</th>
          <th class="text-right">Penambahan</th>
          <th class="text-right">Pembayaran</th>
          <th class="text-right">Saldo</th>
        </tr>
      </thead>
      <tbody>
        @foreach($card['rows'] as $row)
          <tr>
            <td>
              {{ date('d/m/Y', strtotime($row['date'])) }}
              @if(!empty($row['is_opening']))
                <span>(Saldo Awal)</span>
              @endif
            </td>
            <td>
              @if(!empty($row['is_opening']))
                -
              @elseif(!empty($row['doc_number']))
                {{ $row['doc_number'] }}
              @else
                -
              @endif
            </td>
            <td class="text-right">{{ $row['addition'] > 0 ? number_format($row['addition'], 0, ',', '.') : '-' }}</td>
            <td class="text-right">{{ $row['payment'] > 0 ? number_format($row['payment'], 0, ',', '.') : '-' }}</td>
            <td class="text-right">{{ number_format($row['balance'], 0, ',', '.') }}</td>
          </tr>
        @endforeach
        @if(!empty($card['totals']))
          <tr>
            <td class="font-bold">TOTAL</td>
            <td class="font-bold">-</td>
            <td class="text-right font-bold">{{ number_format($card['totals']['additions'] ?? 0, 0, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($card['totals']['payments'] ?? 0, 0, ',', '.') }}</td>
            <td class="text-right font-bold">{{ number_format($card['totals']['ending_balance'] ?? 0, 0, ',', '.') }}</td>
          </tr>
        @endif
      </tbody>
    </table>
  @endforeach
</body>
</html>


