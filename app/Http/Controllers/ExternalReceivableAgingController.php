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

class ExternalReceivableAgingController extends Controller
{
   public function index(Request $request)
   {
      $filters = $request->all();
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

      $reportData = $this->getReportData($filters);

      return Inertia::render('Reports/ExternalReceivableAging', [
         'companies' => $companies,
         'branches' => $branches,
         'filters' => $filters,
         'reportData' => $reportData,
      ]);
   }

   private function getReportData(array $filters): array
   {
      $endDate = $filters['end_date'] ?? date('Y-m-d');
      $end = Carbon::parse($endDate)->endOfDay();

      $debtQuery = ExternalDebt::query()
         ->select([
               'external_debts.id',
               'external_debts.partner_id',
               'external_debts.branch_id',
               'external_debts.due_date',
               'external_debts.primary_currency_amount',
               'external_debts.issue_date',
         ])
         ->where('type', 'receivable')
         ->whereDate('issue_date', '<=', $end->toDateString());

      if (!empty($filters['company_id'])) {
         $debtQuery->whereHas('branch.branchGroup', function ($q) use ($filters) {
               $q->whereIn('company_id', $filters['company_id']);
         });
      }
      if (!empty($filters['branch_id'])) {
         $debtQuery->whereIn('branch_id', $filters['branch_id']);
      }

      $debts = $debtQuery->get();
      if ($debts->isEmpty()) {
         return [
            'rows' => [],
            'totals' => [
               'not_yet_due' => 0,
               'days_1_30' => 0,
               'days_31_60' => 0,
               'days_61_90' => 0,
               'days_91_plus' => 0,
               'total' => 0,
            ],
         ];
      }

      $debtIds = $debts->pluck('id')->all();

      $paidByDebt = ExternalDebtPaymentDetail::query()
         ->select([
            'external_debt_payment_details.external_debt_id',
            DB::raw('SUM(external_debt_payment_details.primary_currency_amount) as total_amount'),
         ])
         ->join('external_debt_payments as edp', 'edp.id', '=', 'external_debt_payment_details.external_debt_payment_id')
         ->whereIn('external_debt_payment_details.external_debt_id', $debtIds)
         ->where('edp.type', 'receivable')
         ->where(function ($q) use ($end) {
            $q->where(function ($q1) use ($end) {
               $q1->whereIn('edp.payment_method', ['cek', 'giro'])
                  ->whereNotNull('edp.withdrawal_date')
                  ->whereDate('edp.withdrawal_date', '<=', $end->toDateString());
            })->orWhere(function ($q2) use ($end) {
               $q2->whereIn('edp.payment_method', ['cash', 'transfer'])
                  ->whereDate('edp.payment_date', '<=', $end->toDateString());
            });
         })
         ->whereNull('external_debt_payment_details.deleted_at')
         ->whereNull('edp.deleted_at')
         ->groupBy('external_debt_payment_details.external_debt_id')
         ->pluck('total_amount', 'external_debt_payment_details.external_debt_id');

      $partnerBuckets = [];

      foreach ($debts as $debt) {
         $paid = (float) ($paidByDebt[$debt->id] ?? 0);
         $outstanding = (float) $debt->primary_currency_amount - $paid;
         if ($outstanding <= 0.000001) {
            continue;
         }

         $bucket = 'not_yet_due';
         if (!empty($debt->due_date)) {
            $due = Carbon::parse($debt->due_date)->endOfDay();
            if ($due->lt($end)) {
               $days = $due->diffInDays($end);
               if ($days >= 1 && $days <= 30) {
                  $bucket = 'days_1_30';
               } elseif ($days >= 31 && $days <= 60) {
                  $bucket = 'days_31_60';
               } elseif ($days >= 61 && $days <= 90) {
                  $bucket = 'days_61_90';
               } else {
                  $bucket = 'days_91_plus';
               }
            }
         }

         if (!isset($partnerBuckets[$debt->partner_id])) {
            $partnerBuckets[$debt->partner_id] = [
               'not_yet_due' => 0,
               'days_1_30' => 0,
               'days_31_60' => 0,
               'days_61_90' => 0,
               'days_91_plus' => 0,
               'total' => 0,
            ];
         }
         $partnerBuckets[$debt->partner_id][$bucket] += $outstanding;
         $partnerBuckets[$debt->partner_id]['total'] += $outstanding;
      }

      $partnerIds = array_keys($partnerBuckets);
      if (empty($partnerIds)) {
         return [
            'rows' => [],
            'totals' => [
               'not_yet_due' => 0,
               'days_1_30' => 0,
               'days_31_60' => 0,
               'days_61_90' => 0,
               'days_91_plus' => 0,
               'total' => 0,
            ],
         ];
      }

      $partners = Partner::whereIn('id', $partnerIds)->orderBy('name', 'asc')->get()->keyBy('id');
      $rows = [];
      $totals = [
         'not_yet_due' => 0,
         'days_1_30' => 0,
         'days_31_60' => 0,
         'days_61_90' => 0,
         'days_91_plus' => 0,
         'total' => 0,
      ];

      foreach ($partners as $partnerId => $partner) {
         $b = $partnerBuckets[$partnerId] ?? null;
         if (!$b || ($b['total'] ?? 0) <= 0) {
            continue;
         }
         $rows[] = [
            'partner' => $partner,
            'not_yet_due' => $b['not_yet_due'],
            'days_1_30' => $b['days_1_30'],
            'days_31_60' => $b['days_31_60'],
            'days_61_90' => $b['days_61_90'],
            'days_91_plus' => $b['days_91_plus'],
            'total' => $b['total'],
         ];

         $totals['not_yet_due'] += $b['not_yet_due'];
         $totals['days_1_30'] += $b['days_1_30'];
         $totals['days_31_60'] += $b['days_31_60'];
         $totals['days_61_90'] += $b['days_61_90'];
         $totals['days_91_plus'] += $b['days_91_plus'];
         $totals['total'] += $b['total'];
      }

      return [
         'rows' => $rows,
         'totals' => $totals,
      ];
   }

