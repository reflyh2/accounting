<?php

namespace App\Http\Controllers;

use App\Models\CostLayer;
use App\Models\ExternalDebt;
use App\Models\ExternalDebtPaymentDetail;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $centralUser = Auth::user();
        $tenantUser = User::where('global_id', $centralUser->global_id)->first();

        $preferences = UserSetting::getValue($centralUser->global_id, 'dashboard_preferences', [
            'default_period' => 'month',
            'visible_cards' => [
                'sales_orders' => true,
                'sales_invoices' => true,
                'purchase_orders' => true,
                'purchase_invoices' => true,
                'receivables' => true,
                'payables' => true,
            ],
            'show_charts' => true,
            'show_recent_documents' => true,
        ]);

        $period = $preferences['default_period'] ?? 'month';
        [$startDate, $endDate, $periodLabel] = $this->getDateRangeForPeriod($period);

        // Determine which modules the user can access
        $access = [
            'sales' => $tenantUser && $tenantUser->hasPermissionTo('sales.sales_order.view'),
            'purchase' => $tenantUser && $tenantUser->hasPermissionTo('purchase.purchase_order.view'),
            'inventory' => $tenantUser && $tenantUser->hasPermissionTo('inventory.stock.view'),
            'accounting' => $tenantUser && $tenantUser->hasPermissionTo('accounting.journal.view'),
            'payable_receivable' => $tenantUser && $tenantUser->hasPermissionTo('accounting.payment.view'),
        ];

        return Inertia::render('Dashboard', [
            'userName' => $centralUser->name,
            'preferences' => $preferences,
            'access' => $access,
            'sales' => $access['sales'] ? $this->getSalesData($startDate, $endDate) : null,
            'purchase' => $access['purchase'] ? $this->getPurchaseData($startDate, $endDate) : null,
            'inventory' => $access['inventory'] ? $this->getInventoryData($startDate, $endDate) : null,
            'accounting' => $access['accounting'] ? $this->getAccountingData($startDate, $endDate) : null,
            'payableReceivable' => $access['payable_receivable'] ? $this->getPayableReceivableData() : null,
            'periodLabel' => $periodLabel,
        ]);
    }

    private function getDateRangeForPeriod(string $period): array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'week':
                return [
                    $now->startOfWeek()->toDateString(),
                    $now->endOfWeek()->toDateString(),
                    'Minggu Ini',
                ];
            case 'quarter':
                return [
                    $now->firstOfQuarter()->toDateString(),
                    $now->copy()->lastOfQuarter()->toDateString(),
                    'Q'.$now->quarter.' '.$now->year,
                ];
            case 'year':
                return [
                    $now->startOfYear()->toDateString(),
                    $now->endOfYear()->toDateString(),
                    'Tahun '.$now->year,
                ];
            case 'month':
            default:
                return [
                    $now->startOfMonth()->toDateString(),
                    $now->endOfMonth()->toDateString(),
                    $now->format('F Y'),
                ];
        }
    }

    // ─── Sales ───

    private function getSalesData(string $startDate, string $endDate): array
    {
        $soQuery = SalesOrder::whereBetween('order_date', [$startDate, $endDate]);
        $siQuery = SalesInvoice::whereBetween('invoice_date', [$startDate, $endDate]);

        return [
            'orders' => [
                'count' => (clone $soQuery)->count(),
                'total' => (float) (clone $soQuery)->sum('total_amount'),
                'confirmed' => (clone $soQuery)->where('status', 'confirmed')->count(),
            ],
            'invoices' => [
                'count' => (clone $siQuery)->count(),
                'total' => (float) (clone $siQuery)->sum('total_amount'),
                'draft' => (clone $siQuery)->where('status', 'draft')->count(),
                'posted' => (clone $siQuery)->where('status', 'posted')->count(),
            ],
            'monthlyTrend' => $this->getSalesMonthlyTrend($endDate),
            'recentOrders' => $this->getRecentSalesOrders(),
        ];
    }

    private function getSalesMonthlyTrend(string $endDate): array
    {
        $labels = [];
        $orderData = [];
        $invoiceData = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-{$i} months", strtotime($endDate)));
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            $labels[] = date('M Y', strtotime($monthStart));

            $orderData[] = (float) SalesOrder::whereBetween('order_date', [$monthStart, $monthEnd])->sum('total_amount');
            $invoiceData[] = (float) SalesInvoice::whereBetween('invoice_date', [$monthStart, $monthEnd])->sum('total_amount');
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Sales Order', 'data' => $orderData],
                ['label' => 'Faktur Penjualan', 'data' => $invoiceData],
            ],
        ];
    }

    private function getRecentSalesOrders(): array
    {
        return SalesOrder::with('partner:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($so) => [
                'id' => $so->id,
                'number' => $so->number,
                'date' => $so->order_date?->format('Y-m-d'),
                'partner' => $so->partner?->name,
                'total' => (float) $so->total_amount,
                'status' => $so->status,
            ])->toArray();
    }

    // ─── Purchase ───

    private function getPurchaseData(string $startDate, string $endDate): array
    {
        $poQuery = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate]);
        $piQuery = PurchaseInvoice::whereBetween('invoice_date', [$startDate, $endDate]);

        return [
            'orders' => [
                'count' => (clone $poQuery)->count(),
                'total' => (float) (clone $poQuery)->sum('total_amount'),
                'pending' => (clone $poQuery)->whereIn('status', ['draft', 'approved'])->count(),
            ],
            'invoices' => [
                'count' => (clone $piQuery)->count(),
                'total' => (float) (clone $piQuery)->sum('total_amount'),
            ],
            'monthlyTrend' => $this->getPurchaseMonthlyTrend($endDate),
            'recentOrders' => $this->getRecentPurchaseOrders(),
        ];
    }

    private function getPurchaseMonthlyTrend(string $endDate): array
    {
        $labels = [];
        $orderData = [];
        $invoiceData = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-{$i} months", strtotime($endDate)));
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            $labels[] = date('M Y', strtotime($monthStart));

            $orderData[] = (float) PurchaseOrder::whereBetween('order_date', [$monthStart, $monthEnd])->sum('total_amount');
            $invoiceData[] = (float) PurchaseInvoice::whereBetween('invoice_date', [$monthStart, $monthEnd])->sum('total_amount');
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Purchase Order', 'data' => $orderData],
                ['label' => 'Faktur Pembelian', 'data' => $invoiceData],
            ],
        ];
    }

    private function getRecentPurchaseOrders(): array
    {
        return PurchaseOrder::with('partner:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($po) => [
                'id' => $po->id,
                'number' => $po->number,
                'date' => $po->order_date?->format('Y-m-d'),
                'partner' => $po->partner?->name,
                'total' => (float) $po->total_amount,
                'status' => $po->status,
            ])->toArray();
    }

    // ─── Inventory ───

    private function getInventoryData(string $startDate, string $endDate): array
    {
        $totalItems = InventoryItem::query()
            ->where('qty_on_hand', '>', 0)
            ->distinct('product_variant_id')
            ->count('product_variant_id');

        $totalValue = (float) (CostLayer::query()
            ->where('qty_remaining', '>', 0)
            ->selectRaw('SUM(qty_remaining * unit_cost) as val')
            ->value('val') ?? 0);

        $txnQuery = InventoryTransaction::query()
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        $txnByType = (clone $txnQuery)
            ->selectRaw('transaction_type, COUNT(*) as count')
            ->groupBy('transaction_type')
            ->pluck('count', 'transaction_type')
            ->toArray();

        return [
            'total_items' => $totalItems,
            'total_value' => $totalValue,
            'total_transactions' => (clone $txnQuery)->count(),
            'transactions_by_type' => $txnByType,
        ];
    }

    // ─── Accounting ───

    private function getAccountingData(string $startDate, string $endDate): array
    {
        $filters = [];
        $periodBalances = $this->getAccountBalancesForPeriod($startDate, $endDate, $filters);
        $cumulativeBalances = $this->getAccountBalancesCumulative($endDate, $filters);

        $revenue = $this->sumByTypes($periodBalances, ['pendapatan']);
        $cogs = $this->sumByTypes($periodBalances, ['beban_pokok_penjualan']);
        $operationalExpenses = $this->sumByTypes($periodBalances, ['beban_operasional']);
        $otherRevenue = $this->sumByTypes($periodBalances, ['pendapatan_lainnya']);
        $otherExpenses = $this->sumByTypes($periodBalances, ['beban_lainnya']);
        $depreciation = $this->sumByTypes($periodBalances, ['beban_penyusutan', 'beban_amortisasi']);

        $grossProfit = $revenue - $cogs;
        $totalExpenses = $operationalExpenses + $otherExpenses + $depreciation;
        $netProfit = $grossProfit + $otherRevenue - $totalExpenses;
        $grossMargin = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0;
        $netMargin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0;

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

        return [
            'revenue' => $revenue,
            'gross_profit' => $grossProfit,
            'gross_margin' => $grossMargin,
            'net_profit' => $netProfit,
            'net_margin' => $netMargin,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'cash_and_bank' => $cashAndBank,
        ];
    }

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

    // ─── Payable / Receivable ───

    private function getPayableReceivableData(): array
    {
        $end = Carbon::now()->endOfDay();

        $payable = $this->getOutstandingSummary('payable', $end);
        $receivable = $this->getOutstandingSummary('receivable', $end);

        return [
            'total_payable' => $payable['total'],
            'total_receivable' => $receivable['total'],
            'net_position' => $receivable['total'] - $payable['total'],
            'payable_overdue' => $payable['overdue'],
            'receivable_overdue' => $receivable['overdue'],
        ];
    }

    private function getOutstandingSummary(string $type, Carbon $end): array
    {
        $debts = ExternalDebt::query()
            ->select(['id', 'partner_id', 'due_date', 'primary_currency_amount', 'issue_date'])
            ->where('type', $type)
            ->whereDate('issue_date', '<=', $end->toDateString())
            ->get();

        if ($debts->isEmpty()) {
            return ['total' => 0, 'overdue' => 0];
        }

        $paidByDebt = ExternalDebtPaymentDetail::query()
            ->select([
                'external_debt_payment_details.external_debt_id',
                DB::raw('SUM(external_debt_payment_details.primary_currency_amount) as total_amount'),
            ])
            ->join('external_debt_payments as edp', 'edp.id', '=', 'external_debt_payment_details.external_debt_payment_id')
            ->whereIn('external_debt_payment_details.external_debt_id', $debts->pluck('id')->all())
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

        $total = 0;
        $overdue = 0;

        foreach ($debts as $debt) {
            $paid = (float) ($paidByDebt[$debt->id] ?? 0);
            $outstanding = (float) $debt->primary_currency_amount - $paid;
            if ($outstanding <= 0.000001) {
                continue;
            }

            $total += $outstanding;

            if (! empty($debt->due_date)) {
                $due = Carbon::parse($debt->due_date)->endOfDay();
                if ($due->lt($end)) {
                    $overdue += $outstanding;
                }
            }
        }

        return ['total' => $total, 'overdue' => $overdue];
    }
}
