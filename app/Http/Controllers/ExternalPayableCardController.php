<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Partner;
use App\Models\ExternalDebt;
use App\Models\ExternalDebtPaymentDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class ExternalPayableCardController extends Controller
{
   public function index(Request $request)
   {
      $filters = $request->all();
      if (empty($filters['start_date'])) {
         $filters['start_date'] = date('Y-m-01');
      }
      if (empty($filters['end_date'])) {
         $filters['end_date'] = date('Y-m-d');
      }

      $companies = Company::orderBy('name', 'asc')->get();

      $branchQuery = Branch::query();
      if (!empty($filters['company_id'])) {
         $branchQuery->whereHas('branchGroup', function ($q) use ($filters) {
               $q->whereIn('company_id', $filters['company_id']);
         });
      }
      $branches = $branchQuery->orderBy('name', 'asc')->get();

      $partners = Partner::orderBy('name', 'asc')->get();

      $cardData = [];
      if (!empty($filters['partner_id'])) {
         $cardData = $this->getCardData($filters);
      }

      return Inertia::render('Reports/ExternalPayableCard', [
         'companies' => $companies,
         'branches' => $branches,
         'partners' => $partners,
         'filters' => $filters,
         'cardData' => $cardData,
      ]);
   }

   private function getCardData(array $filters): array
   {
      $startDate = Carbon::parse($filters['start_date'])->startOfDay();
      $endDate = Carbon::parse($filters['end_date'])->endOfDay();
      $openingCutoff = $startDate->copy()->subDay()->endOfDay();

      $partnerIds = (array) ($filters['partner_id'] ?? []);
      if (empty($partnerIds)) {
         return [];
      }

      // Opening balances per partner: debts - effective payments up to openingCutoff
      $openingDebts = ExternalDebt::query()
         ->where('type', 'payable')
         ->whereIn('partner_id', $partnerIds)
         ->when(!empty($filters['company_id']), function ($q) use ($filters) {
               $q->whereHas('branch.branchGroup', function ($bq) use ($filters) {
                  $bq->whereIn('company_id', $filters['company_id']);
               });
         })
         ->when(!empty($filters['branch_id']), function ($q) use ($filters) {
               $q->whereIn('branch_id', $filters['branch_id']);
         })
         ->whereDate('issue_date', '<=', $openingCutoff->toDateString())
         ->select('partner_id', DB::raw('SUM(primary_currency_amount) as total'))
         ->groupBy('partner_id')
         ->pluck('total', 'partner_id');

      $openingPayments = ExternalDebtPaymentDetail::query()
         ->join('external_debts as ed', 'ed.id', '=', 'external_debt_payment_details.external_debt_id')
         ->join('external_debt_payments as edp', 'edp.id', '=', 'external_debt_payment_details.external_debt_payment_id')
         ->where('ed.type', 'payable')
         ->whereIn('ed.partner_id', $partnerIds)
         ->when(!empty($filters['company_id']), function ($q) use ($filters) {
               $q->whereHas('ed.branch.branchGroup', function ($bq) use ($filters) {
                  $bq->whereIn('company_id', $filters['company_id']);
               });
         })
         ->when(!empty($filters['branch_id']), function ($q) use ($filters) {
               $q->whereIn('ed.branch_id', $filters['branch_id']);
         })
         ->whereNull('external_debt_payment_details.deleted_at')
         ->whereNull('edp.deleted_at')
         ->where(function ($q) use ($openingCutoff) {
               $q->where(function ($q1) use ($openingCutoff) {
                  $q1->whereIn('edp.payment_method', ['cek', 'giro'])
                     ->whereNotNull('edp.withdrawal_date')
                     ->whereDate('edp.withdrawal_date', '<=', $openingCutoff->toDateString());
               })->orWhere(function ($q2) use ($openingCutoff) {
                  $q2->whereIn('edp.payment_method', ['cash', 'transfer'])
                     ->whereDate('edp.payment_date', '<=', $openingCutoff->toDateString());
               });
         })
         ->select('ed.partner_id', DB::raw('SUM(external_debt_payment_details.primary_currency_amount) as total'))
         ->groupBy('ed.partner_id')
         ->pluck('total', 'ed.partner_id');

      // Movements in range - additions (do not group; list each document)
      $additions = ExternalDebt::query()
         ->where('type', 'payable')
         ->whereIn('partner_id', $partnerIds)
         ->when(!empty($filters['company_id']), function ($q) use ($filters) {
               $q->whereHas('branch.branchGroup', function ($bq) use ($filters) {
                  $bq->whereIn('company_id', $filters['company_id']);
               });
         })
         ->when(!empty($filters['branch_id']), function ($q) use ($filters) {
               $q->whereIn('branch_id', $filters['branch_id']);
         })
         ->whereBetween('issue_date', [$startDate->toDateString(), $endDate->toDateString()])
         ->select('id', 'number', 'partner_id', 'issue_date as date', 'primary_currency_amount as amount')
         ->get();

      // Movements in range - payments (effective date; do not group; list each payment detail)
      $payments = ExternalDebtPaymentDetail::query()
         ->join('external_debts as ed', 'ed.id', '=', 'external_debt_payment_details.external_debt_id')
         ->join('external_debt_payments as edp', 'edp.id', '=', 'external_debt_payment_details.external_debt_payment_id')
         ->where('ed.type', 'payable')
         ->whereIn('ed.partner_id', $partnerIds)
         ->when(!empty($filters['company_id']), function ($q) use ($filters) {
               $q->whereHas('ed.branch.branchGroup', function ($bq) use ($filters) {
                  $bq->whereIn('company_id', $filters['company_id']);
               });
         })
         ->when(!empty($filters['branch_id']), function ($q) use ($filters) {
               $q->whereIn('ed.branch_id', $filters['branch_id']);
         })
         ->whereNull('external_debt_payment_details.deleted_at')
         ->whereNull('edp.deleted_at')
         ->where(function ($q) use ($startDate, $endDate) {
               $q->where(function ($q1) use ($startDate, $endDate) {
                  $q1->whereIn('edp.payment_method', ['cek', 'giro'])
                     ->whereNotNull('edp.withdrawal_date')
                     ->whereDate('edp.withdrawal_date', '>=', $startDate->toDateString())
                     ->whereDate('edp.withdrawal_date', '<=', $endDate->toDateString());
               })->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->whereIn('edp.payment_method', ['cash', 'transfer'])
                     ->whereDate('edp.payment_date', '>=', $startDate->toDateString())
                     ->whereDate('edp.payment_date', '<=', $endDate->toDateString());
               });
         })
         ->select(
            'external_debt_payment_details.id',
            'ed.partner_id',
            'edp.id as payment_id',
            'edp.number',
            DB::raw("CASE WHEN edp.payment_method IN ('cek','giro') THEN edp.withdrawal_date ELSE edp.payment_date END as date"),
            DB::raw('external_debt_payment_details.primary_currency_amount as amount')
         )
         ->get();

      $partners = Partner::whereIn('id', $partnerIds)->orderBy('name', 'asc')->get()->keyBy('id');

      $result = [];
      foreach ($partnerIds as $pid) {
         if (!isset($partners[$pid])) {
               continue;
         }

         $opening = (float) ($openingDebts[$pid] ?? 0) - (float) ($openingPayments[$pid] ?? 0);
         $events = [];

         $additionTotal = 0.0;
         $paymentTotal = 0.0;

         foreach ($additions->where('partner_id', $pid) as $a) {
               $events[] = [
                  'date' => $a->date,
                  'addition' => (float) $a->amount,
                  'payment' => 0.0,
                  'doc_type' => 'addition',
                  'doc_id' => $a->id,
                  'doc_number' => $a->number,
               ];
               $additionTotal += (float) $a->amount;
         }
         foreach ($payments->where('partner_id', $pid) as $p) {
               $events[] = [
                  'date' => $p->date,
                  'addition' => 0.0,
                  'payment' => (float) $p->amount,
                  'doc_type' => 'payment',
                  'doc_id' => $p->payment_id,
                  'doc_number' => $p->number,
               ];
               $paymentTotal += (float) $p->amount;
         }

         usort($events, function ($x, $y) {
               $cmp = strcmp($x['date'], $y['date']);
               if ($cmp !== 0) return $cmp;
               // On same date, list additions before payments
               if ($x['addition'] !== $y['addition']) {
                  return $x['addition'] > 0 ? -1 : 1;
               }
               return 0;
         });

         // Build rows with running balance
         $rows = [];
         $balance = $opening;
         $rows[] = [
               'date' => $openingCutoff->toDateString(),
               'addition' => 0.0,
               'payment' => 0.0,
               'balance' => $balance,
               'is_opening' => true,
         ];
         foreach ($events as $e) {
               $balance = $balance + $e['addition'] - $e['payment'];
               $rows[] = [
                  'date' => $e['date'],
                  'addition' => $e['addition'],
                  'payment' => $e['payment'],
                  'balance' => $balance,
                  'is_opening' => false,
                  'doc_type' => $e['doc_type'] ?? null,
                  'doc_id' => $e['doc_id'] ?? null,
                  'doc_number' => $e['doc_number'] ?? null,
               ];
         }

         $totals = [
            'additions' => $additionTotal,
            'payments' => $paymentTotal,
            'ending_balance' => $balance,
         ];

         $result[] = [
               'partner' => $partners[$pid],
               'rows' => $rows,
               'totals' => $totals,
         ];
      }

      return $result;
   }

   public function download(Request $request)
   {
      $filters = $request->all();
      if (empty($filters['start_date'])) {
         $filters['start_date'] = date('Y-m-01');
      }
      if (empty($filters['end_date'])) {
         $filters['end_date'] = date('Y-m-d');
      }
      $cardData = [];
      if (!empty($filters['partner_id'])) {
         $cardData = $this->getCardData($filters);
      }

      if (($request->format ?? 'xlsx') === 'pdf') {
         return $this->downloadPdf($cardData, $filters);
      }
      return $this->downloadExcel($cardData, $filters);
   }

   private function downloadPdf(array $cardData, array $filters)
   {
      $pdf = Pdf::loadView('reports.external-payable-card', [
         'cardData' => $cardData,
         'filters' => $filters,
      ]);
      return $pdf->download('kartu-hutang-' . date('Y-m-d') . '.pdf');
   }

   private function downloadExcel(array $cardData, array $filters)
   {
      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();

      // Title
      $sheet->setCellValue('A1', 'Kartu Hutang');
      $sheet->mergeCells('A1:E1');
      $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A1')->getFont()->setBold(true);
      $sheet->getStyle('A1')->getFont()->setSize(16);

      // Date range
      $sheet->setCellValue('A2', date('d/m/Y', strtotime($filters['start_date'])) . ' s/d ' . date('d/m/Y', strtotime($filters['end_date'])));
      $sheet->mergeCells('A2:E2');
      $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A2')->getFont()->setSize(12);

      $row = 4;
      foreach ($cardData as $card) {
         // Partner header
         $sheet->setCellValue('A' . $row, $card['partner']['name']);
         $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         $row++;

         // Headers
         $sheet->setCellValue('A' . $row, 'Tanggal');
         $sheet->setCellValue('B' . $row, 'Dokumen');
         $sheet->setCellValue('C' . $row, 'Penambahan');
         $sheet->setCellValue('D' . $row, 'Pembayaran');
         $sheet->setCellValue('E' . $row, 'Saldo');
         $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
         $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
         $row++;

         foreach ($card['rows'] as $r) {
               $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($r['date'])));
               $sheet->setCellValue('B' . $row, !empty($r['is_opening']) ? '-' : ($r['doc_number'] ?? ''));
               $sheet->setCellValue('C' . $row, $r['addition']);
               $sheet->setCellValue('D' . $row, $r['payment']);
               $sheet->setCellValue('E' . $row, $r['balance']);
               $row++;
         }

         // Totals row
         if (!empty($card['totals'])) {
            $sheet->setCellValue('A' . $row, 'TOTAL');
            $sheet->setCellValue('C' . $row, $card['totals']['additions'] ?? 0);
            $sheet->setCellValue('D' . $row, $card['totals']['payments'] ?? 0);
            $sheet->setCellValue('E' . $row, $card['totals']['ending_balance'] ?? 0);
            $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
            $row++;
         }

         $row++;
      }

      // Number formatting
      $sheet->getStyle('C4:E' . $row)->getNumberFormat()->setFormatCode('#,##0');

      $writer = new Xlsx($spreadsheet);
      $filename = 'kartu-hutang-' . date('Y-m-d') . '.xlsx';

      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment;filename="' . $filename . '"');
      header('Cache-Control: max-age=0');

      $writer->save('php://output');
      exit;
   }
}


