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

class IncomeReportController extends Controller
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

      return Inertia::render('Reports/Income', [
         'companies' => $companies,
         'branches' => $branches,
         'filters' => $filters,
         'reportData' => $reportData,
      ]);
   }

   private function getReportData($filters)
   {
      // Get date ranges
      $currentStart = $filters['start_date'];
      $currentEnd = $filters['end_date'];
      
      $previousStart = date('Y-m-d', strtotime($currentStart . ' -1 month'));
      
      // Check if current end date is last day of month
      $lastDayOfMonth = date('Y-m-t', strtotime($currentEnd));
      if ($currentEnd === $lastDayOfMonth) {
         // If current end is last day of month, get last day of previous month
         $previousEnd = date('Y-m-t', strtotime(date('Y-m-01', strtotime($currentEnd)) . ' -1 month'));
      } else {
         // Otherwise just get same date in previous month
         $previousEnd = date('Y-m-d', strtotime($currentEnd . ' -1 month')); 
      }
      
      $ytdStart = date('Y-01-01', strtotime($currentStart));
      $ytdEnd = $currentEnd;

      $currentBalances = $this->getAllAccountBalances($currentStart, $currentEnd, $filters);
      $previousBalances = $this->getAllAccountBalances($previousStart, $previousEnd, $filters);
      $ytdBalances = $this->getAllAccountBalances($ytdStart, $ytdEnd, $filters);


      $data = [
         'revenue' => $this->getRevenueSection($filters, $currentBalances, $previousBalances, $ytdBalances),
         'cash_costs' => $this->getCashCostsSection($filters, $currentBalances, $previousBalances, $ytdBalances),
         'non_cash_costs' => $this->getNonCashCostsSection($filters, $currentBalances, $previousBalances, $ytdBalances),
     ];

      // Calculate totals
      $data['total_cost'] = [
         'current' => $data['cash_costs']['total']['current'] + $data['non_cash_costs']['total']['current'],
         'previous' => $data['cash_costs']['total']['previous'] + $data['non_cash_costs']['total']['previous'],
         'ytd' => $data['cash_costs']['total']['ytd'] + $data['non_cash_costs']['total']['ytd'],
      ];

      $data['net_profit'] = [
         'current' => $data['revenue']['gross_profit']['current'] - $data['total_cost']['current'],
         'previous' => $data['revenue']['gross_profit']['previous'] - $data['total_cost']['previous'],
         'ytd' => $data['revenue']['gross_profit']['ytd'] - $data['total_cost']['ytd'],
      ];

      return $data;
   }

   private function getRevenueSection($filters, $currentBalances, $previousBalances, $ytdBalances)
   {
      $isDetailed = $filters['report_type'] === 'detailed';
      
      $revenueAccounts = Account::where('type', 'pendapatan')
         ->when(!$isDetailed, fn($q) => $q->where('level', 0))
         ->when($isDetailed, fn($q) => $q->whereIn('level', [0, 1]))
         ->get();

      $revenueData = $revenueAccounts->map(function ($account) use ($currentBalances, $previousBalances, $ytdBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $ytdBalances, $isDetailed);
      });

      $cogsAccounts = Account::where('type', 'beban_pokok_penjualan')
         ->when(!$isDetailed, fn($q) => $q->where('level', 0))
         ->when($isDetailed, fn($q) => $q->whereIn('level', [0, 1]))
         ->get();

      $cogsData = $cogsAccounts->map(function ($account) use ($currentBalances, $previousBalances, $ytdBalances, $isDetailed) {
         return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $ytdBalances, $isDetailed);
      });

      // Calculate totals
      $grossProfit = [
         'current' => $revenueData->sum('balances.current') - $cogsData->sum('balances.current'),
         'previous' => $revenueData->sum('balances.previous') - $cogsData->sum('balances.previous'),
         'ytd' => $revenueData->sum('balances.ytd') - $cogsData->sum('balances.ytd'),
      ];

      return [
         'revenue' => [
            'accounts' => $revenueData,
         ],
         'cogs' => [
            'accounts' => $cogsData,
         ],
         'gross_profit' => $grossProfit
      ];
   }

   private function getCashCostsSection($filters, $currentBalances, $previousBalances, $ytdBalances)
   {
      $isDetailed = $filters['report_type'] === 'detailed';

      // Get operational accounts
      $operationalAccounts = Account::where('type', 'beban')
          ->when(!$isDetailed, fn($q) => $q->where('level', 1))
          ->when($isDetailed, fn($q) => $q->whereIn('level', [1, 2]))
          ->get();

      $operationalData = $operationalAccounts->map(function ($account) use ($currentBalances, $previousBalances, $ytdBalances, $isDetailed) {
          return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $ytdBalances, $isDetailed);
      });

      // Calculate operational totals
      $operationalTotal = [
          'current' => $operationalData->sum('balances.current'),
          'previous' => $operationalData->sum('balances.previous'),
          'ytd' => $operationalData->sum('balances.ytd'),
      ];

      // Get non-operational accounts (other income)
      $otherIncomeAccounts = Account::where('type', 'pendapatan_lainnya')
          ->when(!$isDetailed, fn($q) => $q->where('level', 0))
          ->when($isDetailed, fn($q) => $q->whereIn('level', [0, 1]))
          ->get();

      $otherIncomeData = $otherIncomeAccounts->map(function ($account) use ($currentBalances, $previousBalances, $ytdBalances, $isDetailed) {
          return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $ytdBalances, $isDetailed);
      });

      // Get non-operational accounts (other expenses)
      $otherExpensesAccounts = Account::where('type', 'beban_lainnya')
          ->when(!$isDetailed, fn($q) => $q->where('level', 0))
          ->when($isDetailed, fn($q) => $q->whereIn('level', [0, 1]))
          ->get();

      $otherExpensesData = $otherExpensesAccounts->map(function ($account) use ($currentBalances, $previousBalances, $ytdBalances, $isDetailed) {
          return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $ytdBalances, $isDetailed);
      });

      // Calculate non-operational totals
      $nonOperationalTotal = [
          'current' => $otherExpensesData->sum('balances.current') - $otherIncomeData->sum('balances.current'),
          'previous' => $otherExpensesData->sum('balances.previous') - $otherIncomeData->sum('balances.previous'),
          'ytd' => $otherExpensesData->sum('balances.ytd') - $otherIncomeData->sum('balances.ytd'),
      ];

      // Calculate total cash costs
      $totalCashCosts = [
          'current' => $operationalTotal['current'] + $nonOperationalTotal['current'],
          'previous' => $operationalTotal['previous'] + $nonOperationalTotal['previous'],
          'ytd' => $operationalTotal['ytd'] + $nonOperationalTotal['ytd'],
      ];

      return [
          'operational' => [
              'accounts' => $operationalData,
              'total' => $operationalTotal
          ],
          'non_operational' => [
              'other_income' => $otherIncomeData,
              'other_expenses' => $otherExpensesData,
              'total' => $nonOperationalTotal
          ],
          'total' => $totalCashCosts
      ];
   }

   private function getNonCashCostsSection($filters, $currentBalances, $previousBalances, $ytdBalances)
   {
      $isDetailed = $filters['report_type'] === 'detailed';

      // Get depreciation accounts
      $depreciationAccounts = Account::where('type', 'beban_penyusutan')
          ->when(!$isDetailed, fn($q) => $q->where('level', 1))
          ->when($isDetailed, fn($q) => $q->whereIn('level', [1, 2]))
          ->get();

      $depreciationData = $depreciationAccounts->map(function ($account) use ($currentBalances, $previousBalances, $ytdBalances, $isDetailed) {
          return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $ytdBalances, $isDetailed);
      });

      // Get amortization accounts
      $amortizationAccounts = Account::where('type', 'beban_amortisasi')
          ->when(!$isDetailed, fn($q) => $q->where('level', 1))
          ->when($isDetailed, fn($q) => $q->whereIn('level', [1, 2]))
          ->get();

      $amortizationData = $amortizationAccounts->map(function ($account) use ($currentBalances, $previousBalances, $ytdBalances, $isDetailed) {
          return $this->mapAccountBalances($account, $currentBalances, $previousBalances, $ytdBalances, $isDetailed);
      });

      // Calculate total non-cash costs
      $totalNonCashCosts = [
          'current' => $depreciationData->sum('balances.current') + $amortizationData->sum('balances.current'),
          'previous' => $depreciationData->sum('balances.previous') + $amortizationData->sum('balances.previous'),
          'ytd' => $depreciationData->sum('balances.ytd') + $amortizationData->sum('balances.ytd'),
      ];

      return [
          'depreciation' => $depreciationData,
          'amortization' => $amortizationData,
          'total' => $totalNonCashCosts
      ];
   }

   public function download(Request $request)
   {
      $filters = $request->all();
      $format = $filters['format'] ?? 'xlsx';
      
      // Get the data
      $reportData = $this->getReportData($filters);
      
      switch ($format) {
         case 'xlsx':
               return $this->downloadExcel($reportData, $filters);
         case 'pdf':
               return $this->downloadPdf($reportData, $filters);
         default:
               abort(400, 'Invalid format');
      }
   }

   private function downloadExcel($reportData, $filters)
   {
      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();
      
      // Set headers
      $sheet->setCellValue('A1', 'Laba/Rugi');
      $sheet->mergeCells('A1:D1');
      $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A1:D1')->getFont()->setBold(true);
      $sheet->getStyle('A1:D1')->getFont()->setSize(16);
      
      $sheet->setCellValue('A2', date('d/m/Y', strtotime($filters['start_date'])) . ' s/d ' . date('d/m/Y', strtotime($filters['end_date'])));
      $sheet->mergeCells('A2:D2');
      $sheet->getStyle('A2:D2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A2:D2')->getFont()->setSize(12);

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

      $row = $nextRow + 2;

      // Headers
      $sheet->setCellValue('A' . $row, 'Keterangan');
      $sheet->setCellValue('B' . $row, 'Bulan Lalu');
      $sheet->setCellValue('C' . $row, 'Bulan Ini');
      $sheet->setCellValue('D' . $row, 'Tahun Berjalan');
      $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
      $row++;

      // Continue with sections...
      $this->writeIncomeSection($sheet, $row, $reportData, $filters);

      $writer = new Xlsx($spreadsheet);
      
      $filename = 'laba-rugi-' . date('Y-m-d') . '.xlsx';
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment;filename="' . $filename . '"');
      header('Cache-Control: max-age=0');
      
      $writer->save('php://output');
      exit;
   }

   private function writeIncomeSection(&$sheet, &$row, $reportData, $filters)
   {
      // Revenue Section
      $sheet->setCellValue('A' . $row, 'PENDAPATAN');
      $sheet->getStyle('A' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      foreach ($reportData['revenue']['revenue']['accounts'] as $account) {
         $sheet->setCellValue('A' . $row, '    ' . ($account['account']['level'] === 0 ? '' : '    ') . $account['account']['code'] . ' - ' . $account['account']['name']);
         if ($filters['report_type'] === 'detailed' && $account['account']['is_parent']) {
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         }
         else
         {
            $sheet->setCellValue('B' . $row, $account['balances']['previous']);
            $sheet->setCellValue('C' . $row, $account['balances']['current']);
            $sheet->setCellValue('D' . $row, $account['balances']['ytd']);
         }
         $row++;
      }

      foreach ($reportData['revenue']['cogs']['accounts'] as $account) {
         $sheet->setCellValue('A' . $row, '    ' . ($account['account']['level'] === 0 ? '' : '    ') . $account['account']['code'] . ' - ' . $account['account']['name']);
         if ($filters['report_type'] === 'detailed' && $account['account']['is_parent']) {
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         }
         else
         {
            $sheet->setCellValue('B' . $row, $account['balances']['previous']);
            $sheet->setCellValue('C' . $row, $account['balances']['current']);
            $sheet->setCellValue('D' . $row, $account['balances']['ytd']);
         }
         $row++;
      }

      // Gross Profit
      $sheet->setCellValue('A' . $row, 'Laba Kotor');
      $sheet->setCellValue('B' . $row, $reportData['revenue']['gross_profit']['previous']);
      $sheet->setCellValue('C' . $row, $reportData['revenue']['gross_profit']['current']);
      $sheet->setCellValue('D' . $row, $reportData['revenue']['gross_profit']['ytd']);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      // Cash Costs Section
      $row++;
      $sheet->setCellValue('A' . $row, 'BIAYA KAS');
      $sheet->getStyle('A' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      // Operational Costs
      $sheet->setCellValue('A' . $row, '    Biaya Operasional:');
      $sheet->getStyle('A' . $row)->getFont()->setBold(true);
      $row++;

      foreach ($reportData['cash_costs']['operational']['accounts'] as $account) {
         $sheet->setCellValue('A' . $row, '        ' . ($account['account']['level'] === 1 ? '' : '    ') . $account['account']['code'] . ' - ' . $account['account']['name']);
         if ($filters['report_type'] === 'detailed' && $account['account']['is_parent']) {
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         }
         else
         {
            $sheet->setCellValue('B' . $row, $account['balances']['previous']);
            $sheet->setCellValue('C' . $row, $account['balances']['current']);
            $sheet->setCellValue('D' . $row, $account['balances']['ytd']);
         }
         $row++;
      }

      // Total Operational Costs
      $sheet->setCellValue('A' . $row, '    Total Biaya Kas Operasional');
      $sheet->setCellValue('B' . $row, $reportData['cash_costs']['operational']['total']['previous']);
      $sheet->setCellValue('C' . $row, $reportData['cash_costs']['operational']['total']['current']);
      $sheet->setCellValue('D' . $row, $reportData['cash_costs']['operational']['total']['ytd']);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
      $row++;

      // Non-operational Section
      $row++;
      $sheet->setCellValue('A' . $row, '    Biaya (Pendapatan) Non-operasional:');
      $sheet->getStyle('A' . $row)->getFont()->setBold(true);
      $row++;

      // Other Income
      foreach ($reportData['cash_costs']['non_operational']['other_income'] as $account) {
         $sheet->setCellValue('A' . $row, '        ' . ($account['account']['level'] === 0 ? '' : '    ') . $account['account']['code'] . ' - ' . $account['account']['name']);
         if ($filters['report_type'] === 'detailed' && $account['account']['is_parent']) {
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         }
         else
         {
            $sheet->setCellValue('B' . $row, -$account['balances']['previous']);
            $sheet->setCellValue('C' . $row, -$account['balances']['current']);
            $sheet->setCellValue('D' . $row, -$account['balances']['ytd']);
         }
         $row++;
      }

      // Other Expenses
      foreach ($reportData['cash_costs']['non_operational']['other_expenses'] as $account) {
         $sheet->setCellValue('A' . $row, '        ' . ($account['account']['level'] === 0 ? '' : '    ') . $account['account']['code'] . ' - ' . $account['account']['name']);
         if ($filters['report_type'] === 'detailed' && $account['account']['is_parent']) {
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         }
         else
         {
            $sheet->setCellValue('B' . $row, $account['balances']['previous']);
            $sheet->setCellValue('C' . $row, $account['balances']['current']);
            $sheet->setCellValue('D' . $row, $account['balances']['ytd']);
         }
         $row++;
      }

      // Total Non-operational Costs
      $sheet->setCellValue('A' . $row, '    Total Biaya Kas Non-operasional');
      $sheet->setCellValue('B' . $row, $reportData['cash_costs']['non_operational']['total']['previous']);
      $sheet->setCellValue('C' . $row, $reportData['cash_costs']['non_operational']['total']['current']);
      $sheet->setCellValue('D' . $row, $reportData['cash_costs']['non_operational']['total']['ytd']);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
      $row++;

      // Total Cash Costs
      $sheet->setCellValue('A' . $row, 'Total Biaya Kas');
      $sheet->setCellValue('B' . $row, $reportData['cash_costs']['total']['previous']);
      $sheet->setCellValue('C' . $row, $reportData['cash_costs']['total']['current']);
      $sheet->setCellValue('D' . $row, $reportData['cash_costs']['total']['ytd']);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      // Non-cash Costs Section
      $row++;
      $sheet->setCellValue('A' . $row, 'BIAYA NON-KAS');
      $sheet->getStyle('A' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      // Depreciation
      foreach ($reportData['non_cash_costs']['depreciation'] as $account) {
         $sheet->setCellValue('A' . $row, '    ' . ($account['account']['level'] === 1 ? '' : '    ') . $account['account']['code'] . ' - ' . $account['account']['name']);
         if ($filters['report_type'] === 'detailed' && $account['account']['is_parent']) {
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         }
         else
         {
            $sheet->setCellValue('B' . $row, $account['balances']['previous']);
            $sheet->setCellValue('C' . $row, $account['balances']['current']);
            $sheet->setCellValue('D' . $row, $account['balances']['ytd']);
         }
         $row++;
      }

      // Amortization
      foreach ($reportData['non_cash_costs']['amortization'] as $account) {
         $sheet->setCellValue('A' . $row, '    ' . ($account['account']['level'] === 1 ? '' : '    ') . $account['account']['code'] . ' - ' . $account['account']['name']);
         if ($filters['report_type'] === 'detailed' && $account['account']['is_parent']) {
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
         }
         else
         {
            $sheet->setCellValue('B' . $row, $account['balances']['previous']);
            $sheet->setCellValue('C' . $row, $account['balances']['current']);
            $sheet->setCellValue('D' . $row, $account['balances']['ytd']);
         }
         $row++;
      }

      // Total Non-cash Costs
      $sheet->setCellValue('A' . $row, 'Total Biaya Non-kas');
      $sheet->setCellValue('B' . $row, $reportData['non_cash_costs']['total']['previous']);
      $sheet->setCellValue('C' . $row, $reportData['non_cash_costs']['total']['current']);
      $sheet->setCellValue('D' . $row, $reportData['non_cash_costs']['total']['ytd']);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      // Summary Section
      $row++;
      $sheet->setCellValue('A' . $row, 'Total Biaya');
      $sheet->setCellValue('B' . $row, $reportData['total_cost']['previous']);
      $sheet->setCellValue('C' . $row, $reportData['total_cost']['current']);
      $sheet->setCellValue('D' . $row, $reportData['total_cost']['ytd']);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
      $row++;

      $sheet->setCellValue('A' . $row, 'Laba Bersih');
      $sheet->setCellValue('B' . $row, $reportData['net_profit']['previous']);
      $sheet->setCellValue('C' . $row, $reportData['net_profit']['current']);
      $sheet->setCellValue('D' . $row, $reportData['net_profit']['ytd']);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
      $sheet->getStyle('A' . $row . ':D' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');

      // Format numbers
      $sheet->getStyle('B6:D' . $row)->getNumberFormat()->setFormatCode('#,##0');
   }

   private function downloadPdf($reportData, $filters)
   {
      $pdf = Pdf::loadView('reports.income', [
         'reportData' => $reportData,
         'filters' => $filters,
      ]);
      
      return $pdf->download('laba-rugi-' . date('Y-m-d') . '.pdf');
   }

   private function getAllAccountBalances($startDate, $endDate, $filters)
   {
      $query = JournalEntry::join('accounts', 'journal_entries.account_id', '=', 'accounts.id')
         ->where('accounts.is_parent', false)
         ->whereHas('journal', function ($query) use ($startDate, $endDate, $filters) {
            $query->whereDate('date', '>=', $startDate)
                  ->whereDate('date', '<=', $endDate);
            
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

   private function mapAccountBalances($account, $currentBalances, $previousBalances, $ytdBalances, $isDetailed)
   {
      $balances = ['current' => 0, 'previous' => 0, 'ytd' => 0];
      
      if ($isDetailed || !$account->is_parent) {
          // For detailed report or non-parent accounts, use direct balance
          $balances = [
              'current' => $currentBalances[$account->id]['balance'] ?? 0,
              'previous' => $previousBalances[$account->id]['balance'] ?? 0,
              'ytd' => $ytdBalances[$account->id]['balance'] ?? 0,
          ];
      } else {
          // For summary report with parent accounts, sum all descendant balances
          $descendantIds = $this->getDescendantIds($account);
          
          foreach ($descendantIds as $id) {
              $balances['current'] += $currentBalances[$id]['balance'] ?? 0;
              $balances['previous'] += $previousBalances[$id]['balance'] ?? 0;
              $balances['ytd'] += $ytdBalances[$id]['balance'] ?? 0;
          }
      }

      return [
          'account' => $account,
          'balances' => $balances
      ];
   }
}