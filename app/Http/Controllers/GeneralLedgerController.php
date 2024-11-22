<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GeneralLedgerController extends Controller
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
        
        $accountQuery = Account::query();
        if (!empty($filters['company_id'])) {
            $accountQuery->whereHas('companies', function ($q) use ($filters) {
                $q->whereIn('companies.id', $filters['company_id']);
            });
        }
        $accounts = $accountQuery->orderBy('code', 'asc')->get();

        $ledgerData = [];
        
        if (!empty($filters['account_id']) && !empty($filters['start_date']) && !empty($filters['end_date'])) {
            foreach ($filters['account_id'] as $accountId) {
                $account = Account::find($accountId);
                $accountIds = $account->getAllDescendantIds()->push($accountId);
                
                // Get opening balance
                $openingBalance = $account->getBalanceForDateAndBranches(
                    date('Y-m-d', strtotime($filters['start_date'] . ' -1 day')),
                    $filters['branch_id'] ?? [],
                    $filters['company_id'] ?? []
                );

                // Get mutations
                $mutations = JournalEntry::whereIn('account_id', $accountIds)
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

                $ledgerData[] = [
                    'account' => $account,
                    'opening_balance' => $openingBalance,
                    'mutations' => $mutations,
                    'ending_balance' => $account->getBalanceForDateAndBranches(
                        $filters['end_date'],
                        $filters['branch_id'] ?? [],
                        $filters['company_id'] ?? []
                    ),
                ];
            }
        }

        $response = [   
            'companies' => $companies,
            'branches' => $branches,
            'accounts' => $accounts,
            'filters' => $filters,
            'ledgerData' => $ledgerData,
        ];

        return Inertia::render('Reports/GeneralLedger', $response);
    }

    public function download(Request $request)
    {
        $filters = $request->all();
        $format = $filters['format'] ?? 'xlsx';
        
        // Get the data
        $ledgerData = $this->getLedgerData($filters);
        
        switch ($format) {
            case 'xlsx':
                return $this->downloadExcel($ledgerData, $filters);
            case 'pdf':
                return $this->downloadPdf($ledgerData, $filters);
            default:
                abort(400, 'Invalid format');
        }
    }

    private function getLedgerData($filters)
    {
        $ledgerData = [];
        
        if (!empty($filters['account_id']) && !empty($filters['start_date']) && !empty($filters['end_date'])) {
            foreach ($filters['account_id'] as $accountId) {
                $account = Account::find($accountId);
                $accountIds = $account->getAllDescendantIds()->push($accountId);
                
                // Get opening balance
                $openingBalance = $account->getBalanceForDateAndBranches(
                    date('Y-m-d', strtotime($filters['start_date'] . ' -1 day')),
                    $filters['branch_id'] ?? [],
                    $filters['company_id'] ?? []
                );

                // Get mutations
                $mutations = JournalEntry::whereIn('account_id', $accountIds)
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

                $ledgerData[] = [
                    'account' => $account,
                    'opening_balance' => $openingBalance,
                    'mutations' => $mutations,
                    'ending_balance' => $account->getBalanceForDateAndBranches(
                        $filters['end_date'],
                        $filters['branch_id'] ?? [],
                        $filters['company_id'] ?? []
                    ),
                ];
            }
        }
        
        return $ledgerData;
    }

    private function downloadExcel($ledgerData, $filters)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $sheet->setCellValue('A1', 'Buku Besar');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFont()->setSize(16);
        
        $sheet->setCellValue('A2', date('d/m/Y', strtotime($filters['start_date'])) . ' s/d ' . date('d/m/Y', strtotime($filters['end_date'])));
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2:F2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:F2')->getFont()->setSize(12);

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
            $sheet->mergeCells('A3:F3');
            $sheet->getStyle('A3:F3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A3:F3')->getFont()->setSize(12);

            $nextRow = 4;
        }

        $sheet->setCellValue('A' . $nextRow, 'Cabang: ' . (!empty($filters['branch_id']) ? $branches?->pluck('name')->implode(', ') : 'Semua Cabang'));
        $sheet->mergeCells('A' . $nextRow . ':F' . $nextRow);
        $sheet->getStyle('A' . $nextRow . ':F' . $nextRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $nextRow . ':F' . $nextRow)->getFont()->setSize(12);
        
        $row = $nextRow + 2;
        foreach ($ledgerData as $data) {
            $sheet->setCellValue('A' . $row, $data['account']['code'] . ' - ' . $data['account']['name']);
            $row++;
            
            // Headers
            $sheet->setCellValue('A' . $row, 'Tanggal');
            $sheet->setCellValue('B' . $row, 'No. Jurnal');
            $sheet->setCellValue('C' . $row, 'Keterangan');
            $sheet->setCellValue('D' . $row, 'Debet');
            $sheet->setCellValue('E' . $row, 'Kredit');
            $sheet->setCellValue('F' . $row, 'Saldo');
            $row++;
            
            // Opening balance
            $sheet->setCellValue('A' . $row, 'Saldo Awal');
            $sheet->setCellValue('F' . $row, $data['opening_balance']);
            $row++;

            $balance = $data['opening_balance'];
            
            // Mutations
            foreach ($data['mutations'] as $mutation) {
                $sheet->setCellValue('A' . $row, $mutation['journal']['date']);
                $sheet->setCellValue('B' . $row, $mutation['journal']['journal_number']);
                $sheet->setCellValue('C' . $row, $mutation['journal']['description']);
                $sheet->setCellValue('D' . $row, $mutation['primary_currency_debit']);
                $sheet->setCellValue('E' . $row, $mutation['primary_currency_credit']);
                $sheet->setCellValue('F' . $row, $balance += ($data['account']->balance_type === 'debit' ? $mutation['primary_currency_debit'] - $mutation['primary_currency_credit'] : $mutation['primary_currency_credit'] - $mutation['primary_currency_debit']));
                $row++;
            }
            
            // Ending balance
            $sheet->setCellValue('A' . $row, 'Saldo Akhir');
            $sheet->setCellValue('D' . $row, $data['mutations']->sum('primary_currency_debit'));
            $sheet->setCellValue('E' . $row, $data['mutations']->sum('primary_currency_credit'));
            $sheet->setCellValue('F' . $row, $balance);
            $row += 2;
        }
        
        $writer = new Xlsx($spreadsheet);
        
        $filename = 'buku-besar-' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }

    private function downloadPdf($ledgerData, $filters)
    {
        $pdf = Pdf::loadView('reports.general-ledger', [
            'ledgerData' => $ledgerData,
            'filters' => $filters
        ]);
        
        return $pdf->download('buku-besar-' . date('Y-m-d') . '.pdf');
    }
}