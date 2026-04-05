<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashFlowController extends Controller
{
    /**
     * Cash flow report using indirect method.
     * Groups cash flows into Operating, Investing, and Financing activities
     * based on account types.
     */
    public function index(Request $request): \Inertia\Response
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

        $cashFlowData = $this->getCashFlowData($filters);

        return Inertia::render('Reports/CashFlow', [
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'cashFlowData' => $cashFlowData,
        ]);
    }

    private function getCashFlowData(array $filters): array
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $branchIds = $filters['branch_id'] ?? [];
        $companyIds = $filters['company_id'] ?? [];

        // Categorize account types into cash flow sections
        $operatingTypes = [
            'pendapatan', 'pendapatan_lainnya',
            'beban_pokok_penjualan', 'beban_operasional', 'beban_lainnya',
            'hutang_usaha', 'hutang_usaha_lainnya',
            'piutang_usaha', 'piutang_usaha_lainnya',
            'persediaan',
            'biaya_dibayar_dimuka', 'pendapatan_diterima_dimuka',
            'liabilitas_jangka_pendek',
        ];

        $investingTypes = [
            'aset_tetap', 'aset_tidak_berwujud',
            'investasi_jangka_panjang', 'aset_lainnya',
        ];

        $financingTypes = [
            'modal', 'liabilitas_jangka_panjang',
            'laba_ditahan',
        ];

        $cashTypes = [
            'kas_dan_bank',
        ];

        $sections = [
            'operating' => $this->getSectionData('Aktivitas Operasional', $operatingTypes, $startDate, $endDate, $branchIds, $companyIds),
            'investing' => $this->getSectionData('Aktivitas Investasi', $investingTypes, $startDate, $endDate, $branchIds, $companyIds),
            'financing' => $this->getSectionData('Aktivitas Pendanaan', $financingTypes, $startDate, $endDate, $branchIds, $companyIds),
        ];

        $netCashChange = $sections['operating']['total'] + $sections['investing']['total'] + $sections['financing']['total'];

        // Opening and closing cash balances
        $cashAccounts = Account::whereIn('type', $cashTypes)->where('is_parent', false)->get();
        $openingCash = 0;
        foreach ($cashAccounts as $account) {
            $openingCash += $account->getBalanceForDateAndBranches(
                date('Y-m-d', strtotime($startDate.' -1 day')),
                $branchIds,
                $companyIds
            );
        }

        $closingCash = $openingCash + $netCashChange;

        return [
            'sections' => $sections,
            'net_cash_change' => $netCashChange,
            'opening_cash' => $openingCash,
            'closing_cash' => $closingCash,
        ];
    }

    private function getSectionData(string $label, array $accountTypes, string $startDate, string $endDate, array $branchIds, array $companyIds): array
    {
        $accounts = Account::whereIn('type', $accountTypes)
            ->where('is_parent', false)
            ->orderBy('code')
            ->get();

        $items = [];
        $sectionTotal = 0;

        foreach ($accounts as $account) {
            $accountIds = $account->getAllDescendantIds()->push($account->id);

            $query = JournalEntry::whereIn('account_id', $accountIds)
                ->whereHas('journal', function ($q) use ($startDate, $endDate, $branchIds, $companyIds) {
                    $q->whereBetween('date', [$startDate, $endDate]);
                    if (! empty($companyIds)) {
                        $q->whereHas('branch.branchGroup', fn ($sub) => $sub->whereIn('company_id', $companyIds));
                    }
                    if (! empty($branchIds)) {
                        $q->whereIn('branch_id', $branchIds);
                    }
                });

            $debit = (float) (clone $query)->sum('primary_currency_debit');
            $credit = (float) (clone $query)->sum('primary_currency_credit');
            $netChange = $debit - $credit;

            if ($netChange == 0) {
                continue;
            }

            // For cash flow purposes, the sign depends on whether the account
            // normally reduces cash (asset increase) or increases cash (liability increase)
            $cashImpact = $account->balance_type === 'credit' ? $netChange * -1 : $netChange;

            $sectionTotal += $cashImpact;

            $items[] = [
                'account_code' => $account->code,
                'account_name' => $account->name,
                'amount' => $cashImpact,
            ];
        }

        return [
            'label' => $label,
            'items' => $items,
            'total' => $sectionTotal,
        ];
    }
}
