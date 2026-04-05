<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrialBalanceController extends Controller
{
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

        $trialBalanceData = $this->getTrialBalanceData($filters);

        return Inertia::render('Reports/TrialBalance', [
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'trialBalanceData' => $trialBalanceData,
        ]);
    }

    private function getTrialBalanceData(array $filters): array
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $branchIds = $filters['branch_id'] ?? [];
        $companyIds = $filters['company_id'] ?? [];

        $accounts = Account::where('is_parent', false)
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $accountIds = $account->getAllDescendantIds()->push($account->id);

            $mutationQuery = JournalEntry::whereIn('account_id', $accountIds)
                ->whereHas('journal', function ($q) use ($startDate, $endDate, $branchIds, $companyIds) {
                    $q->whereBetween('date', [$startDate, $endDate])
                        ->where('journal_type', '!=', 'retained_earnings');
                    if (! empty($companyIds)) {
                        $q->whereHas('branch.branchGroup', fn ($sub) => $sub->whereIn('company_id', $companyIds));
                    }
                    if (! empty($branchIds)) {
                        $q->whereIn('branch_id', $branchIds);
                    }
                });

            $debit = (float) (clone $mutationQuery)->sum('primary_currency_debit');
            $credit = (float) $mutationQuery->sum('primary_currency_credit');

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        return [
            'rows' => $rows,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
            ],
        ];
    }
}
