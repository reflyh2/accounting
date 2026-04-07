<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\ExternalDebt;
use App\Models\ExternalDebtPaymentDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayableReceivableOverviewController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all();

        if (empty($filters['end_date'])) {
            $filters['end_date'] = date('Y-m-d');
        }

        $companies = Company::orderBy('name', 'asc')->get();

        $branchQuery = Branch::query();
        if (! empty($filters['company_id'])) {
            $branchQuery->whereHas('branchGroup', fn ($q) => $q->whereIn('company_id', $filters['company_id']));
        }
        $branches = $branchQuery->orderBy('name', 'asc')->get();

        $summaryData = $this->getSummaryData($filters);
        $chartData = $this->getChartData($filters);

        return Inertia::render('Reports/PayableReceivableOverview', [
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'summaryData' => $summaryData,
            'chartData' => $chartData,
        ]);
    }

    private function getSummaryData(array $filters): array
    {
        $endDate = $filters['end_date'];
        $end = Carbon::parse($endDate)->endOfDay();

        $payableData = $this->getOutstandingByType('payable', $end, $filters);
        $receivableData = $this->getOutstandingByType('receivable', $end, $filters);

        return [
            'total_payable' => $payableData['total'],
            'total_receivable' => $receivableData['total'],
            'net_position' => $receivableData['total'] - $payableData['total'],
            'payable_overdue' => $payableData['overdue'],
            'receivable_overdue' => $receivableData['overdue'],
            'payable_not_yet_due' => $payableData['not_yet_due'],
            'receivable_not_yet_due' => $receivableData['not_yet_due'],
            'payable_partner_count' => $payableData['partner_count'],
            'receivable_partner_count' => $receivableData['partner_count'],
            'payable_aging' => $payableData['aging'],
            'receivable_aging' => $receivableData['aging'],
            'payable_top_partners' => $payableData['top_partners'],
            'receivable_top_partners' => $receivableData['top_partners'],
        ];
    }

    private function getOutstandingByType(string $type, Carbon $end, array $filters): array
    {
        $debts = ExternalDebt::query()
            ->select([
                'external_debts.id',
                'external_debts.partner_id',
                'external_debts.due_date',
                'external_debts.primary_currency_amount',
                'external_debts.issue_date',
            ])
            ->where('type', $type)
            ->whereDate('issue_date', '<=', $end->toDateString())
            ->when(! empty($filters['company_id']), function ($q) use ($filters) {
                $q->whereHas('branch.branchGroup', fn ($sub) => $sub->whereIn('company_id', $filters['company_id']));
            })
            ->when(! empty($filters['branch_id']), function ($q) use ($filters) {
                $q->whereIn('branch_id', $filters['branch_id']);
            })
            ->get();

        if ($debts->isEmpty()) {
            return [
                'total' => 0,
                'overdue' => 0,
                'not_yet_due' => 0,
                'partner_count' => 0,
                'aging' => $this->emptyAging(),
                'top_partners' => [],
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
            ->where('edp.type', $type)
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

        $aging = $this->emptyAging();
        $partnerTotals = [];
        $total = 0;
        $overdue = 0;
        $notYetDue = 0;

        foreach ($debts as $debt) {
            $paid = (float) ($paidByDebt[$debt->id] ?? 0);
            $outstanding = (float) $debt->primary_currency_amount - $paid;
            if ($outstanding <= 0.000001) {
                continue;
            }

            $bucket = 'not_yet_due';
            if (! empty($debt->due_date)) {
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

            $aging[$bucket] += $outstanding;
            $total += $outstanding;

            if ($bucket === 'not_yet_due') {
                $notYetDue += $outstanding;
            } else {
                $overdue += $outstanding;
            }

            $partnerId = $debt->partner_id;
            if (! isset($partnerTotals[$partnerId])) {
                $partnerTotals[$partnerId] = 0;
            }
            $partnerTotals[$partnerId] += $outstanding;
        }

        // Top 5 partners by outstanding amount
        arsort($partnerTotals);
        $topPartnerIds = array_slice(array_keys($partnerTotals), 0, 5);
        $partners = \App\Models\Partner::whereIn('id', $topPartnerIds)->pluck('name', 'id');

        $topPartners = [];
        foreach ($topPartnerIds as $pid) {
            $topPartners[] = [
                'name' => $partners[$pid] ?? '—',
                'amount' => $partnerTotals[$pid],
            ];
        }

        return [
            'total' => $total,
            'overdue' => $overdue,
            'not_yet_due' => $notYetDue,
            'partner_count' => count($partnerTotals),
            'aging' => $aging,
            'top_partners' => $topPartners,
        ];
    }

    private function getChartData(array $filters): array
    {
        $endDate = $filters['end_date'];

        return [
            'payableAgingChart' => $this->getAgingChartData('payable', $endDate, $filters),
            'receivableAgingChart' => $this->getAgingChartData('receivable', $endDate, $filters),
            'monthlyTrend' => $this->getMonthlyTrend($endDate, $filters),
        ];
    }

    private function getAgingChartData(string $type, string $endDate, array $filters): array
    {
        $end = Carbon::parse($endDate)->endOfDay();
        $data = $this->getOutstandingByType($type, $end, $filters);

        return [
            'labels' => ['Belum Jatuh Tempo', '1-30 Hari', '31-60 Hari', '61-90 Hari', '> 90 Hari'],
            'data' => [
                $data['aging']['not_yet_due'],
                $data['aging']['days_1_30'],
                $data['aging']['days_31_60'],
                $data['aging']['days_61_90'],
                $data['aging']['days_91_plus'],
            ],
        ];
    }

    private function getMonthlyTrend(string $endDate, array $filters): array
    {
        $labels = [];
        $payableData = [];
        $receivableData = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthEnd = date('Y-m-t', strtotime("-{$i} months", strtotime($endDate)));
            $labels[] = date('M Y', strtotime($monthEnd));

            $end = Carbon::parse($monthEnd)->endOfDay();
            $payable = $this->getOutstandingByType('payable', $end, $filters);
            $receivable = $this->getOutstandingByType('receivable', $end, $filters);

            $payableData[] = $payable['total'];
            $receivableData[] = $receivable['total'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Piutang', 'data' => $receivableData],
                ['label' => 'Hutang', 'data' => $payableData],
            ],
        ];
    }

    private function emptyAging(): array
    {
        return [
            'not_yet_due' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_91_plus' => 0,
        ];
    }
}
