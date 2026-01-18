<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\ExternalDebt;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesDelivery;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\UserSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get user's dashboard preferences
        $preferences = UserSetting::getValue($user->global_id, 'dashboard_preferences', [
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
        
        // Calculate date range based on user's preferred period
        $period = $preferences['default_period'] ?? 'month';
        [$startDate, $endDate, $periodLabel] = $this->getDateRangeForPeriod($period);
        
        // Get current year date range for trends (always yearly)
        $startOfYear = Carbon::now()->startOfYear()->toDateString();
        $endOfYear = Carbon::now()->endOfYear()->toDateString();

        return Inertia::render('Dashboard', [
            'userName' => $user->name,
            'preferences' => $preferences,
            'summary' => $this->getSummaryData($startDate, $endDate, $periodLabel),
            'chartData' => $preferences['show_charts'] ? $this->getChartData($startOfYear, $endOfYear) : null,
            'recentDocuments' => $preferences['show_recent_documents'] ? $this->getRecentDocuments() : null,
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
                    'Q' . $now->quarter . ' ' . $now->year,
                ];
            case 'year':
                return [
                    $now->startOfYear()->toDateString(),
                    $now->endOfYear()->toDateString(),
                    'Tahun ' . $now->year,
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

    private function getSummaryData(string $startDate, string $endDate, ?string $periodLabel = null): array
    {
        // Sales Orders - this month
        $salesOrdersQuery = SalesOrder::whereBetween('order_date', [$startDate, $endDate]);
        $salesOrders = [
            'count' => (clone $salesOrdersQuery)->count(),
            'total' => (clone $salesOrdersQuery)->sum('total_amount'),
            'confirmed' => (clone $salesOrdersQuery)->where('status', 'confirmed')->count(),
        ];

        // Sales Invoices - this month
        $salesInvoicesQuery = SalesInvoice::whereBetween('invoice_date', [$startDate, $endDate]);
        $salesInvoices = [
            'count' => (clone $salesInvoicesQuery)->count(),
            'total' => (clone $salesInvoicesQuery)->sum('total_amount'),
            'draft' => (clone $salesInvoicesQuery)->where('status', 'draft')->count(),
            'posted' => (clone $salesInvoicesQuery)->where('status', 'posted')->count(),
        ];

        // Purchase Orders - this month
        $purchaseOrdersQuery = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate]);
        $purchaseOrders = [
            'count' => (clone $purchaseOrdersQuery)->count(),
            'total' => (clone $purchaseOrdersQuery)->sum('total_amount'),
            'pending' => (clone $purchaseOrdersQuery)->whereIn('status', ['draft', 'approved'])->count(),
        ];

        // Purchase Invoices - this month
        $purchaseInvoicesQuery = PurchaseInvoice::whereBetween('invoice_date', [$startDate, $endDate]);
        $purchaseInvoices = [
            'count' => (clone $purchaseInvoicesQuery)->count(),
            'total' => (clone $purchaseInvoicesQuery)->sum('total_amount'),
        ];

        // Receivables (outstanding) - debts with open/partial status
        $receivables = ExternalDebt::where('type', 'receivable')
            ->whereIn('status', ['open', 'partial'])
            ->selectRaw('SUM(primary_currency_amount) as total')
            ->first();

        // Payables (outstanding) - debts with open/partial status
        $payables = ExternalDebt::where('type', 'payable')
            ->whereIn('status', ['open', 'partial'])
            ->selectRaw('SUM(primary_currency_amount) as total')
            ->first();

        return [
            'salesOrders' => $salesOrders,
            'salesInvoices' => $salesInvoices,
            'purchaseOrders' => $purchaseOrders,
            'purchaseInvoices' => $purchaseInvoices,
            'receivables' => [
                'total' => (float) ($receivables->total ?? 0),
                'outstanding' => (float) ($receivables->total ?? 0),
            ],
            'payables' => [
                'total' => (float) ($payables->total ?? 0),
                'outstanding' => (float) ($payables->total ?? 0),
            ],
            'periodLabel' => $periodLabel ?? Carbon::now()->format('F Y'),
        ];
    }

    private function getChartData(string $startDate, string $endDate): array
    {
        // Monthly sales trend
        $monthlyTrend = $this->getMonthlyTrendData($startDate, $endDate);
        
        // Sales order status distribution
        $soStatusDistribution = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Sales invoice status distribution
        $siStatusDistribution = SalesInvoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'monthlyTrend' => $monthlyTrend,
            'salesOrderStatus' => [
                'labels' => array_keys($soStatusDistribution),
                'data' => array_values($soStatusDistribution),
            ],
            'salesInvoiceStatus' => [
                'labels' => array_keys($siStatusDistribution),
                'data' => array_values($siStatusDistribution),
            ],
        ];
    }

    private function getMonthlyTrendData(string $startDate, string $endDate): array
    {
        // Sales orders by month
        $salesByMonth = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->selectRaw("TO_CHAR(order_date, 'YYYY-MM') as month, SUM(total_amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Sales invoices by month
        $invoicesByMonth = SalesInvoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->selectRaw("TO_CHAR(invoice_date, 'YYYY-MM') as month, SUM(total_amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Merge all months
        $allMonths = array_unique(array_merge(array_keys($salesByMonth), array_keys($invoicesByMonth)));
        sort($allMonths);

        return [
            'labels' => $allMonths,
            'datasets' => [
                [
                    'label' => 'Sales Orders',
                    'data' => array_map(fn($m) => (float) ($salesByMonth[$m] ?? 0), $allMonths),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
                [
                    'label' => 'Sales Invoices',
                    'data' => array_map(fn($m) => (float) ($invoicesByMonth[$m] ?? 0), $allMonths),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
        ];
    }

    private function getRecentDocuments(): array
    {
        $recentSalesOrders = SalesOrder::with(['partner:id,name', 'branch:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($so) => [
                'id' => $so->id,
                'number' => $so->number,
                'date' => $so->order_date?->format('Y-m-d'),
                'partner' => $so->partner?->name,
                'total' => $so->total_amount,
                'status' => $so->status,
                'type' => 'sales_order',
            ]);

        $recentSalesInvoices = SalesInvoice::with(['partner:id,name', 'branch:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($si) => [
                'id' => $si->id,
                'number' => $si->number,
                'date' => $si->invoice_date?->format('Y-m-d'),
                'partner' => $si->partner?->name,
                'total' => $si->total_amount,
                'status' => $si->status,
                'type' => 'sales_invoice',
            ]);

        $recentPurchaseOrders = PurchaseOrder::with(['partner:id,name', 'branch:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($po) => [
                'id' => $po->id,
                'number' => $po->number,
                'date' => $po->order_date?->format('Y-m-d'),
                'partner' => $po->partner?->name,
                'total' => $po->total_amount,
                'status' => $po->status,
                'type' => 'purchase_order',
            ]);

        return [
            'salesOrders' => $recentSalesOrders,
            'salesInvoices' => $recentSalesInvoices,
            'purchaseOrders' => $recentPurchaseOrders,
        ];
    }
}
