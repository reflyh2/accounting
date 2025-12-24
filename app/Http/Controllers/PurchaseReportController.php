<?php

namespace App\Http\Controllers;

use App\Enums\Documents\GoodsReceiptStatus;
use App\Enums\Documents\InvoiceStatus;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Enums\Documents\PurchaseReturnStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseReportController extends Controller
{
    private const GROUPING_OPTIONS = [
        ['value' => 'document', 'label' => 'Per Dokumen'],
        ['value' => 'supplier', 'label' => 'Per Supplier'],
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

        return Inertia::render('Reports/Purchasing/Overview', [
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'summaryData' => $summaryData,
            'chartData' => $chartData,
        ]);
    }

    private function getChartData($filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        return [
            'topSuppliers' => $this->getTopSuppliersData($filters),
            'statusDistribution' => $this->getStatusDistributionData($filters),
            'monthlyTrend' => $this->getMonthlyTrendData($filters),
            'byBranch' => $this->getByBranchData($filters),
        ];
    }

    private function getTopSuppliersData($filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $query = PurchaseOrder::query()
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

        // PO Status
        $poQuery = PurchaseOrder::query()->whereBetween('order_date', [$startDate, $endDate]);
        $applyFilters($poQuery);
        $poStatus = $poQuery->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Invoice Status
        $piQuery = PurchaseInvoice::query()->whereBetween('invoice_date', [$startDate, $endDate]);
        $applyFilters($piQuery);
        $piStatus = $piQuery->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'po' => [
                'labels' => array_keys($poStatus),
                'data' => array_values($poStatus),
            ],
            'invoice' => [
                'labels' => array_keys($piStatus),
                'data' => array_values($piStatus),
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

        // Get monthly PO data
        $poQuery = PurchaseOrder::query()
            ->selectRaw("TO_CHAR(order_date, 'YYYY-MM') as month, SUM(total_amount) as total_value")
            ->whereBetween('order_date', [$startDate, $endDate]);
        $applyFilters($poQuery);
        $poMonthly = $poQuery->groupBy('month')
            ->orderBy('month')
            ->pluck('total_value', 'month')
            ->toArray();

        // Get monthly GRN data
        $grQuery = GoodsReceipt::query()
            ->selectRaw("TO_CHAR(receipt_date, 'YYYY-MM') as month, SUM(total_value) as total_value")
            ->whereBetween('receipt_date', [$startDate, $endDate]);
        $applyFilters($grQuery);
        $grMonthly = $grQuery->groupBy('month')
            ->orderBy('month')
            ->pluck('total_value', 'month')
            ->toArray();

        // Merge months
        $allMonths = array_unique(array_merge(array_keys($poMonthly), array_keys($grMonthly)));
        sort($allMonths);

        return [
            'labels' => $allMonths,
            'datasets' => [
                [
                    'label' => 'Purchase Orders',
                    'data' => array_map(fn($m) => (float) ($poMonthly[$m] ?? 0), $allMonths),
                ],
                [
                    'label' => 'Goods Receipts',
                    'data' => array_map(fn($m) => (float) ($grMonthly[$m] ?? 0), $allMonths),
                ],
            ],
        ];
    }

    private function getByBranchData($filters)
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $query = PurchaseOrder::query()
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

        $poQuery = PurchaseOrder::query();
        $applyFilters($poQuery);
        $poQuery->whereBetween('order_date', [$startDate, $endDate]);

        $poSummary = [
            'total_count' => (clone $poQuery)->count(),
            'total_value' => (clone $poQuery)->sum('total_amount'),
            'draft_count' => (clone $poQuery)->where('status', 'draft')->count(),
            'approved_count' => (clone $poQuery)->where('status', 'approved')->count(),
            'sent_count' => (clone $poQuery)->where('status', 'sent')->count(),
        ];

        $grQuery = GoodsReceipt::query();
        $applyFilters($grQuery);
        $grQuery->whereBetween('receipt_date', [$startDate, $endDate]);

        $grSummary = [
            'total_count' => (clone $grQuery)->count(),
            'total_value' => (clone $grQuery)->sum('total_value'),
            'draft_count' => (clone $grQuery)->where('status', 'draft')->count(),
            'posted_count' => (clone $grQuery)->where('status', 'posted')->count(),
        ];

        $piQuery = PurchaseInvoice::query();
        $applyFilters($piQuery);
        $piQuery->whereBetween('invoice_date', [$startDate, $endDate]);

        $piSummary = [
            'total_count' => (clone $piQuery)->count(),
            'total_value' => (clone $piQuery)->sum('total_amount'),
            'draft_count' => (clone $piQuery)->where('status', 'draft')->count(),
            'posted_count' => (clone $piQuery)->where('status', 'posted')->count(),
            'paid_count' => (clone $piQuery)->where('status', 'paid')->count(),
        ];

        $prQuery = PurchaseReturn::query();
        $applyFilters($prQuery);
        $prQuery->whereBetween('return_date', [$startDate, $endDate]);

        $prSummary = [
            'total_count' => (clone $prQuery)->count(),
            'total_value' => (clone $prQuery)->sum('total_value'),
            'draft_count' => (clone $prQuery)->where('status', 'draft')->count(),
            'posted_count' => (clone $prQuery)->where('status', 'posted')->count(),
        ];

        return [
            'purchase_orders' => $poSummary,
            'goods_receipts' => $grSummary,
            'purchase_invoices' => $piSummary,
            'purchase_returns' => $prSummary,
        ];
    }

    public function purchaseOrders(Request $request)
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $suppliers = $this->getSuppliers();
        $statusOptions = $this->getStatusOptions(PurchaseOrderStatus::class);

        $groupBy = $filters['group_by'] ?? 'document';

        $query = PurchaseOrder::with(['company', 'branch', 'partner', 'currency'])
            ->when(!empty($filters['company_id']), fn($q) => $q->whereIn('company_id', (array) $filters['company_id']))
            ->when(!empty($filters['branch_id']), fn($q) => $q->whereIn('branch_id', (array) $filters['branch_id']))
            ->when(!empty($filters['partner_id']), fn($q) => $q->where('partner_id', $filters['partner_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->whereBetween('order_date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('order_date', 'desc');

        $data = $this->getGroupedData($query, $groupBy, 'purchase_orders', $filters);

        $totals = [
            'total_amount' => (clone $query)->sum('total_amount'),
            'count' => (clone $query)->count(),
        ];

        return Inertia::render('Reports/Purchasing/PurchaseOrderReport', [
            'companies' => $companies,
            'branches' => $branches,
            'suppliers' => $suppliers,
            'statusOptions' => $statusOptions,
            'groupingOptions' => self::GROUPING_OPTIONS,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
            'statusLabels' => $this->getStatusLabels(PurchaseOrderStatus::class),
        ]);
    }

    public function goodsReceipts(Request $request)
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $suppliers = $this->getSuppliers();
        $statusOptions = $this->getStatusOptions(GoodsReceiptStatus::class);

        $groupBy = $filters['group_by'] ?? 'document';

        $query = GoodsReceipt::with(['company', 'branch', 'supplier', 'currency'])
            ->when(!empty($filters['company_id']), fn($q) => $q->whereIn('company_id', (array) $filters['company_id']))
            ->when(!empty($filters['branch_id']), fn($q) => $q->whereIn('branch_id', (array) $filters['branch_id']))
            ->when(!empty($filters['partner_id']), fn($q) => $q->where('supplier_id', $filters['partner_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->whereBetween('receipt_date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('receipt_date', 'desc');

        $data = $this->getGroupedData($query, $groupBy, 'goods_receipts', $filters);

        $totals = [
            'total_value' => (clone $query)->sum('total_value'),
            'count' => (clone $query)->count(),
        ];

        return Inertia::render('Reports/Purchasing/GoodsReceiptReport', [
            'companies' => $companies,
            'branches' => $branches,
            'suppliers' => $suppliers,
            'statusOptions' => $statusOptions,
            'groupingOptions' => self::GROUPING_OPTIONS,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
            'statusLabels' => $this->getStatusLabels(GoodsReceiptStatus::class),
        ]);
    }

    public function purchaseInvoices(Request $request)
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $suppliers = $this->getSuppliers();
        $statusOptions = $this->getStatusOptions(InvoiceStatus::class);

        $groupBy = $filters['group_by'] ?? 'document';

        $query = PurchaseInvoice::with(['company', 'branch', 'partner', 'currency'])
            ->when(!empty($filters['company_id']), fn($q) => $q->whereIn('company_id', (array) $filters['company_id']))
            ->when(!empty($filters['branch_id']), fn($q) => $q->whereIn('branch_id', (array) $filters['branch_id']))
            ->when(!empty($filters['partner_id']), fn($q) => $q->where('partner_id', $filters['partner_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->whereBetween('invoice_date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('invoice_date', 'desc');

        $data = $this->getGroupedData($query, $groupBy, 'purchase_invoices', $filters);

        $totals = [
            'total_amount' => (clone $query)->sum('total_amount'),
            'count' => (clone $query)->count(),
        ];

        return Inertia::render('Reports/Purchasing/PurchaseInvoiceReport', [
            'companies' => $companies,
            'branches' => $branches,
            'suppliers' => $suppliers,
            'statusOptions' => $statusOptions,
            'groupingOptions' => self::GROUPING_OPTIONS,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
            'statusLabels' => $this->getStatusLabels(InvoiceStatus::class),
        ]);
    }

    public function purchaseReturns(Request $request)
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $suppliers = $this->getSuppliers();
        $statusOptions = $this->getStatusOptions(PurchaseReturnStatus::class);

        $groupBy = $filters['group_by'] ?? 'document';

        $query = PurchaseReturn::with(['company', 'branch', 'partner', 'currency', 'goodsReceipt'])
            ->when(!empty($filters['company_id']), fn($q) => $q->whereIn('company_id', (array) $filters['company_id']))
            ->when(!empty($filters['branch_id']), fn($q) => $q->whereIn('branch_id', (array) $filters['branch_id']))
            ->when(!empty($filters['partner_id']), fn($q) => $q->where('partner_id', $filters['partner_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->whereBetween('return_date', [$filters['start_date'], $filters['end_date']])
            ->orderBy('return_date', 'desc');

        $data = $this->getGroupedData($query, $groupBy, 'purchase_returns', $filters);

        $totals = [
            'total_value' => (clone $query)->sum('total_value'),
            'count' => (clone $query)->count(),
        ];

        return Inertia::render('Reports/Purchasing/PurchaseReturnReport', [
            'companies' => $companies,
            'branches' => $branches,
            'suppliers' => $suppliers,
            'statusOptions' => $statusOptions,
            'groupingOptions' => self::GROUPING_OPTIONS,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
            'statusLabels' => $this->getStatusLabels(PurchaseReturnStatus::class),
        ]);
    }

    private function getGroupedData($query, string $groupBy, string $reportType, array $filters)
    {
        if ($groupBy === 'document') {
            return $query->paginate(50)->withQueryString();
        }

        $valueField = in_array($reportType, ['goods_receipts', 'purchase_returns']) ? 'total_value' : 'total_amount';

        switch ($groupBy) {
            case 'supplier':
                $supplierField = $reportType === 'goods_receipts' ? 'supplier_id' : 'partner_id';
                $supplierRelation = $reportType === 'goods_receipts' ? 'supplier' : 'partner';
                return $query->get()->groupBy($supplierField)
                    ->map(function ($items) use ($valueField, $supplierRelation) {
                        $supplier = $items->first()->{$supplierRelation};
                        return [
                            'group_name' => $supplier?->name ?? 'Tanpa Supplier',
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
        // Different line models have different relationships:
        // PurchaseOrderLine, GoodsReceiptLine, PurchaseReturnLine: product(), variant()
        // PurchaseInvoiceLine: productVariant() only (no product)
        $lineRelations = match ($reportType) {
            'purchase_invoices' => ['lines.productVariant.product'],
            default => ['lines.product', 'lines.variant'],
        };

        $documents = $query->with($lineRelations)->get();

        $itemsData = collect();

        foreach ($documents as $document) {
            if (!$document->lines) continue;
            
            foreach ($document->lines as $line) {
                // Get product name based on report type
                if ($reportType === 'purchase_invoices') {
                    $productName = $line->productVariant?->product?->name ?? $line->description ?? 'Unknown';
                    $variantName = $line->productVariant?->name ?? null;
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

    private function getSuppliers()
    {
        return Partner::whereHas('roles', fn($q) => $q->where('role', 'supplier'))
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
