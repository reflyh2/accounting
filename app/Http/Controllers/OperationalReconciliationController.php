<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OperationalReconciliationController extends Controller
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
        if (! empty($filters['company_id'])) {
            $query->whereHas('branchGroup', fn ($q) => $q->whereIn('company_id', $filters['company_id']));
        }
        $branches = $query->orderBy('name', 'asc')->get();

        $summaryData = $this->getSummaryData($filters);
        $chartData = $this->getChartData($filters);

        return Inertia::render('Reports/AccountingOverview', [
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'summaryData' => $summaryData,
            'chartData' => $chartData,
        ]);
    }

    private function getSummaryData(array $filters): array
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $periodBalances = $this->getAccountBalancesForPeriod($startDate, $endDate, $filters);
        $cumulativeBalances = $this->getAccountBalancesCumulative($endDate, $filters);

        // Income statement figures (period)
        $revenue = $this->sumByTypes($periodBalances, ['pendapatan']);
        $otherRevenue = $this->sumByTypes($periodBalances, ['pendapatan_lainnya']);
        $cogs = $this->sumByTypes($periodBalances, ['beban_pokok_penjualan']);
        $operationalExpenses = $this->sumByTypes($periodBalances, ['beban']);
        $otherExpenses = $this->sumByTypes($periodBalances, ['beban_lainnya']);
        $depreciation = $this->sumByTypes($periodBalances, ['beban_penyusutan']);
        $amortization = $this->sumByTypes($periodBalances, ['beban_amortisasi']);

        $grossProfit = $revenue - $cogs;
        $totalExpenses = $operationalExpenses + $otherExpenses + $depreciation + $amortization;
        $netProfit = $grossProfit + $otherRevenue - $totalExpenses;
        $grossMargin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;
        $netMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;

        // Balance sheet figures (cumulative to end date)
        $totalAssets = $this->sumByTypes($cumulativeBalances, [
            'kas_bank', 'piutang_usaha', 'piutang_usaha_lainnya', 'persediaan',
            'biaya_dibayar_dimuka', 'aset_tetap', 'aset_tidak_berwujud',
            'investasi_jangka_panjang', 'aset_lainnya',
        ]);

        $totalLiabilities = $this->sumByTypes($cumulativeBalances, [
            'hutang_usaha', 'hutang_usaha_lainnya',
            'liabilitas_jangka_pendek', 'liabilitas_jangka_panjang',
            'pendapatan_diterima_dimuka',
        ]);

        $totalEquity = $this->sumByTypes($cumulativeBalances, ['modal', 'laba_ditahan']);

        $cashAndBank = $this->sumByTypes($cumulativeBalances, ['kas_bank']);
        $receivables = $this->sumByTypes($cumulativeBalances, ['piutang_usaha', 'piutang_usaha_lainnya']);
        $payables = $this->sumByTypes($cumulativeBalances, ['hutang_usaha', 'hutang_usaha_lainnya']);
        $inventory = $this->sumByTypes($cumulativeBalances, ['persediaan']);

        return [
            // Income statement
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => round($grossMargin, 1),
            'operational_expenses' => $operationalExpenses,
            'other_revenue' => $otherRevenue,
            'other_expenses' => $otherExpenses,
            'depreciation' => $depreciation + $amortization,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'net_margin' => round($netMargin, 1),

            // Balance sheet
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'cash_and_bank' => $cashAndBank,
            'receivables' => $receivables,
            'payables' => $payables,
            'inventory' => $inventory,
        ];
    }

    private function getChartData(array $filters): array
    {
        return [
            'monthlyProfitTrend' => $this->getMonthlyProfitTrend($filters),
            'expenseBreakdown' => $this->getExpenseBreakdown($filters),
            'assetComposition' => $this->getAssetComposition($filters),
            'revenueVsCogs' => $this->getRevenueVsCogsTrend($filters),
        ];
    }

    private function getMonthlyProfitTrend(array $filters): array
    {
        $endDate = $filters['end_date'];
        $labels = [];
        $revenueData = [];
        $profitData = [];

        // Last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-{$i} months", strtotime($endDate)));
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            $labels[] = date('M Y', strtotime($monthStart));

            $balances = $this->getAccountBalancesForPeriod($monthStart, $monthEnd, $filters);
            $rev = $this->sumByTypes($balances, ['pendapatan']);
            $cogs = $this->sumByTypes($balances, ['beban_pokok_penjualan']);
            $expenses = $this->sumByTypes($balances, ['beban', 'beban_lainnya', 'beban_penyusutan', 'beban_amortisasi']);
            $otherRev = $this->sumByTypes($balances, ['pendapatan_lainnya']);

            $revenueData[] = $rev;
            $profitData[] = $rev + $otherRev - $cogs - $expenses;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Pendapatan', 'data' => $revenueData],
                ['label' => 'Laba Bersih', 'data' => $profitData],
            ],
        ];
    }

    private function getExpenseBreakdown(array $filters): array
    {
        $balances = $this->getAccountBalancesForPeriod($filters['start_date'], $filters['end_date'], $filters);

        $categories = [
            'HPP' => $this->sumByTypes($balances, ['beban_pokok_penjualan']),
            'Operasional' => $this->sumByTypes($balances, ['beban']),
            'Penyusutan' => $this->sumByTypes($balances, ['beban_penyusutan', 'beban_amortisasi']),
            'Lainnya' => $this->sumByTypes($balances, ['beban_lainnya']),
        ];

        // Remove zero values
        $categories = array_filter($categories, fn ($v) => $v > 0);

        return [
            'labels' => array_keys($categories),
            'data' => array_values($categories),
        ];
    }

    private function getAssetComposition(array $filters): array
    {
        $balances = $this->getAccountBalancesCumulative($filters['end_date'], $filters);

        $categories = [
            'Kas & Bank' => $this->sumByTypes($balances, ['kas_bank']),
            'Piutang' => $this->sumByTypes($balances, ['piutang_usaha', 'piutang_usaha_lainnya']),
            'Persediaan' => $this->sumByTypes($balances, ['persediaan']),
            'Aset Tetap' => $this->sumByTypes($balances, ['aset_tetap', 'aset_tidak_berwujud']),
            'Lainnya' => $this->sumByTypes($balances, ['biaya_dibayar_dimuka', 'investasi_jangka_panjang', 'aset_lainnya']),
        ];

        $categories = array_filter($categories, fn ($v) => $v > 0);

        return [
            'labels' => array_keys($categories),
            'data' => array_values($categories),
        ];
    }

    private function getRevenueVsCogsTrend(array $filters): array
    {
        $endDate = $filters['end_date'];
        $labels = [];
        $revenueData = [];
        $cogsData = [];
        $marginData = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-{$i} months", strtotime($endDate)));
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            $labels[] = date('M Y', strtotime($monthStart));

            $balances = $this->getAccountBalancesForPeriod($monthStart, $monthEnd, $filters);
            $rev = $this->sumByTypes($balances, ['pendapatan']);
            $cogs = $this->sumByTypes($balances, ['beban_pokok_penjualan']);

            $revenueData[] = $rev;
            $cogsData[] = $cogs;
            $marginData[] = $rev > 0 ? round((($rev - $cogs) / $rev) * 100, 1) : 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Pendapatan', 'data' => $revenueData],
                ['label' => 'HPP', 'data' => $cogsData],
            ],
            'marginData' => $marginData,
        ];
    }

    // ─── Helpers ───

    private function getAccountBalancesForPeriod(string $startDate, string $endDate, array $filters)
    {
        return JournalEntry::join('accounts', 'journal_entries.account_id', '=', 'accounts.id')
            ->where('accounts.is_parent', false)
            ->whereHas('journal', function ($q) use ($startDate, $endDate, $filters) {
                $q->whereBetween('date', [$startDate, $endDate])
                    ->where('journal_type', '!=', 'retained_earnings');
                if (! empty($filters['company_id'])) {
                    $q->whereHas('branch.branchGroup', fn ($sub) => $sub->whereIn('company_id', $filters['company_id']));
                }
                if (! empty($filters['branch_id'])) {
                    $q->whereIn('branch_id', $filters['branch_id']);
                }
            })
            ->select(
                'accounts.type',
                'accounts.balance_type',
                DB::raw('SUM(journal_entries.primary_currency_debit) as total_debit'),
                DB::raw('SUM(journal_entries.primary_currency_credit) as total_credit')
            )
            ->groupBy('accounts.type', 'accounts.balance_type')
            ->get();
    }

    private function getAccountBalancesCumulative(string $endDate, array $filters)
    {
        return JournalEntry::join('accounts', 'journal_entries.account_id', '=', 'accounts.id')
            ->where('accounts.is_parent', false)
            ->whereHas('journal', function ($q) use ($endDate, $filters) {
                $q->where('date', '<=', $endDate)
                    ->where('journal_type', '!=', 'retained_earnings');
                if (! empty($filters['company_id'])) {
                    $q->whereHas('branch.branchGroup', fn ($sub) => $sub->whereIn('company_id', $filters['company_id']));
                }
                if (! empty($filters['branch_id'])) {
                    $q->whereIn('branch_id', $filters['branch_id']);
                }
            })
            ->select(
                'accounts.type',
                'accounts.balance_type',
                DB::raw('SUM(journal_entries.primary_currency_debit) as total_debit'),
                DB::raw('SUM(journal_entries.primary_currency_credit) as total_credit')
            )
            ->groupBy('accounts.type', 'accounts.balance_type')
            ->get();
    }

    private function sumByTypes($balances, array $types): float
    {
        return (float) $balances->whereIn('type', $types)->sum(function ($item) {
            return $item->balance_type === 'debit'
                ? $item->total_debit - $item->total_credit
                : $item->total_credit - $item->total_debit;
        });
    }
}
