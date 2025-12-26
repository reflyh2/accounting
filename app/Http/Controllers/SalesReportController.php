<?php

namespace App\Http\Controllers;

use App\Enums\Documents\InvoiceStatus;
use App\Enums\Documents\SalesDeliveryStatus;
use App\Enums\Documents\SalesOrderStatus;
use App\Enums\Documents\SalesReturnStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Partner;
use App\Models\SalesDelivery;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalesReportController extends Controller
{
    private const GROUPING_OPTIONS = [
        ['value' => 'document', 'label' => 'Per Dokumen'],
        ['value' => 'customer', 'label' => 'Per Customer'],
        ['value' => 'items', 'label' => 'Per Item'],
        ['value' => 'creator', 'label' => 'Per Pembuat'],
        ['value' => 'status', 'label' => 'Per Status'],
        ['value' => 'branch', 'label' => 'Per Cabang'],
        ['value' => 'company', 'label' => 'Per Perusahaan'],
    ];

    public function index(Request $request)
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $summaryData = $this->getSummaryData($filters);
        $chartData = $this->getChartData($filters);

        return Inertia::render('Reports/Sales/Overview', [
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'summaryData' => $summaryData,
            'chartData' => $chartData,
        ]);
    }

    private function getChartData($filters)
    {
        return [
            'topCustomers' => $this->getTopCustomersData($filters),
            'statusDistribution' => $this->getStatusDistributionData($filters),
            'monthlyTrend' => $this->getMonthlyTrendData($filters),
            'byBranch' => $this->getByBranchData($filters),
        ];
    }

    private function getTopCustomersData($filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $query = SalesOrder::query()
            ->selectRaw('partner_id, SUM(total_amount) as total_value')
            ->with('partner:id,name')
            ->whereBetween('order_date', [$startDate, $endDate]);

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }
        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', (array) $filters['branch_id']);
        }

        $results = $query->groupBy('partner_id')
            ->orderByDesc('total_value')
            ->limit(5)
            ->get();

        return [
            'labels' => $results->map(fn($r) => $r->partner?->name ?? 'Unknown')->toArray(),
            'data' => $results->pluck('total_value')->map(fn($v) => (float) $v)->toArray(),
        ];
    }

    private function getStatusDistributionData($filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $applyFilters = function ($query) use ($filters) {
            if (!empty($filters['company_id'])) {
                $query->whereIn('company_id', (array) $filters['company_id']);
            }
            if (!empty($filters['branch_id'])) {
                $query->whereIn('branch_id', (array) $filters['branch_id']);
            }
            return $query;
        };

        // SO Status
        $soQuery = SalesOrder::query()->whereBetween('order_date', [$startDate, $endDate]);
        $applyFilters($soQuery);
        $soStatus = $soQuery->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Invoice Status
        $siQuery = SalesInvoice::query()->whereBetween('invoice_date', [$startDate, $endDate]);
        $applyFilters($siQuery);
        $siStatus = $siQuery->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'so' => [
                'labels' => array_keys($soStatus),
                'data' => array_values($soStatus),
            ],
            'invoice' => [
                'labels' => array_keys($siStatus),
                'data' => array_values($siStatus),
            ],
        ];
    }

    private function getMonthlyTrendData($filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $applyFilters = function ($query) use ($filters) {
            if (!empty($filters['company_id'])) {
                $query->whereIn('company_id', (array) $filters['company_id']);
            }
            if (!empty($filters['branch_id'])) {
                $query->whereIn('branch_id', (array) $filters['branch_id']);
            }
            return $query;
        };

        // Get monthly SO data
        $soQuery = SalesOrder::query()
            ->selectRaw("TO_CHAR(order_date, 'YYYY-MM') as month, SUM(total_amount) as total_value")
            ->whereBetween('order_date', [$startDate, $endDate]);
        $applyFilters($soQuery);
        $soMonthly = $soQuery->groupBy('month')
            ->orderBy('month')
            ->pluck('total_value', 'month')
            ->toArray();

        // Get monthly Delivery data
        $sdQuery = SalesDelivery::query()
            ->selectRaw("TO_CHAR(delivery_date, 'YYYY-MM') as month, SUM(total_amount) as total_value")
            ->whereBetween('delivery_date', [$startDate, $endDate]);
        $applyFilters($sdQuery);
        $sdMonthly = $sdQuery->groupBy('month')
            ->orderBy('month')
            ->pluck('total_value', 'month')
            ->toArray();

        // Merge months
        $allMonths = array_unique(array_merge(array_keys($soMonthly), array_keys($sdMonthly)));
        sort($allMonths);

        return [
            'labels' => $allMonths,
            'datasets' => [
                [
                    'label' => 'Sales Orders',
                    'data' => array_map(fn($m) => (float) ($soMonthly[$m] ?? 0), $allMonths),
                ],
                [
                    'label' => 'Sales Deliveries',
                    'data' => array_map(fn($m) => (float) ($sdMonthly[$m] ?? 0), $allMonths),
                ],
            ],
        ];
    }

    private function getByBranchData($filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $query = SalesOrder::query()
            ->selectRaw('branch_id, SUM(total_amount) as total_value')
            ->with('branch:id,name')
            ->whereBetween('order_date', [$startDate, $endDate]);

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }
        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', (array) $filters['branch_id']);
        }

        $results = $query->groupBy('branch_id')
            ->orderByDesc('total_value')
            ->get();

        return [
            'labels' => $results->map(fn($r) => $r->branch?->name ?? 'Unknown')->toArray(),
            'data' => $results->pluck('total_value')->map(fn($v) => (float) $v)->toArray(),
        ];
    }

    private function getSummaryData($filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $applyFilters = function ($query) use ($filters) {
            if (!empty($filters['company_id'])) {
                $query->whereIn('company_id', (array) $filters['company_id']);
            }
            if (!empty($filters['branch_id'])) {
                $query->whereIn('branch_id', (array) $filters['branch_id']);
            }
            return $query;
        };

        $soQuery = SalesOrder::query();
        $applyFilters($soQuery);
        $soQuery->whereBetween('order_date', [$startDate, $endDate]);

        $soSummary = [
            'total_count' => (clone $soQuery)->count(),
            'total_value' => (clone $soQuery)->sum('total_amount'),
            'draft_count' => (clone $soQuery)->where('status', 'draft')->count(),
            'quote_count' => (clone $soQuery)->where('status', 'quote')->count(),
            'confirmed_count' => (clone $soQuery)->where('status', 'confirmed')->count(),
        ];

        $sdQuery = SalesDelivery::query();
        $applyFilters($sdQuery);
        $sdQuery->whereBetween('delivery_date', [$startDate, $endDate]);

        $sdSummary = [
            'total_count' => (clone $sdQuery)->count(),
            'total_value' => (clone $sdQuery)->sum('total_amount'),
            'draft_count' => (clone $sdQuery)->where('status', 'draft')->count(),
            'posted_count' => (clone $sdQuery)->where('status', 'posted')->count(),
        ];

        $siQuery = SalesInvoice::query();
        $applyFilters($siQuery);
        $siQuery->whereBetween('invoice_date', [$startDate, $endDate]);

        $siSummary = [
            'total_count' => (clone $siQuery)->count(),
            'total_value' => (clone $siQuery)->sum('total_amount'),
            'draft_count' => (clone $siQuery)->where('status', 'draft')->count(),
            'posted_count' => (clone $siQuery)->where('status', 'posted')->count(),
            'paid_count' => (clone $siQuery)->where('status', 'paid')->count(),
        ];

        $srQuery = SalesReturn::query();
        $applyFilters($srQuery);
        $srQuery->whereBetween('return_date', [$startDate, $endDate]);

        $srSummary = [
            'total_count' => (clone $srQuery)->count(),
            'total_value' => (clone $srQuery)->sum('total_value'),
            'draft_count' => (clone $srQuery)->where('status', 'draft')->count(),
            'posted_count' => (clone $srQuery)->where('status', 'posted')->count(),
        ];

        return [
            'sales_orders' => $soSummary,
            'sales_deliveries' => $sdSummary,
            'sales_invoices' => $siSummary,
            'sales_returns' => $srSummary,
        ];
    }

    public function salesOrders(Request $request)
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $customers = $this->getCustomers();
        $statusOptions = $this->getStatusOptions(SalesOrderStatus::class);

        $groupBy = $filters['group_by'] ?? 'document';

        $query = SalesOrder::with(['company', 'branch', 'partner', 'currency'])
            ->when(!empty($filters['company_id']), fn($q) => $q->whereIn('company_id', (array) $filters['company_id']))
            ->when(!empty($filters['branch_id']), fn($q) => $q->whereIn('branch_id', (array) $filters['branch_id']))
            ->when(!empty($filters['partner_id']), fn($q) => $q->where('partner_id', $filters['partner_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->whereBetween('order_date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('order_date', 'desc');

        $data = $this->getGroupedData($query, $groupBy, 'sales_orders', $filters);

        $totals = [
            'total_amount' => (clone $query)->sum('total_amount'),
            'count' => (clone $query)->count(),
        ];

        return Inertia::render('Reports/Sales/SalesOrderReport', [
            'companies' => $companies,
            'branches' => $branches,
            'customers' => $customers,
            'statusOptions' => $statusOptions,
            'groupingOptions' => self::GROUPING_OPTIONS,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
            'statusLabels' => $this->getStatusLabels(SalesOrderStatus::class),
        ]);
    }

    public function salesDeliveries(Request $request)
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $customers = $this->getCustomers();
        $statusOptions = $this->getStatusOptions(SalesDeliveryStatus::class);

        $groupBy = $filters['group_by'] ?? 'document';

        $query = SalesDelivery::with(['company', 'branch', 'partner', 'currency'])
            ->when(!empty($filters['company_id']), fn($q) => $q->whereIn('company_id', (array) $filters['company_id']))
            ->when(!empty($filters['branch_id']), fn($q) => $q->whereIn('branch_id', (array) $filters['branch_id']))
            ->when(!empty($filters['partner_id']), fn($q) => $q->where('partner_id', $filters['partner_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->whereBetween('delivery_date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('delivery_date', 'desc');

        $data = $this->getGroupedData($query, $groupBy, 'sales_deliveries', $filters);

        $totals = [
            'total_amount' => (clone $query)->sum('total_amount'),
            'count' => (clone $query)->count(),
        ];

        return Inertia::render('Reports/Sales/SalesDeliveryReport', [
            'companies' => $companies,
            'branches' => $branches,
            'customers' => $customers,
            'statusOptions' => $statusOptions,
            'groupingOptions' => self::GROUPING_OPTIONS,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
            'statusLabels' => $this->getStatusLabels(SalesDeliveryStatus::class),
        ]);
    }

    public function salesInvoices(Request $request)
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $customers = $this->getCustomers();
        $statusOptions = $this->getStatusOptions(InvoiceStatus::class);

        $groupBy = $filters['group_by'] ?? 'document';

        $query = SalesInvoice::with(['company', 'branch', 'partner', 'currency', 'lines.salesDeliveryLine'])
            ->when(!empty($filters['company_id']), fn($q) => $q->whereIn('company_id', (array) $filters['company_id']))
            ->when(!empty($filters['branch_id']), fn($q) => $q->whereIn('branch_id', (array) $filters['branch_id']))
            ->when(!empty($filters['partner_id']), fn($q) => $q->where('partner_id', $filters['partner_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->whereBetween('invoice_date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('invoice_date', 'desc');

        $data = $this->getGroupedDataWithCogs($query, $groupBy, $filters);

        // Calculate totals with COGS
        $allInvoices = (clone $query)->get();
        $totalRevenue = $allInvoices->sum('total_amount');
        $totalCogs = $allInvoices->sum(function ($invoice) {
            return $this->calculateInvoiceCogs($invoice);
        });
        $totalGrossProfit = $totalRevenue - $totalCogs;

        $totals = [
            'total_amount' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'gross_profit' => $totalGrossProfit,
            'margin_percentage' => $totalRevenue > 0 ? round(($totalGrossProfit / $totalRevenue) * 100, 2) : 0,
            'count' => $allInvoices->count(),
        ];

        return Inertia::render('Reports/Sales/SalesInvoiceReport', [
            'companies' => $companies,
            'branches' => $branches,
            'customers' => $customers,
            'statusOptions' => $statusOptions,
            'groupingOptions' => self::GROUPING_OPTIONS,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
            'statusLabels' => $this->getStatusLabels(InvoiceStatus::class),
        ]);
    }

    public function salesReturns(Request $request)
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $customers = $this->getCustomers();
        $statusOptions = $this->getStatusOptions(SalesReturnStatus::class);

        $groupBy = $filters['group_by'] ?? 'document';

        $query = SalesReturn::with(['company', 'branch', 'partner', 'currency', 'salesDelivery'])
            ->when(!empty($filters['company_id']), fn($q) => $q->whereIn('company_id', (array) $filters['company_id']))
            ->when(!empty($filters['branch_id']), fn($q) => $q->whereIn('branch_id', (array) $filters['branch_id']))
            ->when(!empty($filters['partner_id']), fn($q) => $q->where('partner_id', $filters['partner_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->whereBetween('return_date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('return_date', 'desc');

        $data = $this->getGroupedData($query, $groupBy, 'sales_returns', $filters);

        $totals = [
            'total_value' => (clone $query)->sum('total_value'),
            'count' => (clone $query)->count(),
        ];

        return Inertia::render('Reports/Sales/SalesReturnReport', [
            'companies' => $companies,
            'branches' => $branches,
            'customers' => $customers,
            'statusOptions' => $statusOptions,
            'groupingOptions' => self::GROUPING_OPTIONS,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
            'statusLabels' => $this->getStatusLabels(SalesReturnStatus::class),
        ]);
    }

    private function calculateInvoiceCogs(SalesInvoice $invoice): float
    {
        $cogs = 0;
        
        foreach ($invoice->lines as $line) {
            // Use COGS from delivery line if linked
            if ($line->salesDeliveryLine && $line->salesDeliveryLine->cogs_total) {
                // Pro-rate COGS based on quantity invoiced vs delivered
                $deliveredQty = $line->salesDeliveryLine->quantity ?? 1;
                $invoicedQty = $line->quantity ?? 1;
                $lineCogsRate = $line->salesDeliveryLine->cogs_total / $deliveredQty;
                $cogs += $lineCogsRate * $invoicedQty;
            } else {
                // Fallback to delivery_value_base for direct invoices
                $cogs += $line->delivery_value_base ?? 0;
            }
        }

        return $cogs;
    }

    private function getGroupedDataWithCogs($query, string $groupBy, array $filters)
    {
        if ($groupBy === 'document') {
            $paginated = $query->paginate(50)->withQueryString();
            
            // Add COGS and gross profit to each invoice
            $paginated->getCollection()->transform(function ($invoice) {
                $cogs = $this->calculateInvoiceCogs($invoice);
                $revenue = $invoice->total_amount ?? 0;
                $grossProfit = $revenue - $cogs;
                
                $invoice->cogs = $cogs;
                $invoice->gross_profit = $grossProfit;
                $invoice->margin_percentage = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0;
                
                return $invoice;
            });
            
            return $paginated;
        }

        $items = $query->get();
        
        // Add COGS to each invoice
        $items = $items->map(function ($invoice) {
            $cogs = $this->calculateInvoiceCogs($invoice);
            $revenue = $invoice->total_amount ?? 0;
            $grossProfit = $revenue - $cogs;
            
            $invoice->cogs = $cogs;
            $invoice->gross_profit = $grossProfit;
            $invoice->margin_percentage = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0;
            
            return $invoice;
        });

        switch ($groupBy) {
            case 'customer':
                return $items->groupBy('partner_id')
                    ->map(function ($groupItems) {
                        $totalRevenue = $groupItems->sum('total_amount');
                        $totalCogs = $groupItems->sum('cogs');
                        $grossProfit = $totalRevenue - $totalCogs;
                        return [
                            'group_name' => $groupItems->first()->partner?->name ?? 'Tanpa Customer',
                            'count' => $groupItems->count(),
                            'total_value' => $totalRevenue,
                            'total_cogs' => $totalCogs,
                            'gross_profit' => $grossProfit,
                            'margin_percentage' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
                            'items' => $groupItems,
                        ];
                    })->values();

            case 'items':
                return $this->getItemsGroupedDataWithCogs($query);

            case 'creator':
                $creatorIds = $items->pluck('created_by')->unique()->filter();
                $creators = User::whereIn('global_id', $creatorIds)->pluck('name', 'global_id');
                return $items->groupBy('created_by')
                    ->map(function ($groupItems, $createdBy) use ($creators) {
                        $totalRevenue = $groupItems->sum('total_amount');
                        $totalCogs = $groupItems->sum('cogs');
                        $grossProfit = $totalRevenue - $totalCogs;
                        return [
                            'group_name' => $creators[$createdBy] ?? $createdBy ?: 'Unknown',
                            'count' => $groupItems->count(),
                            'total_value' => $totalRevenue,
                            'total_cogs' => $totalCogs,
                            'gross_profit' => $grossProfit,
                            'margin_percentage' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
                            'items' => $groupItems,
                        ];
                    })->values();

            case 'status':
                return $items->groupBy('status')
                    ->map(function ($groupItems, $status) {
                        $totalRevenue = $groupItems->sum('total_amount');
                        $totalCogs = $groupItems->sum('cogs');
                        $grossProfit = $totalRevenue - $totalCogs;
                        return [
                            'group_name' => $status,
                            'count' => $groupItems->count(),
                            'total_value' => $totalRevenue,
                            'total_cogs' => $totalCogs,
                            'gross_profit' => $grossProfit,
                            'margin_percentage' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
                            'items' => $groupItems,
                        ];
                    })->values();

            case 'branch':
                return $items->groupBy('branch_id')
                    ->map(function ($groupItems) {
                        $totalRevenue = $groupItems->sum('total_amount');
                        $totalCogs = $groupItems->sum('cogs');
                        $grossProfit = $totalRevenue - $totalCogs;
                        return [
                            'group_name' => $groupItems->first()->branch?->name ?? 'Tanpa Cabang',
                            'count' => $groupItems->count(),
                            'total_value' => $totalRevenue,
                            'total_cogs' => $totalCogs,
                            'gross_profit' => $grossProfit,
                            'margin_percentage' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
                            'items' => $groupItems,
                        ];
                    })->values();

            case 'company':
                return $items->groupBy('company_id')
                    ->map(function ($groupItems) {
                        $totalRevenue = $groupItems->sum('total_amount');
                        $totalCogs = $groupItems->sum('cogs');
                        $grossProfit = $totalRevenue - $totalCogs;
                        return [
                            'group_name' => $groupItems->first()->company?->name ?? 'Tanpa Perusahaan',
                            'count' => $groupItems->count(),
                            'total_value' => $totalRevenue,
                            'total_cogs' => $totalCogs,
                            'gross_profit' => $grossProfit,
                            'margin_percentage' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
                            'items' => $groupItems,
                        ];
                    })->values();

            default:
                return $query->paginate(50)->withQueryString();
        }
    }

    private function getItemsGroupedDataWithCogs($query)
    {
        $documents = $query->with(['lines.salesDeliveryLine', 'lines.salesOrderLine.product', 'lines.salesOrderLine.variant'])->get();

        $itemsData = collect();

        foreach ($documents as $document) {
            if (!$document->lines) continue;
            
            foreach ($document->lines as $line) {
                $productName = $line->salesOrderLine?->product?->name ?? $line->description ?? 'Unknown';
                $variantName = $line->salesOrderLine?->variant?->name ?? null;
                
                // Calculate line COGS
                $lineCogs = 0;
                if ($line->salesDeliveryLine && $line->salesDeliveryLine->cogs_total) {
                    $deliveredQty = $line->salesDeliveryLine->quantity ?? 1;
                    $invoicedQty = $line->quantity ?? 1;
                    $lineCogsRate = $line->salesDeliveryLine->cogs_total / $deliveredQty;
                    $lineCogs = $lineCogsRate * $invoicedQty;
                } else {
                    $lineCogs = $line->delivery_value_base ?? 0;
                }

                $itemsData->push([
                    'document' => $document,
                    'line' => $line,
                    'product_name' => $productName,
                    'variant_name' => $variantName,
                    'quantity' => $line->quantity ?? 0,
                    'unit_price' => $line->unit_price ?? 0,
                    'total' => $line->line_total ?? (($line->quantity ?? 0) * ($line->unit_price ?? 0)),
                    'cogs' => $lineCogs,
                    'gross_profit' => ($line->line_total ?? 0) - $lineCogs,
                ]);
            }
        }

        return $itemsData->groupBy('product_name')
            ->map(function ($items, $productName) {
                $totalRevenue = $items->sum('total');
                $totalCogs = $items->sum('cogs');
                $grossProfit = $totalRevenue - $totalCogs;
                return [
                    'group_name' => $productName,
                    'count' => $items->count(),
                    'total_quantity' => $items->sum('quantity'),
                    'total_value' => $totalRevenue,
                    'total_cogs' => $totalCogs,
                    'gross_profit' => $grossProfit,
                    'margin_percentage' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
                    'items' => $items,
                ];
            })->values();
    }

    private function getGroupedData($query, string $groupBy, string $reportType, array $filters)
    {
        if ($groupBy === 'document') {
            return $query->paginate(50)->withQueryString();
        }

        $valueField = in_array($reportType, ['sales_deliveries', 'sales_returns']) ? 'total_amount' : 'total_amount';
        if ($reportType === 'sales_returns') {
            $valueField = 'total_value';
        }

        switch ($groupBy) {
            case 'customer':
                return $query->get()->groupBy('partner_id')
                    ->map(function ($items) use ($valueField) {
                        return [
                            'group_name' => $items->first()->partner?->name ?? 'Tanpa Customer',
                            'count' => $items->count(),
                            'total_value' => $items->sum($valueField),
                            'items' => $items,
                        ];
                    })->values();

            case 'items':
                return $this->getItemsGroupedData($query, $reportType);

            case 'creator':
                $creatorIds = $query->pluck('created_by')->unique()->filter();
                $creators = User::whereIn('global_id', $creatorIds)->pluck('name', 'global_id');
                return $query->get()->groupBy('created_by')
                    ->map(function ($items, $createdBy) use ($valueField, $creators) {
                        return [
                            'group_name' => $creators[$createdBy] ?? $createdBy ?: 'Unknown',
                            'count' => $items->count(),
                            'total_value' => $items->sum($valueField),
                            'items' => $items,
                        ];
                    })->values();

            case 'status':
                return $query->get()->groupBy('status')
                    ->map(function ($items, $status) use ($valueField) {
                        return [
                            'group_name' => $status,
                            'count' => $items->count(),
                            'total_value' => $items->sum($valueField),
                            'items' => $items,
                        ];
                    })->values();

            case 'branch':
                return $query->get()->groupBy('branch_id')
                    ->map(function ($items) use ($valueField) {
                        return [
                            'group_name' => $items->first()->branch?->name ?? 'Tanpa Cabang',
                            'count' => $items->count(),
                            'total_value' => $items->sum($valueField),
                            'items' => $items,
                        ];
                    })->values();

            case 'company':
                return $query->get()->groupBy('company_id')
                    ->map(function ($items) use ($valueField) {
                        return [
                            'group_name' => $items->first()->company?->name ?? 'Tanpa Perusahaan',
                            'count' => $items->count(),
                            'total_value' => $items->sum($valueField),
                            'items' => $items,
                        ];
                    })->values();

            default:
                return $query->paginate(50)->withQueryString();
        }
    }

    private function getItemsGroupedData($query, string $reportType)
    {
        $lineRelations = match ($reportType) {
            'sales_invoices' => ['lines.salesOrderLine.product', 'lines.salesOrderLine.variant'],
            default => ['lines.product', 'lines.variant'],
        };

        $documents = $query->with($lineRelations)->get();

        $itemsData = collect();

        foreach ($documents as $document) {
            if (!$document->lines) continue;
            
            foreach ($document->lines as $line) {
                // Get product name based on report type
                if ($reportType === 'sales_invoices') {
                    $productName = $line->salesOrderLine?->product?->name ?? $line->description ?? 'Unknown';
                    $variantName = $line->salesOrderLine?->variant?->name ?? null;
                } else {
                    $productName = $line->product?->name ?? $line->description ?? 'Unknown';
                    $variantName = $line->variant?->name ?? null;
                }

                $itemsData->push([
                    'document' => $document,
                    'line' => $line,
                    'product_name' => $productName,
                    'variant_name' => $variantName,
                    'quantity' => $line->quantity ?? 0,
                    'unit_price' => $line->unit_price ?? 0,
                    'total' => $line->line_total ?? (($line->quantity ?? 0) * ($line->unit_price ?? 0)),
                ]);
            }
        }

        return $itemsData->groupBy('product_name')
            ->map(function ($items, $productName) {
                return [
                    'group_name' => $productName,
                    'count' => $items->count(),
                    'total_quantity' => $items->sum('quantity'),
                    'total_value' => $items->sum('total'),
                    'items' => $items,
                ];
            })->values();
    }

    private function getDefaultFilters(Request $request): array
    {
        $filters = $request->all();
        $filters['start_date'] = $filters['start_date'] ?? date('Y-m-01');
        $filters['end_date'] = $filters['end_date'] ?? date('Y-m-d');
        $filters['group_by'] = $filters['group_by'] ?? 'document';
        return $filters;
    }

    private function getBranches(array $filters)
    {
        $query = Branch::query();
        if (!empty($filters['company_id'])) {
            $query->whereHas('branchGroup', fn($q) => $q->whereIn('company_id', (array) $filters['company_id']));
        }
        return $query->orderBy('name', 'asc')->get();
    }

    private function getCustomers()
    {
        return Partner::whereHas('roles', fn($q) => $q->where('role', 'customer'))
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);
    }

    private function getStatusOptions(string $enumClass): array
    {
        $options = [['value' => '', 'label' => 'Semua Status']];

        foreach ($enumClass::cases() as $case) {
            $options[] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }

        return $options;
    }

    private function getStatusLabels(string $enumClass): array
    {
        $labels = [];
        foreach ($enumClass::cases() as $case) {
            $labels[$case->value] = $case->label();
        }
        return $labels;
    }
}
