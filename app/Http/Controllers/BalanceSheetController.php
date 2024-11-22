<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Currency;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class BalanceSheetController extends Controller
{
   public function index(Request $request)
   {
      $filters = $request->all();

      if (empty($filters['end_date'])) {
         $filters['end_date'] = date('Y-m-d');
      }

      if (empty($filters['report_type'])) {
         $filters['report_type'] = 'summary';
      }
      
      $companies = Company::orderBy('name', 'asc')->get();
      
      $query = Branch::query();
      if (!empty($filters['company_id'])) {
         $query->whereHas('branchGroup', function ($q) use ($filters) {
               $q->whereIn('company_id', $filters['company_id']);
         });
      }
      $branches = $query->orderBy('name', 'asc')->get();

      $reportData = $this->getReportData($filters);

      return Inertia::render('Reports/BalanceSheet', [
         'companies' => $companies,
         'branches' => $branches,
         'filters' => $filters,
         'reportData' => $reportData,
      ]);
   }

   private function getReportData($filters)
   {
      // Get date for previous month end
      $currentDate = $filters['end_date'];
      $previousDate = date('Y-m-d', strtotime($currentDate . ' -1 month'));

      // Check if current end date is last day of month
      $lastDayOfMonth = date('Y-m-t', strtotime($currentDate));
      if ($currentDate === $lastDayOfMonth) {
         // If current end is last day of month, get last day of previous month
         $previousDate = date('Y-m-t', strtotime(date('Y-m-01', strtotime($currentDate)) . ' -1 month'));
      }

      $currentBalances = $this->getAllAccountBalances($currentDate, $filters);
      $previousBalances = $this->getAllAccountBalances($previousDate, $filters);

      $data = [
         'assets' => $this->getAssetsSection($filters, $currentBalances, $previousBalances),
         'liabilities' => $this->getLiabilitiesSection($filters, $currentBalances, $previousBalances),
         'equity' => $this->getEquitySection($filters, $currentBalances, $previousBalances),
      ];

      // Calculate totals
      $data['total_assets'] = [
         'current' => $data['assets']['total']['current'],
         'previous' => $data['assets']['total']['previous'],
      ];

      $data['total_liabilities_equity'] = [
         'current' => $data['liabilities']['total']['current'] + $data['equity']['total']['current'],
         'previous' => $data['liabilities']['total']['previous'] + $data['equity']['total']['previous'],
      ];

      return $data;
   }

   private function getAssetsSection($filters, $currentBalances, $previousBalances)
   {
      $isDetailed = $filters['report_type'] === 'detailed';

      // Cash and Bank
      $cashBankAccounts = Account::where('type', 'kas_bank')
         ->when(!$isDetailed, fn($q) => $q->where('level', 2))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 2))
         ->get();

      $cashBankData = $cashBankAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Accounts Receivable
      $receivableAccounts = Account::where('type', 'piutang_usaha')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $receivableData = $receivableAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Inventory
      $inventoryAccounts = Account::where('type', 'persediaan')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $inventoryData = $inventoryAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Other Current Assets
      $otherCurrentAccounts = Account::where('type', 'aset_lancar_lainnya')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $otherCurrentData = $otherCurrentAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Fixed Assets
      $fixedAssetAccounts = Account::where('type', 'aset_tetap')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $fixedAssetData = $fixedAssetAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Accumulated Depreciation
      $accumulatedDepreciationAccounts = Account::where('type', 'akumulasi_penyusutan')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $accumulatedDepreciationData = $accumulatedDepreciationAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Other Assets
      $otherAssetAccounts = Account::where('type', 'aset_lainnya')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $otherAssetData = $otherAssetAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Calculate total assets
      $totalAssets = [
         'current' => $cashBankData->sum('balances.current') +
                     $receivableData->sum('balances.current') +
                     $inventoryData->sum('balances.current') +
                     $otherCurrentData->sum('balances.current') +
                     $fixedAssetData->sum('balances.current') +
                     $accumulatedDepreciationData->sum('balances.current') +
                     $otherAssetData->sum('balances.current'),
         'previous' => $cashBankData->sum('balances.previous') +
                        $receivableData->sum('balances.previous') +
                        $inventoryData->sum('balances.previous') +
                        $otherCurrentData->sum('balances.previous') +
                        $fixedAssetData->sum('balances.previous') +
                        $accumulatedDepreciationData->sum('balances.previous') +
                        $otherAssetData->sum('balances.previous'),
      ];

      return [
         'cash_bank' => $cashBankData,
         'receivable' => $receivableData,
         'inventory' => $inventoryData,
         'other_current' => $otherCurrentData,
         'fixed_asset' => $fixedAssetData,
         'accumulated_depreciation' => $accumulatedDepreciationData,
         'other_asset' => $otherAssetData,
         'total' => $totalAssets
      ];
   }

   private function getLiabilitiesSection($filters, $currentBalances, $previousBalances)
   {
      $isDetailed = $filters['report_type'] === 'detailed';

      // Accounts Payable
      $payableAccounts = Account::where('type', 'hutang_usaha')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $payableData = $payableAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Other Accounts Payable
      $otherPayableAccounts = Account::where('type', 'hutang_usaha_lainnya')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $otherPayableData = $otherPayableAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Short-term Liabilities
      $shortTermAccounts = Account::where('type', 'liabilitas_jangka_pendek')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $shortTermData = $shortTermAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Long-term Liabilities
      $longTermAccounts = Account::where('type', 'liabilitas_jangka_panjang')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $longTermData = $longTermAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Calculate total liabilities
      $totalLiabilities = [
         'current' => $payableData->sum('balances.current') +
                     $otherPayableData->sum('balances.current') +
                     $shortTermData->sum('balances.current') +
                     $longTermData->sum('balances.current'),
         'previous' => $payableData->sum('balances.previous') +
                        $otherPayableData->sum('balances.previous') +
                        $shortTermData->sum('balances.previous') +
                        $longTermData->sum('balances.previous'),
      ];

      return [
         'payable' => $payableData,
         'other_payable' => $otherPayableData,
         'short_term' => $shortTermData,
         'long_term' => $longTermData,
         'total' => $totalLiabilities
      ];
   }

   private function getEquitySection($filters, $currentBalances, $previousBalances)
   {
      $isDetailed = $filters['report_type'] === 'detailed';

      // Equity accounts
      $equityAccounts = Account::where('type', 'modal')
         ->when(!$isDetailed, fn($q) => $q->where('level', 1))
         ->when($isDetailed, fn($q) => $q->where('level', '>=', 1))
         ->get();

      $equityData = $equityAccounts->map(function ($account) use ($currentBalances, $previousBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed);
      });

      // Calculate total equity
      $totalEquity = [
         'current' => $equityData->sum('balances.current'),
         'previous' => $equityData->sum('balances.previous'),
      ];

      return [
         'accounts' => $equityData,
         'total' => $totalEquity
      ];
   }

   private function getAllAccountBalances($date, $filters)
   {
      $query = JournalEntry::join('accounts', 'journal_entries.account_id', '=', 'accounts.id')
         ->where('accounts.is_parent', false)
         ->whereHas('journal', function ($query) use ($date, $filters) {
               $query->whereDate('date', '<=', $date);
               
               if (!empty($filters['company_id'])) {
                  $query->whereHas('branch.branchGroup', function ($q) use ($filters) {
                     $q->whereIn('company_id', $filters['company_id']);
                  });
               }
               
               if (!empty($filters['branch_id'])) {
                  $query->whereIn('branch_id', $filters['branch_id']);
               }
         })
         ->select(
               'account_id',
               'accounts.balance_type',
               DB::raw('SUM(journal_entries.primary_currency_debit) as total_debit'),
               DB::raw('SUM(journal_entries.primary_currency_credit) as total_credit')
         )
         ->groupBy('account_id', 'accounts.balance_type');

      return $query->get()
         ->keyBy('account_id')
         ->map(function ($item) {
               return [
                  'balance' => $item->balance_type === 'debit' 
                     ? $item->total_debit - $item->total_credit 
                     : $item->total_credit - $item->total_debit
               ];
         });
   }

   private function getDescendantIds($account)
   {
      return $account->descendants()
         ->where('is_parent', false)
         ->pluck('id')
         ->push($account->id)
         ->unique();
   }

   private function mapAccountBalances($account, $currentBalances, $previousBalances, $isDetailed)
   {
      $balances = ['current' => 0, 'previous' => 0];
      
      if ($isDetailed || !$account->is_parent) {
         // For detailed report or non-parent accounts, use direct balance
         $balances = [
               'current' => $currentBalances[$account->id]['balance'] ?? 0,
               'previous' => $previousBalances[$account->id]['balance'] ?? 0,
         ];
      } else {
         // For summary report with parent accounts, sum all descendant balances
         $descendantIds = $this->getDescendantIds($account);
         
         foreach ($descendantIds as $id) {
               $balances['current'] += $currentBalances[$id]['balance'] ?? 0;
               $balances['previous'] += $previousBalances[$id]['balance'] ?? 0;
         }
      }

      return [
         'account' => $account,
         'balances' => $balances
      ];
   }

   public function download(Request $request)
   {
      $filters = $request->all();
      $reportData = $this->getReportData($filters);
      
      if ($request->format === 'pdf') {
         return $this->downloadPdf($reportData, $filters);
      }
      
      return $this->downloadExcel($reportData, $filters);
   }

   private function downloadPdf($reportData, $filters)
   {
      $pdf = Pdf::loadView('reports.balance-sheet', [
         'reportData' => $reportData,
         'filters' => $filters,
      ]);
      
      return $pdf->download('neraca-' . date('Y-m-d') . '.pdf');
   }

   private function downloadExcel($reportData, $filters)
   {
      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();
      
      // Set column widths
      $sheet->getColumnDimension('A')->setWidth(50);
      $sheet->getColumnDimension('B')->setWidth(20);
      $sheet->getColumnDimension('C')->setWidth(20);
      
      // Add title
      $sheet->setCellValue('A1', 'NERACA');
      $sheet->mergeCells('A1:C1');
      $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A1')->getFont()->setBold(true);
      $sheet->getStyle('A1')->getFont()->setSize(16);
      
      // Add date
      $sheet->setCellValue('A2', 'Per ' . date('d/m/Y', strtotime($filters['end_date'])));
      $sheet->mergeCells('A2:C2');
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
         $sheet->mergeCells('A3:D3');
         $sheet->getStyle('A3:D3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
         $sheet->getStyle('A3:D3')->getFont()->setSize(12);
         $nextRow = 4;
      }

      $sheet->setCellValue('A' . $nextRow, 'Cabang: ' . (!empty($filters['branch_id']) ? $branches?->pluck('name')->implode(', ') : 'Semua Cabang'));
      $sheet->mergeCells('A' . $nextRow . ':D' . $nextRow);
      $sheet->getStyle('A' . $nextRow . ':D' . $nextRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A' . $nextRow . ':D' . $nextRow)->getFont()->setSize(12);

      // Add headers
      $row = 6;
      $sheet->setCellValue('A' . $row, 'Keterangan');
      $sheet->setCellValue('B' . $row, 'Bulan Lalu');
      $sheet->setCellValue('C' . $row, 'Bulan Ini');
      $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
      $row++;

      // Assets Section
      $this->addExcelSection($sheet, $row, 'AKTIVA', $reportData['assets'], $filters);
      $row = $this->getNextRow($sheet);
      $row++;

      // Liabilities Section
      $this->addExcelSection($sheet, $row, 'PASIVA', [], $filters);
      $row = $this->getNextRow($sheet);
      $this->addExcelSection($sheet, $row, 'Kewajiban', $reportData['liabilities'], $filters);
      $row = $this->getNextRow($sheet);
      $this->addExcelSection($sheet, $row, 'Modal', $reportData['equity'], $filters);
      $row = $this->getNextRow($sheet);

      $sheet->setCellValue('A' . $row, 'Total Pasiva');
      $sheet->setCellValue('B' . $row, $reportData['total_liabilities_equity']['previous']);
      $sheet->setCellValue('C' . $row, $reportData['total_liabilities_equity']['current']);
      $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      // Format numbers
      $sheet->getStyle('B7:C' . $row)->getNumberFormat()->setFormatCode('#,##0');

      $writer = new Xlsx($spreadsheet);
      
      $filename = 'neraca-' . date('Y-m-d') . '.xlsx';
      
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment;filename="' . $filename . '"');
      header('Cache-Control: max-age=0');
      
      $writer->save('php://output');
      exit;
   }

   private function addExcelSection($sheet, &$row, $title, $data, $filters)
   {
      // Section header
      $sheet->setCellValue('A' . $row, $title);
      $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      // Add data rows
      foreach ($data as $key => $section) {
         if ($key !== 'total') {
               foreach ($section as $account) {
                  if (!isset($account['account'])) dd($data, $key, $section, $account);
                  $sheet->setCellValue('A' . $row, str_repeat('    ', $account['account']['level']) . $account['account']['code'] . ' - ' . $account['account']['name']);
                  if ($filters['report_type'] === 'detailed' && $account['account']['is_parent']) {
                     $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                  } else {
                     $sheet->setCellValue('B' . $row, $account['balances']['previous']);
                     $sheet->setCellValue('C' . $row, $account['balances']['current']);
                  }
                  $row++;
               }
         }
      }

      // Add total
      if (isset($data['total'])) {
         $sheet->setCellValue('A' . $row, 'Total ' . $title);
         $sheet->setCellValue('B' . $row, $data['total']['previous']);
         $sheet->setCellValue('C' . $row, $data['total']['current']);
         $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
         $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
         $row++;
      }
   }

   private function getNextRow($sheet)
   {
      return $sheet->getHighestRow() + 1;
   }
}
