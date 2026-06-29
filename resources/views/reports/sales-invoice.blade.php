<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px;margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 11px; }
        th { background-color: #f5f5f5; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .my-0 { margin-top: 0; margin-bottom: 0; }
        .mt-2 { margin-top: 0.5em; }
        .mb-0 { margin-bottom: 0; }
        .text-green { color: #16a34a; }
        .text-red { color: #dc2626; }
        .badge { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 500; }
        .badge-draft { background-color: #f3f4f6; color: #374151; }
        .badge-posted { background-color: #dbeafe; color: #1e40af; }
        .badge-partially_paid { background-color: #fef3c7; color: #92400e; }
        .badge-paid { background-color: #dcfce7; color: #166534; }
        .badge-canceled { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
   <h1 class="text-center my-0">Laporan Faktur Penjualan</h1>
   <p class="text-center my-0">{{ date('d/m/Y', strtotime($filters['start_date'])) }} s/d {{ date('d/m/Y', strtotime($filters['end_date'])) }}</p>

   @if(!empty($filters['company_id']))
      <p class="text-center mt-2 mb-0">
            Perusahaan: {{ \App\Models\Company::whereIn('id', (array) $filters['company_id'])->pluck('name')->implode(', ') }}
      </p>
   @endif

   @if(!empty($filters['branch_id']))
      <p class="text-center my-0">
         Cabang: {{ \App\Models\Branch::whereIn('id', (array) $filters['branch_id'])->pluck('name')->implode(', ') }}
      </p>
   @endif

   <table>
      <thead>
            <tr>
               <th>No. Invoice</th>
               <th>Tanggal</th>
               <th>Customer</th>
               <th>Salesperson</th>
               <th class="text-right">Revenue</th>
               <th class="text-right">COGS</th>
               <th class="text-right">Gross Profit</th>
               <th class="text-right">Margin</th>
               <th class="text-center">Status</th>
            </tr>
      </thead>
      <tbody>
            @foreach($invoices as $invoice)
               <tr>
                  <td>{{ $invoice->invoice_number }}</td>
                  <td>{{ date('d/m/Y', strtotime($invoice->invoice_date)) }}</td>
                  <td>{{ $invoice->partner?->name ?? '-' }}</td>
                  <td>{{ $invoice->salesPerson?->name ?? '-' }}</td>
                  <td class="text-right">{{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
                  <td class="text-right">{{ number_format($invoice->cogs, 0, ',', '.') }}</td>
                  <td class="text-right {{ $invoice->gross_profit >= 0 ? 'text-green' : 'text-red' }}">
                     {{ number_format($invoice->gross_profit, 0, ',', '.') }}
                  </td>
                  <td class="text-right {{ $invoice->margin_percentage >= 0 ? 'text-green' : 'text-red' }}">
                     {{ number_format($invoice->margin_percentage, 1, ',', '.') }}%
                  </td>
                  <td class="text-center">
                     <span class="badge badge-{{ $invoice->status }}">
                        {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                     </span>
                  </td>
               </tr>
            @endforeach

            @php
               $totalRevenue = $invoices->sum('total_amount');
               $totalCogs = $invoices->sum('cogs');
               $totalProfit = $invoices->sum('gross_profit');
               $marginPct = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0;
            @endphp

            <tr>
               <td colspan="4" class="font-bold">TOTAL</td>
               <td class="text-right font-bold">{{ number_format($totalRevenue, 0, ',', '.') }}</td>
               <td class="text-right font-bold">{{ number_format($totalCogs, 0, ',', '.') }}</td>
               <td class="text-right font-bold {{ $totalProfit >= 0 ? 'text-green' : 'text-red' }}">
                  {{ number_format($totalProfit, 0, ',', '.') }}
               </td>
               <td class="text-right font-bold {{ $marginPct >= 0 ? 'text-green' : 'text-red' }}">
                  {{ number_format($marginPct, 1, ',', '.') }}%
               </td>
               <td></td>
            </tr>
      </tbody>
   </table>
</body>
</html>