   public function download(Request $request)
   {
      $filters = $request->all();
      if (empty($filters['end_date'])) {
         $filters['end_date'] = date('Y-m-d');
      }
      $reportData = $this->getReportData($filters);

      if (($request->format ?? 'xlsx') === 'pdf') {
         return $this->downloadPdf($reportData, $filters);
      }
      return $this->downloadExcel($reportData, $filters);
   }

   private function downloadPdf(array $reportData, array $filters)
   {
      $pdf = Pdf::loadView('reports.external-receivable-aging', [
         'reportData' => $reportData,
         'filters' => $filters,
      ]);
      return $pdf->download('umur-piutang-luar-' . date('Y-m-d') . '.pdf');
   }

   private function downloadExcel(array $reportData, array $filters)
   {
      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();

      // Set column widths
      $sheet->getColumnDimension('A')->setWidth(50);
      $sheet->getColumnDimension('B')->setWidth(18);
      $sheet->getColumnDimension('C')->setWidth(18);
      $sheet->getColumnDimension('D')->setWidth(18);
      $sheet->getColumnDimension('E')->setWidth(18);
      $sheet->getColumnDimension('F')->setWidth(18);
      $sheet->getColumnDimension('G')->setWidth(18);

      // Title
      $sheet->setCellValue('A1', 'Umur Piutang Luar');
      $sheet->mergeCells('A1:G1');
      $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A1')->getFont()->setBold(true);
      $sheet->getStyle('A1')->getFont()->setSize(16);

      // Date
      $sheet->setCellValue('A2', 'Per ' . date('d/m/Y', strtotime($filters['end_date'])));
      $sheet->mergeCells('A2:G2');
      $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A2')->getFont()->setSize(12);

      $companies = null;
      $branches = null;
      if (!empty($filters['company_id'])) {
         $companies = Company::whereIn('id', $filters['company_id'])->get();
      }
      if (!empty($filters['branch_id'])) {
         $branches = Branch::whereIn('id', $filters['branch_id'])->get();
      }

      $nextRow = 3;
      if (!empty($filters['company_id']) || empty($filters['branch_id'])) {
         $sheet->setCellValue('A3', 'Perusahaan: ' . (!empty($filters['company_id']) ? $companies?->pluck('name')->implode(', ') : (!empty($branches) ? '' : 'Semua Perusahaan')));
         $sheet->mergeCells('A3:G3');
         $sheet->getStyle('A3:G3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
         $sheet->getStyle('A3:G3')->getFont()->setSize(12);
         $nextRow = 4;
      }

      $sheet->setCellValue('A' . $nextRow, 'Cabang: ' . (!empty($filters['branch_id']) ? $branches?->pluck('name')->implode(', ') : 'Semua Cabang'));
      $sheet->mergeCells('A' . $nextRow . ':G' . $nextRow);
      $sheet->getStyle('A' . $nextRow . ':G' . $nextRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A' . $nextRow . ':G' . $nextRow)->getFont()->setSize(12);

      // Headers
      $row = 6;
      $sheet->setCellValue('A' . $row, 'Partner');
      $sheet->setCellValue('B' . $row, 'Belum Jatuh Tempo');
      $sheet->setCellValue('C' . $row, '1-30 Hari');
      $sheet->setCellValue('D' . $row, '31-60 Hari');
      $sheet->setCellValue('E' . $row, '61-90 Hari');
      $sheet->setCellValue('F' . $row, '91+ Hari');
      $sheet->setCellValue('G' . $row, 'Total');
      $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
      $row++;

      foreach ($reportData['rows'] as $r) {
         $sheet->setCellValue('A' . $row, $r['partner']['name']);
         $sheet->setCellValue('B' . $row, $r['not_yet_due']);
         $sheet->setCellValue('C' . $row, $r['days_1_30']);
         $sheet->setCellValue('D' . $row, $r['days_31_60']);
         $sheet->setCellValue('E' . $row, $r['days_61_90']);
         $sheet->setCellValue('F' . $row, $r['days_91_plus']);
         $sheet->setCellValue('G' . $row, $r['total']);
         $row++;
      }

      // Totals
      $sheet->setCellValue('A' . $row, 'TOTAL');
      $sheet->setCellValue('B' . $row, $reportData['totals']['not_yet_due']);
      $sheet->setCellValue('C' . $row, $reportData['totals']['days_1_30']);
      $sheet->setCellValue('D' . $row, $reportData['totals']['days_31_60']);
      $sheet->setCellValue('E' . $row, $reportData['totals']['days_61_90']);
      $sheet->setCellValue('F' . $row, $reportData['totals']['days_91_plus']);
      $sheet->setCellValue('G' . $row, $reportData['totals']['total']);
      $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      // Number format
      $sheet->getStyle('B7:G' . $row)->getNumberFormat()->setFormatCode('#,##0');

      $writer = new Xlsx($spreadsheet);
      $filename = 'umur-piutang-luar-' . date('Y-m-d') . '.xlsx';

      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment;filename="' . $filename . '"');
      header('Cache-Control: max-age=0');

      $writer->save('php://output');
      exit;
   }
}


