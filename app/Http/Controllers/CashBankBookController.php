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

class CashBankBookController extends Controller
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
      
      $query = Branch::query();
      if (!empty($filters['company_id'])) {
         $query->whereHas('branchGroup', function ($q) use ($filters) {
               $q->whereIn('company_id', $filters['company_id']);
         });
      }
      $branches = $query->orderBy('name', 'asc')->get();
      
      $accountQuery = Account::query()
         ->where('type', 'kas_bank')
         ->where('is_parent', false);

      if (!empty($filters['company_id'])) {
         $accountQuery->whereHas('companies', function ($q) use ($filters) {
               $q->whereIn('companies.id', $filters['company_id']);
         });
      }
      $accounts = $accountQuery->orderBy('code', 'asc')->get();

      $bookData = [];
      
      if (!empty($filters['account_id']) && !empty($filters['start_date']) && !empty($filters['end_date'])) {
         foreach ($filters['account_id'] as $accountId) {
               $account = Account::find($accountId);
               
               // Group mutations by currency
               $currencies = $account->currencies;
               
               foreach ($currencies as $currency) {
                  // Get opening balance for this currency
                  $openingBalance = $account->getBalanceForDateAndBranchesAndCurrency(
                     date('Y-m-d', strtotime($filters['start_date'] . ' -1 day')),
                     $filters['branch_id'] ?? [],
                     $filters['company_id'] ?? [],
                     $currency->id
                  );

                  // Get primary currency opening balance
                  $primaryOpeningBalance = $account->getBalanceForDateAndBranches(
                     date('Y-m-d', strtotime($filters['start_date'] . ' -1 day')),
                     $filters['branch_id'] ?? [],
                     $filters['company_id'] ?? [],
                     $currency->id
                  );

                  // Get mutations
                  $mutations = JournalEntry::where('account_id', $accountId)
                     ->where('currency_id', $currency->id)
                     ->whereHas('journal', function ($query) use ($filters) {
                           $query->whereBetween('date', [$filters['start_date'], $filters['end_date']])
                              ->when(!empty($filters['company_id']), function ($q) use ($filters) {
                                 $q->whereHas('branch.branchGroup', function ($q) use ($filters) {
                                       $q->whereIn('company_id', $filters['company_id']);
                                 });
                              })
                              ->when(!empty($filters['branch_id']), function ($q) use ($filters) {
                                 $q->whereIn('branch_id', $filters['branch_id']);
                              });
                     })
                     ->with(['journal', 'currency'])
                     ->orderBy('created_at')
                     ->get();

                  $bookData[] = [
                     'account' => $account,
                     'currency' => $currency,
                     'opening_balance' => $openingBalance,
                     'primary_opening_balance' => $primaryOpeningBalance,
                     'mutations' => $mutations,
                     'ending_balance' => $account->getBalanceForDateAndBranchesAndCurrency(
                           $filters['end_date'],
                           $filters['branch_id'] ?? [],
                           $filters['company_id'] ?? [],
                           $currency->id
                     ),
                     'primary_ending_balance' => $account->getBalanceForDateAndBranches(
                           $filters['end_date'],
                           $filters['branch_id'] ?? [],
                           $filters['company_id'] ?? [],
                           $currency->id
                     ),
                     'combined_primary_ending_balance' => $account->getBalanceForDateAndBranches(
                           $filters['end_date'],
                           $filters['branch_id'] ?? [],
                           $filters['company_id'] ?? []
                     ),
                  ];
               }
         }
      }

      $primaryCurrency = Currency::where('is_primary', true)->first();

      return Inertia::render('Reports/CashBankBook', [
         'companies' => $companies,
         'branches' => $branches,
         'accounts' => $accounts,
         'filters' => $filters,
         'bookData' => $bookData,
         'primaryCurrency' => $primaryCurrency,
      ]);
   }

   public function download(Request $request)
   {
      $filters = $request->all();
      $format = $filters['format'] ?? 'xlsx';

      // Get the data
      $bookData = $this->getBookData($filters);

      $primaryCurrency = Currency::where('is_primary', true)->first();

      switch ($format) {
         case 'xlsx':
            return $this->downloadExcel($bookData, $filters, $primaryCurrency);
         case 'pdf':
            return $this->downloadPdf($bookData, $filters, $primaryCurrency);
         default:
               abort(400, 'Invalid format');
      }
   }

   private function getBookData($filters)
   {
      $bookData = [];
      
      if (!empty($filters['account_id'])) {
         foreach ($filters['account_id'] as $accountId) {
               $account = Account::find($accountId);
               $currencies = $account->currencies;
               
               foreach ($currencies as $currency) {
                  $openingBalance = $account->getBalanceForDateAndBranchesAndCurrency(
                     date('Y-m-d', strtotime($filters['start_date'] . ' -1 day')),
                     $filters['branch_id'] ?? [],
                     $filters['company_id'] ?? [],
                     $currency->id
                  );

                  $primaryOpeningBalance = $account->getBalanceForDateAndBranches(
                     date('Y-m-d', strtotime($filters['start_date'] . ' -1 day')),
                     $filters['branch_id'] ?? [],
                     $filters['company_id'] ?? [],
                     $currency->id
                  );

                  $mutations = JournalEntry::where('account_id', $accountId)
                     ->where('currency_id', $currency->id)
                     ->whereHas('journal', function ($query) use ($filters) {
                           $query->whereBetween('date', [$filters['start_date'], $filters['end_date']])
                              ->when(!empty($filters['company_id']), function ($q) use ($filters) {
                                 $q->whereHas('branch.branchGroup', function ($q) use ($filters) {
                                       $q->whereIn('company_id', $filters['company_id']);
                                 });
                              })
                              ->when(!empty($filters['branch_id']), function ($q) use ($filters) {
                                 $q->whereIn('branch_id', $filters['branch_id']);
                              });
                     })
                     ->with(['journal', 'currency'])
                     ->orderBy('created_at')
                     ->get();

                  $bookData[] = [
                     'account' => $account,
                     'currency' => $currency,
                     'opening_balance' => $openingBalance,
                     'primary_opening_balance' => $primaryOpeningBalance,
                     'mutations' => $mutations,
                     'ending_balance' => $account->getBalanceForDateAndBranchesAndCurrency(
                           $filters['end_date'],
                           $filters['branch_id'] ?? [],
                           $filters['company_id'] ?? [],
                           $currency->id
                     ),
                     'primary_ending_balance' => $account->getBalanceForDateAndBranches(
                           $filters['end_date'],
                           $filters['branch_id'] ?? [],
                           $filters['company_id'] ?? [],
                           $currency->id
                     ),
                     'combined_primary_ending_balance' => $account->getBalanceForDateAndBranches(
                           $filters['end_date'],
                           $filters['branch_id'] ?? [],
                           $filters['company_id'] ?? []
                     ),
                  ];
               }
         }
      }
      
      return $bookData;
   }

   private function downloadExcel($bookData, $filters, $primaryCurrency)
   {
      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();
      
      // Set headers
      $sheet->setCellValue('A1', 'Buku Kas & Bank');
      $sheet->mergeCells('A1:I1');
      $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A1:I1')->getFont()->setBold(true);
      $sheet->getStyle('A1:I1')->getFont()->setSize(16);
      
      $sheet->setCellValue('A2', date('d/m/Y', strtotime($filters['start_date'])) . ' s/d ' . date('d/m/Y', strtotime($filters['end_date'])));
      $sheet->mergeCells('A2:I2');
      $sheet->getStyle('A2:I2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A2:I2')->getFont()->setSize(12);

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
         $sheet->mergeCells('A3:I3');
         $sheet->getStyle('A3:I3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
         $sheet->getStyle('A3:I3')->getFont()->setSize(12);
         $nextRow = 4;
      }

      $sheet->setCellValue('A' . $nextRow, 'Cabang: ' . (!empty($filters['branch_id']) ? $branches?->pluck('name')->implode(', ') : 'Semua Cabang'));
      $sheet->mergeCells('A' . $nextRow . ':I' . $nextRow);
      $sheet->getStyle('A' . $nextRow . ':I' . $nextRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle('A' . $nextRow . ':I' . $nextRow)->getFont()->setSize(12);
      
      $row = $nextRow + 2;
      foreach ($bookData as $data) {
         $sheet->setCellValue('A' . $row, $data['account']['code'] . ' - ' . $data['account']['name'] . ' (' . $data['currency']['code'] . ')');
         $row++;
         
         // Headers
         $sheet->setCellValue('A' . $row, 'Tanggal');
         $sheet->setCellValue('B' . $row, 'No. Jurnal');
         $sheet->setCellValue('C' . $row, 'Keterangan');
         $sheet->setCellValue('D' . $row, 'Masuk');
         $sheet->setCellValue('E' . $row, 'Keluar');
         $sheet->setCellValue('F' . $row, 'Saldo');
         $sheet->setCellValue('G' . $row, 'Kurs');
         $sheet->setCellValue('H' . $row, 'Perubahan (' . $primaryCurrency->code . ')');
         $sheet->setCellValue('I' . $row, 'Saldo (' . $primaryCurrency->code . ')');
         $row++;
         
         // Opening balance
         $sheet->setCellValue('A' . $row, 'Saldo Awal');
         $sheet->setCellValue('F' . $row, $data['opening_balance']);
         $sheet->setCellValue('I' . $row, $data['primary_opening_balance']);
         $row++;

         $balance = $data['opening_balance'];
         $primaryBalance = $data['primary_opening_balance'];
         
         // Mutations
         foreach ($data['mutations'] as $mutation) {
               $sheet->setCellValue('A' . $row, $mutation['journal']['date']);
               $sheet->setCellValue('B' . $row, $mutation['journal']['journal_number']);
               $sheet->setCellValue('C' . $row, $mutation['journal']['description']);
               $sheet->setCellValue('D' . $row, $mutation['debit']);
               $sheet->setCellValue('E' . $row, $mutation['credit']);
               
               $balance += ($data['account']['balance_type'] === 'debit' 
                  ? $mutation['debit'] - $mutation['credit']
                  : $mutation['credit'] - $mutation['debit']);
               $sheet->setCellValue('F' . $row, $balance);
               
               $sheet->setCellValue('G' . $row, $mutation['exchange_rate']);
               
               $primaryChange = $data['account']['balance_type'] === 'debit'
                  ? $mutation['primary_currency_debit'] - $mutation['primary_currency_credit']
                  : $mutation['primary_currency_credit'] - $mutation['primary_currency_debit'];
               $sheet->setCellValue('H' . $row, $primaryChange);
               
               $primaryBalance += $primaryChange;
               $sheet->setCellValue('I' . $row, $primaryBalance);
               
               $row++;
         }
         
         // Ending balance
         $sheet->setCellValue('A' . $row, 'Saldo Akhir');
         $sheet->setCellValue('D' . $row, $data['mutations']->sum('debit'));
         $sheet->setCellValue('E' . $row, $data['mutations']->sum('credit'));
         $sheet->setCellValue('F' . $row, $data['ending_balance']);
         $sheet->setCellValue('G' . $row, $data['primary_ending_balance'] / $data['ending_balance']);
         $sheet->setCellValue('H' . $row, $data['mutations']->sum(function($m) use ($data) {
               return $data['account']['balance_type'] === 'debit'
                  ? $m['primary_currency_debit'] - $m['primary_currency_credit']
                  : $m['primary_currency_credit'] - $m['primary_currency_debit'];
         }));
         $sheet->setCellValue('I' . $row, $data['primary_ending_balance']);
         $row++;
         
         // Combined ending balance
         $sheet->setCellValue('A' . $row, 'Saldo Akhir Gabungan ' . $data['account']['name']);
         $sheet->mergeCells('A' . $row . ':H' . $row);
         $sheet->setCellValue('I' . $row, $data['combined_primary_ending_balance']);
         $row += 2;
      }
      
      $writer = new Xlsx($spreadsheet);
      
      $filename = 'buku-kas-bank-' . date('Y-m-d') . '.xlsx';
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment;filename="' . $filename . '"');
      header('Cache-Control: max-age=0');
      
      $writer->save('php://output');
      exit;
   }

   private function downloadPdf($bookData, $filters, $primaryCurrency)
   {
      $pdf = Pdf::loadView('reports.cash-bank-book', [
         'bookData' => $bookData,
         'filters' => $filters,
         'primaryCurrency' => $primaryCurrency
      ]);
      
      return $pdf->download('buku-kas-bank-' . date('Y-m-d') . '.pdf');
   }
}