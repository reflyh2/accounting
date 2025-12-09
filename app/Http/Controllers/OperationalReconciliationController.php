<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\FinishedGoodsReceipt;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseInvoice;
use App\Models\SalesDelivery;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\WorkOrder;
use Carbon\Carbon;
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
            $query->whereHas('branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', $filters['company_id']);
            });
        }
        $branches = $query->orderBy('name', 'asc')->get();

        $kpis = $this->calculateKPIs($filters);

        return Inertia::render('Reports/OperationalReconciliation', [
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'kpis' => $kpis,
        ]);
    }

    private function calculateKPIs(array $filters): array
    {
        $companyIds = $filters['company_id'] ?? [];
        $branchIds = $filters['branch_id'] ?? [];
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        return [
            'grni_aging' => $this->calculateGrniAging($companyIds, $branchIds),
            'cogs_vs_revenue' => $this->calculateCogsVsRevenue($companyIds, $branchIds, $startDate, $endDate),
            'wo_lead_time' => $this->calculateWoLeadTime($companyIds, $branchIds, $startDate, $endDate),
            'purchase_cycle_time' => $this->calculatePurchaseCycleTime($companyIds, $branchIds, $startDate, $endDate),
            'grn_to_ap_lag' => $this->calculateGrnToApLag($companyIds, $branchIds, $startDate, $endDate),
            'ppv_totals' => $this->calculatePpvTotals($companyIds, $branchIds, $startDate, $endDate),
            'sales_fill_rate' => $this->calculateSalesFillRate($companyIds, $branchIds, $startDate, $endDate),
            'on_time_delivery' => $this->calculateOnTimeDelivery($companyIds, $branchIds, $startDate, $endDate),
            'material_usage_variance' => $this->calculateMaterialUsageVariance($companyIds, $branchIds, $startDate, $endDate),
            'fg_unit_cost_trend' => $this->calculateFgUnitCostTrend($companyIds, $branchIds, $startDate, $endDate),
        ];
    }

    private function calculateGrniAging(array $companyIds, array $branchIds): array
    {
        $query = GoodsReceiptLine::with(['goodsReceipt.purchaseOrder', 'goodsReceipt.company', 'goodsReceipt.branch'])
            ->whereHas('goodsReceipt', function ($q) use ($companyIds, $branchIds) {
                $q->where('status', 'posted');
                if (! empty($companyIds)) {
                    $q->whereIn('company_id', $companyIds);
                }
                if (! empty($branchIds)) {
                    $q->whereIn('branch_id', $branchIds);
                }
            })
            ->whereRaw('quantity_invoiced_base < quantity_base');

        $lines = $query->get();

        $aging = [
            '0-30' => ['count' => 0, 'value' => 0],
            '31-60' => ['count' => 0, 'value' => 0],
            '61-90' => ['count' => 0, 'value' => 0],
            '90+' => ['count' => 0, 'value' => 0],
        ];

        foreach ($lines as $line) {
            $receiptDate = $line->goodsReceipt->receipt_date;
            $daysAged = Carbon::parse($receiptDate)->diffInDays(now());
            $outstandingQty = $line->quantity_base - $line->quantity_invoiced_base;
            $outstandingValue = $outstandingQty * $line->unit_cost_base;

            if ($daysAged <= 30) {
                $aging['0-30']['count']++;
                $aging['0-30']['value'] += $outstandingValue;
            } elseif ($daysAged <= 60) {
                $aging['31-60']['count']++;
                $aging['31-60']['value'] += $outstandingValue;
            } elseif ($daysAged <= 90) {
                $aging['61-90']['count']++;
                $aging['61-90']['value'] += $outstandingValue;
            } else {
                $aging['90+']['count']++;
                $aging['90+']['value'] += $outstandingValue;
            }
        }

        return [
            'summary' => $aging,
            'total_value' => array_sum(array_column($aging, 'value')),
            'total_count' => array_sum(array_column($aging, 'count')),
        ];
    }

    private function calculateCogsVsRevenue(array $companyIds, array $branchIds, string $startDate, string $endDate): array
    {
        $deliveryQuery = SalesDelivery::whereBetween('delivery_date', [$startDate, $endDate])
            ->where('status', 'posted');
        $invoiceQuery = SalesInvoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->where('status', 'posted');

        if (! empty($companyIds)) {
            $deliveryQuery->whereIn('company_id', $companyIds);
            $invoiceQuery->whereIn('company_id', $companyIds);
        }
        if (! empty($branchIds)) {
            $deliveryQuery->whereIn('branch_id', $branchIds);
            $invoiceQuery->whereIn('branch_id', $branchIds);
        }

        $totalCogs = $deliveryQuery->sum('total_cogs');
        $totalRevenue = $invoiceQuery->sum('subtotal');

        return [
            'total_cogs' => $totalCogs,
            'total_revenue' => $totalRevenue,
            'margin' => $totalRevenue - $totalCogs,
            'margin_percentage' => $totalRevenue > 0 ? (($totalRevenue - $totalCogs) / $totalRevenue) * 100 : 0,
        ];
    }

    private function calculateWoLeadTime(array $companyIds, array $branchIds, string $startDate, string $endDate): array
    {
        $query = WorkOrder::where('status', 'completed')
            ->whereNotNull('actual_start_date')
            ->whereNotNull('actual_end_date')
            ->whereBetween('actual_end_date', [$startDate, $endDate]);

        if (! empty($companyIds)) {
            $query->whereIn('company_id', $companyIds);
        }
        if (! empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        $workOrders = $query->get();

        $leadTimes = $workOrders->map(function ($wo) {
            return Carbon::parse($wo->actual_start_date)->diffInDays($wo->actual_end_date);
        });

        return [
            'average' => $leadTimes->avg() ?? 0,
            'min' => $leadTimes->min() ?? 0,
            'max' => $leadTimes->max() ?? 0,
            'count' => $workOrders->count(),
        ];
    }

    private function calculatePurchaseCycleTime(array $companyIds, array $branchIds, string $startDate, string $endDate): array
    {
        $query = GoodsReceipt::with('purchaseOrder')
            ->where('status', 'posted')
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->whereNotNull('purchase_order_id');

        if (! empty($companyIds)) {
            $query->whereIn('company_id', $companyIds);
        }
        if (! empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        $receipts = $query->get();

        $cycleTimes = $receipts->map(function ($grn) {
            if (! $grn->purchaseOrder || ! $grn->purchaseOrder->order_date) {
                return null;
            }

            return Carbon::parse($grn->purchaseOrder->order_date)->diffInDays($grn->receipt_date);
        })->filter();

        return [
            'average' => $cycleTimes->avg() ?? 0,
            'min' => $cycleTimes->min() ?? 0,
            'max' => $cycleTimes->max() ?? 0,
            'count' => $receipts->count(),
        ];
    }

    private function calculateGrnToApLag(array $companyIds, array $branchIds, string $startDate, string $endDate): array
    {
        $query = PurchaseInvoice::with(['purchaseOrder.goodsReceipts'])
            ->where('status', 'posted')
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereNotNull('purchase_order_id');

        if (! empty($companyIds)) {
            $query->whereIn('company_id', $companyIds);
        }
        if (! empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        $invoices = $query->get();

        $lags = collect();
        foreach ($invoices as $invoice) {
            if ($invoice->purchaseOrder) {
                $latestGrn = $invoice->purchaseOrder->goodsReceipts()
                    ->where('status', 'posted')
                    ->orderBy('receipt_date', 'desc')
                    ->first();

                if ($latestGrn) {
                    $lags->push(Carbon::parse($latestGrn->receipt_date)->diffInDays($invoice->invoice_date));
                }
            }
        }

        return [
            'average' => $lags->avg() ?? 0,
            'min' => $lags->min() ?? 0,
            'max' => $lags->max() ?? 0,
            'count' => $invoices->count(),
        ];
    }

    private function calculatePpvTotals(array $companyIds, array $branchIds, string $startDate, string $endDate): array
    {
        $query = PurchaseInvoice::where('status', 'posted')
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        if (! empty($companyIds)) {
            $query->whereIn('company_id', $companyIds);
        }
        if (! empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        $favorable = $query->where('ppv_amount', '<', 0)->sum(DB::raw('ABS(ppv_amount)'));
        $unfavorable = $query->where('ppv_amount', '>', 0)->sum('ppv_amount');
        $net = $unfavorable - $favorable;

        return [
            'favorable' => $favorable,
            'unfavorable' => $unfavorable,
            'net' => $net,
            'count' => $query->count(),
        ];
    }

    private function calculateSalesFillRate(array $companyIds, array $branchIds, string $startDate, string $endDate): array
    {
        $orderQuery = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'confirmed');
        $deliveryQuery = SalesDelivery::whereBetween('delivery_date', [$startDate, $endDate])
            ->where('status', 'posted');

        if (! empty($companyIds)) {
            $orderQuery->whereIn('company_id', $companyIds);
            $deliveryQuery->whereIn('company_id', $companyIds);
        }
        if (! empty($branchIds)) {
            $orderQuery->whereIn('branch_id', $branchIds);
            $deliveryQuery->whereIn('branch_id', $branchIds);
        }

        $totalOrdered = $orderQuery->sum('total_amount');
        $totalDelivered = $deliveryQuery->sum('total_amount');

        return [
            'total_ordered' => $totalOrdered,
            'total_delivered' => $totalDelivered,
            'fill_rate' => $totalOrdered > 0 ? ($totalDelivered / $totalOrdered) * 100 : 0,
        ];
    }

    private function calculateOnTimeDelivery(array $companyIds, array $branchIds, string $startDate, string $endDate): array
    {
        $query = SalesDelivery::with('salesOrder')
            ->whereBetween('delivery_date', [$startDate, $endDate])
            ->where('status', 'posted');

        if (! empty($companyIds)) {
            $query->whereIn('company_id', $companyIds);
        }
        if (! empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        $deliveries = $query->get();

        $onTime = 0;
        $late = 0;

        foreach ($deliveries as $delivery) {
            if ($delivery->salesOrder && $delivery->salesOrder->expected_delivery_date) {
                if ($delivery->delivery_date <= $delivery->salesOrder->expected_delivery_date) {
                    $onTime++;
                } else {
                    $late++;
                }
            }
        }

        $total = $onTime + $late;

        return [
            'on_time' => $onTime,
            'late' => $late,
            'total' => $total,
            'on_time_percentage' => $total > 0 ? ($onTime / $total) * 100 : 0,
        ];
    }

    private function calculateMaterialUsageVariance(array $companyIds, array $branchIds, string $startDate, string $endDate): array
    {
        // This would require variance tracking in work orders
        // For now, return placeholder structure
        return [
            'favorable' => 0,
            'unfavorable' => 0,
            'net' => 0,
            'count' => 0,
        ];
    }

    private function calculateFgUnitCostTrend(array $companyIds, array $branchIds, string $startDate, string $endDate): array
    {
        $query = FinishedGoodsReceipt::whereBetween('receipt_date', [$startDate, $endDate])
            ->where('status', 'posted')
            ->whereNotNull('unit_cost_base');

        if (! empty($companyIds)) {
            $query->whereIn('company_id', $companyIds);
        }
        if (! empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        $receipts = $query->orderBy('receipt_date')->get();

        $trend = $receipts->groupBy(function ($receipt) {
            return Carbon::parse($receipt->receipt_date)->format('Y-m');
        })->map(function ($group) {
            return [
                'average_unit_cost' => $group->avg('unit_cost_base'),
                'count' => $group->count(),
            ];
        });

        return [
            'trend' => $trend,
            'current_average' => $receipts->avg('unit_cost_base') ?? 0,
        ];
    }
}
