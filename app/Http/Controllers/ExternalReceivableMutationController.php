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

class ExternalReceivableMutationController extends Controller
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

    $reportData = $this->getReportData($filters);

    return Inertia::render('Reports/ExternalReceivableMutation', [
      'companies' => $companies,
      'branches' => $branches,
      'filters' => $filters,
      'reportData' => $reportData,
    ]);
  }

  private function getReportData(array $filters): array
  {
    $startDate = Carbon::parse($filters['start_date'])->startOfDay();
    $endDate = Carbon::parse($filters['end_date'])->endOfDay();
    $openingCutoff = $startDate->copy()->subDay()->endOfDay();

    $debtBase = ExternalDebt::query()
      ->where('type', 'receivable');
    if (!empty($filters['company_id'])) {
      $debtBase->whereHas('branch.branchGroup', function ($q) use ($filters) {
        $q->whereIn('company_id', $filters['company_id']);
      });
    }
    if (!empty($filters['branch_id'])) {
      $debtBase->whereIn('branch_id', $filters['branch_id']);
    }

    $openingDebts = (clone $debtBase)
      ->whereDate('issue_date', '<=', $openingCutoff->toDateString())
      ->select('partner_id', DB::raw('SUM(primary_currency_amount) as total'))
      ->groupBy('partner_id')
      ->pluck('total', 'partner_id');

    $openingPayments = ExternalDebtPaymentDetail::query()
      ->join('external_debts as ed', 'ed.id', '=', 'external_debt_payment_details.external_debt_id')
      ->join('external_debt_payments as edp', 'edp.id', '=', 'external_debt_payment_details.external_debt_payment_id')
      ->where('ed.type', 'receivable')
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

    $additions = (clone $debtBase)
      ->whereDate('issue_date', '>=', $startDate->toDateString())
      ->whereDate('issue_date', '<=', $endDate->toDateString())
      ->select('partner_id', DB::raw('SUM(primary_currency_amount) as total'))
      ->groupBy('partner_id')
      ->pluck('total', 'partner_id');

    $payments = ExternalDebtPaymentDetail::query()
      ->join('external_debts as ed', 'ed.id', '=', 'external_debt_payment_details.external_debt_id')
      ->join('external_debt_payments as edp', 'edp.id', '=', 'external_debt_payment_details.external_debt_payment_id')
      ->where('ed.type', 'receivable')
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
      ->select('ed.partner_id', DB::raw('SUM(external_debt_payment_details.primary_currency_amount) as total'))
      ->groupBy('ed.partner_id')
      ->pluck('total', 'ed.partner_id');

    $partnerIds = collect()
      ->merge(array_keys($openingDebts->toArray()))
      ->merge(array_keys($openingPayments->toArray()))
      ->merge(array_keys($additions->toArray()))
      ->merge(array_keys($payments->toArray()))
      ->unique()
      ->values()
      ->all();

    if (empty($partnerIds)) {
      return [
        'rows' => [],
        'totals' => [
          'opening' => 0,
          'additions' => 0,
          'payments' => 0,
          'closing' => 0,
        ],
      ];
    }

    $partners = Partner::whereIn('id', $partnerIds)->orderBy('name', 'asc')->get()->keyBy('id');

    $rows = [];
    $totals = [
      'opening' => 0,
      'additions' => 0,
      'payments' => 0,
      'closing' => 0,
    ];

    foreach ($partners as $pid => $partner) {
      $open = (float) ($openingDebts[$pid] ?? 0) - (float) ($openingPayments[$pid] ?? 0);
      $add = (float) ($additions[$pid] ?? 0);
      $pay = (float) ($payments[$pid] ?? 0);
      $close = $open + $add - $pay;

      if (abs($open) < 0.000001 && abs($add) < 0.000001 && abs($pay) < 0.000001 && abs($close) < 0.000001) {
        continue;
      }

      $rows[] = [
        'partner' => $partner,
        'opening' => $open,
        'additions' => $add,
        'payments' => $pay,
        'closing' => $close,
      ];

      $totals['opening'] += $open;
      $totals['additions'] += $add;
      $totals['payments'] += $pay;
      $totals['closing'] += $close;
    }

    return [
      'rows' => $rows,
      'totals' => $totals,
    ];
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
    $reportData = $this->getReportData($filters);

    if (($request->format ?? 'xlsx') === 'pdf') {
      return $this->downloadPdf($reportData, $filters);
    }
    return $this->downloadExcel($reportData, $filters);
  }

  private function downloadPdf($reportData, $filters)
  {
    $pdf = Pdf::loadView('reports.external-receivable-mutation', [
      'reportData' => $reportData,
      'filters' => $filters,
    ]);
    return $pdf->download('mutasi-piutang-luar-' . date('Y-m-d') . '.pdf');
  }

  private function downloadExcel($reportData, $filters)
  {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Widths
    $sheet->getColumnDimension('A')->setWidth(50);
    $sheet->getColumnDimension('B')->setWidth(18);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(18);
    $sheet->getColumnDimension('E')->setWidth(18);

    // Title
    $sheet->setCellValue('A1', 'Mutasi Piutang Luar');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getStyle('A1')->getFont()->setSize(16);

    // Date
    $sheet->setCellValue('A2', date('d/m/Y', strtotime($filters['start_date'])) . ' s/d ' . date('d/m/Y', strtotime($filters['end_date'])));
    $sheet->mergeCells('A2:E2');
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
      $sheet->mergeCells('A3:E3');
      $sheet->getStyle('A3:E3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A3:E3')->getFont()->setSize(12);
      $nextRow = 4;
    }

    $sheet->setCellValue('A' . $nextRow, 'Cabang: ' . (!empty($filters['branch_id']) ? $branches?->pluck('name')->implode(', ') : 'Semua Cabang'));
    $sheet->mergeCells('A' . $nextRow . ':E' . $nextRow);
    $sheet->getStyle('A' . $nextRow . ':E' . $nextRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $nextRow . ':E' . $nextRow)->getFont()->setSize(12);

    // Headers
    $row = 6;
    $sheet->setCellValue('A' . $row, 'Partner');
    $sheet->setCellValue('B' . $row, 'Saldo Awal');
    $sheet->setCellValue('C' . $row, 'Penambahan');
    $sheet->setCellValue('D' . $row, 'Pembayaran');
    $sheet->setCellValue('E' . $row, 'Saldo Akhir');
    $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
    $row++;

    foreach ($reportData['rows'] as $r) {
      $sheet->setCellValue('A' . $row, $r['partner']['name']);
      $sheet->setCellValue('B' . $row, $r['opening']);
      $sheet->setCellValue('C' . $row, $r['additions']);
      $sheet->setCellValue('D' . $row, $r['payments']);
      $sheet->setCellValue('E' . $row, $r['closing']);
      $row++;
    }

    // Totals
    $sheet->setCellValue('A' . $row, 'TOTAL');
    $sheet->setCellValue('B' . $row, $reportData['totals']['opening']);
    $sheet->setCellValue('C' . $row, $reportData['totals']['additions']);
    $sheet->setCellValue('D' . $row, $reportData['totals']['payments']);
    $sheet->setCellValue('E' . $row, $reportData['totals']['closing']);
    $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
    $row++;

    // Number format
    $sheet->getStyle('B7:E' . $row)->getNumberFormat()->setFormatCode('#,##0');

    $writer = new Xlsx($spreadsheet);
    $filename = 'mutasi-piutang-luar-' . date('Y-m-d') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer->save('php://output');
    exit;
  }
}


