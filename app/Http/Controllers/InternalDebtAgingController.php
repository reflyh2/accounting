<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Branch;
use App\Models\InternalDebt;
use App\Models\InternalDebtPaymentDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class InternalDebtAgingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all();
        if (empty($filters['end_date'])) {
            $filters['end_date'] = date('Y-m-d');
        }
        // Normalize single selects
        $filters['company_id'] = isset($filters['company_id']) && $filters['company_id'] !== '' ? (int) $filters['company_id'] : null;
        $filters['branch_id'] = isset($filters['branch_id']) && $filters['branch_id'] !== '' ? (int) $filters['branch_id'] : null;

        $companies = Company::orderBy('name', 'asc')->get();

        $branchQuery = Branch::query();
        if (!empty($filters['company_id'])) {
            $branchQuery->whereHas('branchGroup', function ($q) use ($filters) {
                $q->where('company_id', $filters['company_id']);
            });
        }
        $branches = $branchQuery->orderBy('name', 'asc')->get();

        $reportData = null;
        if (!empty($filters['branch_id'])) {
            $reportData = $this->getReportData($filters);
        }

        return Inertia::render('Reports/InternalDebtAging', [
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
        $selectedBranchId = (int) ($filters['branch_id'] ?? 0);

        // compute function specialized for payables or receivables relative to the selected branch
        $compute = function (string $type) use ($end, $selectedBranchId) {
            $debtQuery = InternalDebt::query()
                ->select([
                    'internal_debts.id',
                    'internal_debts.branch_id',
                    'internal_debts.counterparty_branch_id',
                    'internal_debts.due_date',
                    'internal_debts.primary_currency_amount',
                    'internal_debts.issue_date',
                ])
                ->whereDate('issue_date', '<=', $end->toDateString());

            // Payables: debts of selected branch (branch_id = selected); group by counterparty branch
            // Receivables: debts owed to selected branch (counterparty_branch_id = selected); group by originating branch_id
            if ($type === 'payable') {
                $debtQuery->where('branch_id', $selectedBranchId);
            } else {
                $debtQuery->where('counterparty_branch_id', $selectedBranchId);
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

            $paidByDebt = InternalDebtPaymentDetail::query()
                ->select([
                    'internal_debt_payment_details.internal_debt_id',
                    DB::raw('SUM(internal_debt_payment_details.primary_currency_amount) as total_amount'),
                ])
                ->join('internal_debt_payments as idp', 'idp.id', '=', 'internal_debt_payment_details.internal_debt_payment_id')
                ->whereIn('internal_debt_payment_details.internal_debt_id', $debtIds)
                ->where(function ($q) use ($end) {
                    $q->where(function ($q1) use ($end) {
                        $q1->whereIn('idp.payment_method', ['cek', 'giro'])
                            ->whereNotNull('idp.withdrawal_date')
                            ->whereDate('idp.withdrawal_date', '<=', $end->toDateString());
                    })->orWhere(function ($q2) use ($end) {
                        $q2->whereIn('idp.payment_method', ['cash', 'transfer'])
                            ->whereDate('idp.payment_date', '<=', $end->toDateString());
                    });
                })
                ->whereNull('internal_debt_payment_details.deleted_at')
                ->whereNull('idp.deleted_at')
                ->groupBy('internal_debt_payment_details.internal_debt_id')
                ->pluck('total_amount', 'internal_debt_payment_details.internal_debt_id');

            // Buckets keyed by the "other branch" id
            $otherBranchBuckets = [];

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

                // pick the other branch based on type
                $otherBranchId = $type === 'payable' ? $debt->counterparty_branch_id : $debt->branch_id;

                if (!isset($otherBranchBuckets[$otherBranchId])) {
                    $otherBranchBuckets[$otherBranchId] = [
                        'not_yet_due' => 0,
                        'days_1_30' => 0,
                        'days_31_60' => 0,
                        'days_61_90' => 0,
                        'days_91_plus' => 0,
                        'total' => 0,
                    ];
                }
                $otherBranchBuckets[$otherBranchId][$bucket] += $outstanding;
                $otherBranchBuckets[$otherBranchId]['total'] += $outstanding;
            }

            $otherBranchIds = array_keys($otherBranchBuckets);
            if (empty($otherBranchIds)) {
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

            $branches = Branch::whereIn('id', $otherBranchIds)
                ->with('branchGroup.company')
                ->orderBy('name', 'asc')
                ->get()
                ->keyBy('id');
            $rows = [];
            $totals = [
                'not_yet_due' => 0,
                'days_1_30' => 0,
                'days_31_60' => 0,
                'days_61_90' => 0,
                'days_91_plus' => 0,
                'total' => 0,
            ];

            foreach ($branches as $branchId => $branch) {
                $b = $otherBranchBuckets[$branchId] ?? null;
                if (!$b || ($b['total'] ?? 0) <= 0) {
                    continue;
                }
                $rows[] = [
                    'branch' => $branch,
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
        };

        $payables = $compute('payable');
        $receivables = $compute('receivable');

        // Build combined: receivables - payables per branch
        $branchMap = [];
        foreach ($receivables['rows'] as $row) {
            $branchMap[$row['branch']->id] = [
                'branch' => $row['branch'],
                'not_yet_due' => $row['not_yet_due'],
                'days_1_30' => $row['days_1_30'],
                'days_31_60' => $row['days_31_60'],
                'days_61_90' => $row['days_61_90'],
                'days_91_plus' => $row['days_91_plus'],
                'total' => $row['total'],
            ];
        }
        foreach ($payables['rows'] as $row) {
            $id = $row['branch']->id;
            if (!isset($branchMap[$id])) {
                $branchMap[$id] = [
                    'branch' => $row['branch'],
                    'not_yet_due' => 0,
                    'days_1_30' => 0,
                    'days_31_60' => 0,
                    'days_61_90' => 0,
                    'days_91_plus' => 0,
                    'total' => 0,
                ];
            }
            $branchMap[$id]['not_yet_due'] -= $row['not_yet_due'];
            $branchMap[$id]['days_1_30'] -= $row['days_1_30'];
            $branchMap[$id]['days_31_60'] -= $row['days_31_60'];
            $branchMap[$id]['days_61_90'] -= $row['days_61_90'];
            $branchMap[$id]['days_91_plus'] -= $row['days_91_plus'];
            $branchMap[$id]['total'] -= $row['total'];
        }

        $combinedRows = [];
        $combinedTotals = [
            'not_yet_due' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_91_plus' => 0,
            'total' => 0,
        ];
        foreach ($branchMap as $row) {
            // Hide rows where receivables and payables are both zero (net and components are zero)
            if (abs($row['not_yet_due']) < 0.000001
                && abs($row['days_1_30']) < 0.000001
                && abs($row['days_31_60']) < 0.000001
                && abs($row['days_61_90']) < 0.000001
                && abs($row['days_91_plus']) < 0.000001
                && abs($row['total']) < 0.000001) {
                continue;
            }
            $combinedRows[] = $row;
            $combinedTotals['not_yet_due'] += $row['not_yet_due'];
            $combinedTotals['days_1_30'] += $row['days_1_30'];
            $combinedTotals['days_31_60'] += $row['days_31_60'];
            $combinedTotals['days_61_90'] += $row['days_61_90'];
            $combinedTotals['days_91_plus'] += $row['days_91_plus'];
            $combinedTotals['total'] += $row['total'];
        }

        return [
            'payables' => $payables,
            'receivables' => $receivables,
            'combined' => [
                'rows' => $combinedRows,
                'totals' => $combinedTotals,
            ],
        ];
    }

    public function download(Request $request)
    {
        $filters = $request->all();
        if (empty($filters['end_date'])) {
            $filters['end_date'] = date('Y-m-d');
        }
        $filters['company_id'] = isset($filters['company_id']) && $filters['company_id'] !== '' ? (int) $filters['company_id'] : null;
        $filters['branch_id'] = isset($filters['branch_id']) && $filters['branch_id'] !== '' ? (int) $filters['branch_id'] : null;

        $reportData = !empty($filters['branch_id']) ? $this->getReportData($filters) : [
            'payables' => ['rows' => [], 'totals' => ['not_yet_due'=>0,'days_1_30'=>0,'days_31_60'=>0,'days_61_90'=>0,'days_91_plus'=>0,'total'=>0]],
            'receivables' => ['rows' => [], 'totals' => ['not_yet_due'=>0,'days_1_30'=>0,'days_31_60'=>0,'days_61_90'=>0,'days_91_plus'=>0,'total'=>0]],
            'combined' => ['rows' => [], 'totals' => ['not_yet_due'=>0,'days_1_30'=>0,'days_31_60'=>0,'days_61_90'=>0,'days_91_plus'=>0,'total'=>0]],
        ];

        if (($request->format ?? 'xlsx') === 'pdf') {
            return $this->downloadPdf($reportData, $filters);
        }
        return $this->downloadExcel($reportData, $filters);
    }

    private function downloadPdf(array $reportData, array $filters)
    {
        $pdf = Pdf::loadView('reports.internal-debt-aging', [
            'reportData' => $reportData,
            'filters' => $filters,
        ]);
        return $pdf->download('umur-hutang-piutang-internal-' . date('Y-m-d') . '.pdf');
    }

    private function downloadExcel(array $reportData, array $filters)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(18);

        // Title
        $sheet->setCellValue('A1', 'Umur Hutang/Piutang Internal');
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
            $companies = Company::where('id', $filters['company_id'])->get();
        }
        if (!empty($filters['branch_id'])) {
            $branches = Branch::where('id', $filters['branch_id'])->get();
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

        $row = $nextRow + 2;
        $writeSection = function (string $title, array $data) use (&$sheet, &$row) {
            // Section title
            $sheet->setCellValue('A' . $row, $title);
            $sheet->mergeCells('A' . $row . ':G' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFont()->setSize(13);
            $row += 1;

            // Headers
            $sheet->setCellValue('A' . $row, 'Cabang');
            $sheet->setCellValue('B' . $row, 'Belum Jatuh Tempo');
            $sheet->setCellValue('C' . $row, '1-30 Hari');
            $sheet->setCellValue('D' . $row, '31-60 Hari');
            $sheet->setCellValue('E' . $row, '61-90 Hari');
            $sheet->setCellValue('F' . $row, '91+ Hari');
            $sheet->setCellValue('G' . $row, 'Total');
            $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
            $row++;

            foreach ($data['rows'] as $r) {
                // Prefer array access (Inertia serialization), fallback to model
                $companyName = '';
                if (is_array($r['branch'] ?? null)) {
                    $companyName = $r['branch']['branch_group']['company']['name'] ?? '';
                    $nameOnly = $r['branch']['name'] ?? '';
                } else {
                    $companyName = $r['branch']->branchGroup?->company?->name ?? '';
                    $nameOnly = $r['branch']->name ?? '';
                }
                $name = trim(($companyName ? ($companyName . ' - ') : '') . $nameOnly);
                $sheet->setCellValue('A' . $row, $name);
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
            $sheet->setCellValue('B' . $row, $data['totals']['not_yet_due'] ?? 0);
            $sheet->setCellValue('C' . $row, $data['totals']['days_1_30'] ?? 0);
            $sheet->setCellValue('D' . $row, $data['totals']['days_31_60'] ?? 0);
            $sheet->setCellValue('E' . $row, $data['totals']['days_61_90'] ?? 0);
            $sheet->setCellValue('F' . $row, $data['totals']['days_91_plus'] ?? 0);
            $sheet->setCellValue('G' . $row, $data['totals']['total'] ?? 0);
            $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
            $row += 2;
        };

        // Helper to set color for a cell based on mode/value
        $setCellColor = function ($sheet, string $cell, string $mode, $value = null) {
            $green = '16A34A';
            $red = 'DC2626';
            $color = null;
            if ($mode === 'receivable') {
                $color = $green;
            } elseif ($mode === 'payable') {
                $color = $red;
            } elseif ($mode === 'combined') {
                if ($value > 0) $color = $green;
                elseif ($value < 0) $color = $red;
            }
            if ($color) {
                $sheet->getStyle($cell)->getFont()->getColor()->setRGB($color);
            }
        };

        $writeSection = function (string $title, array $data, string $mode) use (&$sheet, &$row, $setCellColor) {
            // Section title
            $sheet->setCellValue('A' . $row, $title);
            $sheet->mergeCells('A' . $row . ':G' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getFont()->setSize(13);
            $row += 1;

            // Headers
            $sheet->setCellValue('A' . $row, 'Cabang');
            $sheet->setCellValue('B' . $row, 'Belum Jatuh Tempo');
            $sheet->setCellValue('C' . $row, '1-30 Hari');
            $sheet->setCellValue('D' . $row, '31-60 Hari');
            $sheet->setCellValue('E' . $row, '61-90 Hari');
            $sheet->setCellValue('F' . $row, '91+ Hari');
            $sheet->setCellValue('G' . $row, 'Total');
            $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
            $row++;

            foreach ($data['rows'] as $r) {
                // Prefer array access (Inertia serialization), fallback to model
                $companyName = '';
                if (is_array($r['branch'] ?? null)) {
                    $companyName = $r['branch']['branch_group']['company']['name'] ?? '';
                    $nameOnly = $r['branch']['name'] ?? '';
                } else {
                    $companyName = $r['branch']->branchGroup?->company?->name ?? '';
                    $nameOnly = $r['branch']->name ?? '';
                }
                $name = trim(($companyName ? ($companyName . ' - ') : '') . $nameOnly);
                $sheet->setCellValue('A' . $row, $name);

                $vals = [
                    'B' => $r['not_yet_due'] ?? 0,
                    'C' => $r['days_1_30'] ?? 0,
                    'D' => $r['days_31_60'] ?? 0,
                    'E' => $r['days_61_90'] ?? 0,
                    'F' => $r['days_91_plus'] ?? 0,
                    'G' => $r['total'] ?? 0,
                ];
                foreach ($vals as $col => $val) {
                    $sheet->setCellValue($col . $row, $val);
                    $setCellColor($sheet, $col . $row, $mode, $val);
                }
                $row++;
            }

            // Totals
            $sheet->setCellValue('A' . $row, 'TOTAL');
            $totVals = [
                'B' => $data['totals']['not_yet_due'] ?? 0,
                'C' => $data['totals']['days_1_30'] ?? 0,
                'D' => $data['totals']['days_31_60'] ?? 0,
                'E' => $data['totals']['days_61_90'] ?? 0,
                'F' => $data['totals']['days_91_plus'] ?? 0,
                'G' => $data['totals']['total'] ?? 0,
            ];
            foreach ($totVals as $col => $val) {
                $sheet->setCellValue($col . $row, $val);
                $setCellColor($sheet, $col . $row, $mode, $val);
            }
            $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
            $row += 2;
        };

        // Receivables first (green), then Payables (red), then Combined (conditional)
        $writeSection('Piutang Internal', $reportData['receivables'], 'receivable');
        $writeSection('Hutang Internal', $reportData['payables'], 'payable');
        $writeSection('Gabungan (Piutang - Hutang)', $reportData['combined'], 'combined');

        // Number format
        $sheet->getStyle('B7:G' . $row)->getNumberFormat()->setFormatCode('#,##0');

        $writer = new Xlsx($spreadsheet);
        $filename = 'umur-hutang-piutang-internal-' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}


