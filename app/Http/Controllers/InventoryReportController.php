<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CostLayer;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionLine;
use App\Models\Location;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryReportController extends Controller
{
    private const TRANSACTION_TYPES = [
        ['value' => '', 'label' => 'Semua Tipe'],
        ['value' => 'receipt', 'label' => 'Penerimaan'],
        ['value' => 'issue', 'label' => 'Pengeluaran'],
        ['value' => 'adjustment', 'label' => 'Penyesuaian'],
        ['value' => 'transfer', 'label' => 'Transfer'],
    ];

    private const TRANSACTION_TYPE_LABELS = [
        'receipt' => 'Penerimaan',
        'issue' => 'Pengeluaran',
        'adjustment' => 'Penyesuaian',
        'transfer' => 'Transfer',
    ];

    public function index(Request $request): \Inertia\Response
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $summaryData = $this->getSummaryData($filters);
        $chartData = $this->getChartData($filters);

        return Inertia::render('Reports/Inventory/Overview', [
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'summaryData' => $summaryData,
            'chartData' => $chartData,
        ]);
    }

    public function stockMovement(Request $request): \Inertia\Response
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $locations = $this->getLocations($filters);
        $categories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $data = $this->getMovementSummaryData($filters);
        $totals = $this->getMovementTotals($filters);

        return Inertia::render('Reports/Inventory/StockMovementReport', [
            'companies' => $companies,
            'branches' => $branches,
            'locations' => $locations,
            'categories' => $categories,
            'transactionTypes' => self::TRANSACTION_TYPES,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
            'typeLabels' => self::TRANSACTION_TYPE_LABELS,
        ]);
    }

    public function stockValuation(Request $request): \Inertia\Response
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $locations = $this->getLocations($filters);
        $categories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $data = $this->getValuationData($filters);

        $totals = $this->getValuationTotals($filters);

        return Inertia::render('Reports/Inventory/StockValuationReport', [
            'companies' => $companies,
            'branches' => $branches,
            'locations' => $locations,
            'categories' => $categories,
            'filters' => $filters,
            'data' => $data,
            'totals' => $totals,
        ]);
    }

    public function stockCard(Request $request): \Inertia\Response
    {
        $filters = $this->getDefaultFilters($request);
        $companies = Company::orderBy('name', 'asc')->get();
        $branches = $this->getBranches($filters);
        $locations = $this->getLocations($filters);
        $products = $this->getProducts();

        $data = null;
        $product = null;
        $openingBalance = 0;
        $openingValue = 0;

        if (! empty($filters['product_variant_id'])) {
            $product = ProductVariant::with(['product', 'uom'])->find($filters['product_variant_id']);

            if ($product) {
                $locationFilter = $filters['location_id'] ?? null;

                // Opening balance: all movements before start_date
                $opening = InventoryTransactionLine::query()
                    ->where('product_variant_id', $filters['product_variant_id'])
                    ->whereHas('transaction', function ($q) use ($filters, $locationFilter) {
                        $q->where('transaction_date', '<', $filters['start_date']);
                        if ($locationFilter) {
                            $q->where(function ($sub) use ($locationFilter) {
                                $sub->where('location_id_from', $locationFilter)
                                    ->orWhere('location_id_to', $locationFilter);
                            });
                        }
                    })
                    ->selectRaw("
                        COALESCE(SUM(CASE WHEN effect = 'in' THEN quantity ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN effect = 'out' THEN quantity ELSE 0 END), 0) as balance,
                        COALESCE(SUM(CASE WHEN effect = 'in' THEN quantity * unit_cost ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN effect = 'out' THEN quantity * unit_cost ELSE 0 END), 0) as total_value
                    ")
                    ->first();

                $openingBalance = (float) ($opening->balance ?? 0);
                $openingValue = (float) ($opening->total_value ?? 0);

                // Movements in period
                $movements = InventoryTransactionLine::query()
                    ->with(['transaction.locationFrom', 'transaction.locationTo', 'uom'])
                    ->where('product_variant_id', $filters['product_variant_id'])
                    ->whereHas('transaction', function ($q) use ($filters, $locationFilter) {
                        $q->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']]);
                        if ($locationFilter) {
                            $q->where(function ($sub) use ($locationFilter) {
                                $sub->where('location_id_from', $locationFilter)
                                    ->orWhere('location_id_to', $locationFilter);
                            });
                        }
                    })
                    ->join('inventory_transactions', 'inventory_transactions.id', '=', 'inventory_transaction_lines.inventory_transaction_id')
                    ->orderBy('inventory_transactions.transaction_date', 'asc')
                    ->orderBy('inventory_transactions.id', 'asc')
                    ->select('inventory_transaction_lines.*')
                    ->get();

                // Compute running balance with valuation
                $balance = $openingBalance;
                $balanceValue = $openingValue;
                $data = $movements->map(function ($line) use (&$balance, &$balanceValue) {
                    $in = $line->effect === 'in' ? (float) $line->quantity : 0;
                    $out = $line->effect === 'out' ? (float) $line->quantity : 0;
                    $unitCost = (float) $line->unit_cost;
                    $lineValue = ($in - $out) * $unitCost;
                    $balance += $in - $out;
                    $balanceValue += $lineValue;

                    return [
                        'date' => $line->transaction->transaction_date,
                        'transaction_number' => $line->transaction->transaction_number,
                        'transaction_type' => $line->transaction->transaction_type,
                        'location_from' => $line->transaction->locationFrom?->name,
                        'location_to' => $line->transaction->locationTo?->name,
                        'uom' => $line->uom?->abbreviation ?? $line->uom?->name,
                        'qty_in' => $in,
                        'qty_out' => $out,
                        'unit_cost' => $unitCost,
                        'balance' => $balance,
                        'balance_value' => $balanceValue,
                    ];
                })->values();
            }
        }

        return Inertia::render('Reports/Inventory/StockCardReport', [
            'companies' => $companies,
            'branches' => $branches,
            'locations' => $locations,
            'products' => $products,
            'filters' => $filters,
            'data' => $data,
            'product' => $product,
            'openingBalance' => $openingBalance,
            'openingValue' => $openingValue,
            'typeLabels' => self::TRANSACTION_TYPE_LABELS,
        ]);
    }

    // ─── Private Helpers ───

    private function getValuationData(array $filters)
    {
        // Get inventory items with valuation from cost layers
        $query = InventoryItem::query()
            ->with(['productVariant.product.category', 'productVariant.uom', 'location.branch'])
            ->where('qty_on_hand', '>', 0);

        $this->applyInventoryItemFilters($query, $filters);

        return $query->orderBy('product_variant_id')
            ->paginate(50)
            ->withQueryString()
            ->through(function ($item) {
                $costData = CostLayer::query()
                    ->where('product_variant_id', $item->product_variant_id)
                    ->where('location_id', $item->location_id)
                    ->where('qty_remaining', '>', 0)
                    ->selectRaw('SUM(qty_remaining * unit_cost) as total_value, CASE WHEN SUM(qty_remaining) > 0 THEN SUM(qty_remaining * unit_cost) / SUM(qty_remaining) ELSE 0 END as avg_cost')
                    ->first();

                $avgCost = (float) ($costData->avg_cost ?? 0);
                $qtyOnHand = (float) $item->qty_on_hand;
                $qtyReserved = (float) $item->qty_reserved;
                $qtyAvailable = $qtyOnHand - $qtyReserved;

                return [
                    'id' => $item->id,
                    'product_name' => $item->productVariant?->product?->name ?? '-',
                    'variant_name' => $item->productVariant?->name ?? '-',
                    'category' => $item->productVariant?->product?->category?->name ?? '-',
                    'uom' => $item->productVariant?->uom?->abbreviation ?? $item->productVariant?->uom?->name ?? '-',
                    'location' => $item->location?->name ?? '-',
                    'branch' => $item->location?->branch?->name ?? '-',
                    'qty_on_hand' => $qtyOnHand,
                    'qty_reserved' => $qtyReserved,
                    'qty_available' => $qtyAvailable,
                    'avg_cost' => $avgCost,
                    'value_on_hand' => $qtyOnHand * $avgCost,
                    'value_reserved' => $qtyReserved * $avgCost,
                    'value_available' => $qtyAvailable * $avgCost,
                ];
            });
    }

    private function getValuationTotals(array $filters): array
    {
        $itemQuery = InventoryItem::query()->where('qty_on_hand', '>', 0);
        $this->applyInventoryItemFilters($itemQuery, $filters);

        $totalOnHand = 0.0;
        $totalReserved = 0.0;
        $totalValueOnHand = 0.0;
        $totalValueReserved = 0.0;
        $totalValueAvailable = 0.0;

        // Iterate all items to compute value totals using per-item avg cost
        (clone $itemQuery)->chunk(200, function ($items) use (&$totalOnHand, &$totalReserved, &$totalValueOnHand, &$totalValueReserved, &$totalValueAvailable) {
            foreach ($items as $item) {
                $avgCost = (float) (CostLayer::query()
                    ->where('product_variant_id', $item->product_variant_id)
                    ->where('location_id', $item->location_id)
                    ->where('qty_remaining', '>', 0)
                    ->selectRaw('CASE WHEN SUM(qty_remaining) > 0 THEN SUM(qty_remaining * unit_cost) / SUM(qty_remaining) ELSE 0 END as avg_cost')
                    ->value('avg_cost') ?? 0);

                $qtyOnHand = (float) $item->qty_on_hand;
                $qtyReserved = (float) $item->qty_reserved;
                $qtyAvailable = $qtyOnHand - $qtyReserved;

                $totalOnHand += $qtyOnHand;
                $totalReserved += $qtyReserved;
                $totalValueOnHand += $qtyOnHand * $avgCost;
                $totalValueReserved += $qtyReserved * $avgCost;
                $totalValueAvailable += $qtyAvailable * $avgCost;
            }
        });

        return [
            'total_qty_on_hand' => $totalOnHand,
            'total_qty_reserved' => $totalReserved,
            'total_qty_available' => $totalOnHand - $totalReserved,
            'total_value_on_hand' => $totalValueOnHand,
            'total_value_reserved' => $totalValueReserved,
            'total_value_available' => $totalValueAvailable,
            'distinct_products' => (clone $itemQuery)->distinct('product_variant_id')->count('product_variant_id'),
        ];
    }

    private function getMovementTotals(array $filters): array
    {
        $lineQuery = InventoryTransactionLine::query()
            ->whereHas('transaction', function ($q) use ($filters) {
                $q->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']]);
                $this->applyTransactionBranchFilters($q, $filters);
                if (! empty($filters['transaction_type'])) {
                    $q->where('transaction_type', $filters['transaction_type']);
                }
                if (! empty($filters['location_id'])) {
                    $q->where(function ($sub) use ($filters) {
                        $sub->where('location_id_from', $filters['location_id'])
                            ->orWhere('location_id_to', $filters['location_id']);
                    });
                }
            })
            ->when(! empty($filters['category_id']), fn ($q) => $q->whereHas('productVariant.product',
                fn ($sub) => $sub->where('product_category_id', $filters['category_id'])));

        $totals = (clone $lineQuery)->selectRaw("
            SUM(CASE WHEN effect = 'in' THEN quantity * unit_cost ELSE 0 END) as total_value_in,
            SUM(CASE WHEN effect = 'out' THEN quantity * unit_cost ELSE 0 END) as total_value_out
        ")->first();

        $totalValueIn = (float) ($totals->total_value_in ?? 0);
        $totalValueOut = (float) ($totals->total_value_out ?? 0);

        // Beginning value: all movements before start_date for products that had movements in period
        $variantIds = (clone $lineQuery)->distinct()->pluck('product_variant_id');

        $beginTotals = InventoryTransactionLine::query()
            ->whereIn('product_variant_id', $variantIds)
            ->whereHas('transaction', fn ($q) => $q->where('transaction_date', '<', $filters['start_date']))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN effect = 'in' THEN quantity * unit_cost ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN effect = 'out' THEN quantity * unit_cost ELSE 0 END), 0) as begin_value
            ")
            ->first();

        $beginValue = (float) ($beginTotals->begin_value ?? 0);
        $endValue = $beginValue + $totalValueIn - $totalValueOut;

        return [
            'begin_value' => $beginValue,
            'value_in' => $totalValueIn,
            'value_out' => $totalValueOut,
            'end_value' => $endValue,
        ];
    }

    private function getMovementSummaryData(array $filters)
    {
        // Get all product variants that have movements in the period
        $lineQuery = InventoryTransactionLine::query()
            ->with(['productVariant.product.category', 'productVariant.uom'])
            ->whereHas('transaction', function ($q) use ($filters) {
                $q->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']]);
                $this->applyTransactionBranchFilters($q, $filters);
                if (! empty($filters['transaction_type'])) {
                    $q->where('transaction_type', $filters['transaction_type']);
                }
                if (! empty($filters['location_id'])) {
                    $q->where(function ($sub) use ($filters) {
                        $sub->where('location_id_from', $filters['location_id'])
                            ->orWhere('location_id_to', $filters['location_id']);
                    });
                }
            })
            ->when(! empty($filters['category_id']), fn ($q) => $q->whereHas('productVariant.product',
                fn ($sub) => $sub->where('product_category_id', $filters['category_id'])));

        // Get unique product variant IDs with movement data
        $movementData = (clone $lineQuery)
            ->select('product_variant_id')
            ->selectRaw("
                SUM(CASE WHEN effect = 'in' THEN quantity ELSE 0 END) as qty_in,
                SUM(CASE WHEN effect = 'out' THEN quantity ELSE 0 END) as qty_out,
                SUM(CASE WHEN effect = 'in' THEN quantity * unit_cost ELSE 0 END) as value_in,
                SUM(CASE WHEN effect = 'out' THEN quantity * unit_cost ELSE 0 END) as value_out
            ")
            ->groupBy('product_variant_id')
            ->paginate(50)
            ->withQueryString();

        // For each product, compute beginning/ending balances
        $movementData->through(function ($row) use ($filters) {
            $variant = ProductVariant::with(['product.category', 'uom'])->find($row->product_variant_id);

            // Beginning balance: all movements before start_date
            $beginning = InventoryTransactionLine::query()
                ->where('product_variant_id', $row->product_variant_id)
                ->whereHas('transaction', fn ($q) => $q->where('transaction_date', '<', $filters['start_date']))
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN effect = 'in' THEN quantity ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN effect = 'out' THEN quantity ELSE 0 END), 0) as qty,
                    COALESCE(SUM(CASE WHEN effect = 'in' THEN quantity * unit_cost ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN effect = 'out' THEN quantity * unit_cost ELSE 0 END), 0) as value
                ")
                ->first();

            $beginQty = (float) ($beginning->qty ?? 0);
            $beginValue = (float) ($beginning->value ?? 0);
            $qtyIn = (float) $row->qty_in;
            $qtyOut = (float) $row->qty_out;
            $valueIn = (float) $row->value_in;
            $valueOut = (float) $row->value_out;
            $endQty = $beginQty + $qtyIn - $qtyOut;
            $endValue = $beginValue + $valueIn - $valueOut;

            return [
                'product_variant_id' => $row->product_variant_id,
                'product_name' => $variant?->product?->name ?? '-',
                'variant_name' => $variant?->name ?? '-',
                'category' => $variant?->product?->category?->name ?? '-',
                'uom' => $variant?->uom?->abbreviation ?? $variant?->uom?->name ?? '-',
                'begin_qty' => $beginQty,
                'begin_avg_cost' => $beginQty > 0 ? $beginValue / $beginQty : 0,
                'begin_value' => $beginValue,
                'qty_in' => $qtyIn,
                'avg_cost_in' => $qtyIn > 0 ? $valueIn / $qtyIn : 0,
                'value_in' => $valueIn,
                'qty_out' => $qtyOut,
                'avg_cost_out' => $qtyOut > 0 ? $valueOut / $qtyOut : 0,
                'value_out' => $valueOut,
                'end_qty' => $endQty,
                'end_avg_cost' => $endQty > 0 ? $endValue / $endQty : 0,
                'end_value' => $endValue,
            ];
        });

        return $movementData;
    }

    private function getSummaryData(array $filters): array
    {
        $totalItems = InventoryItem::query()
            ->where('qty_on_hand', '>', 0)
            ->distinct('product_variant_id')
            ->count('product_variant_id');

        $totalValue = (float) (CostLayer::query()
            ->where('qty_remaining', '>', 0)
            ->selectRaw('SUM(qty_remaining * unit_cost) as val')
            ->value('val') ?? 0);

        $applyFilters = function ($query) use ($filters) {
            if (! empty($filters['branch_id'])) {
                $query->whereHas('locationFrom', fn ($q) => $q->whereIn('branch_id', (array) $filters['branch_id']))
                    ->orWhereHas('locationTo', fn ($q) => $q->whereIn('branch_id', (array) $filters['branch_id']));
            }
        };

        $txnQuery = InventoryTransaction::query()
            ->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']]);
        $applyFilters($txnQuery);

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

    private function getChartData(array $filters): array
    {
        return [
            'stockByCategory' => $this->getStockByCategoryData(),
            'topItemsByValue' => $this->getTopItemsByValueData(),
            'movementTrend' => $this->getMovementTrendData($filters),
            'movementByType' => $this->getMovementByTypeData($filters),
        ];
    }

    private function getStockByCategoryData(): array
    {
        $results = InventoryItem::query()
            ->join('product_variants', 'inventory_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('product_categories', 'products.product_category_id', '=', 'product_categories.id')
            ->where('inventory_items.qty_on_hand', '>', 0)
            ->selectRaw('product_categories.name as category_name, SUM(inventory_items.qty_on_hand) as total_qty')
            ->groupBy('product_categories.id', 'product_categories.name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        return [
            'labels' => $results->pluck('category_name')->toArray(),
            'data' => $results->pluck('total_qty')->map(fn ($v) => (float) $v)->toArray(),
        ];
    }

    private function getTopItemsByValueData(): array
    {
        $results = CostLayer::query()
            ->where('qty_remaining', '>', 0)
            ->join('product_variants', 'cost_layers.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->selectRaw('products.name as product_name, SUM(cost_layers.qty_remaining * cost_layers.unit_cost) as total_value')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        return [
            'labels' => $results->pluck('product_name')->toArray(),
            'data' => $results->pluck('total_value')->map(fn ($v) => (float) $v)->toArray(),
        ];
    }

    private function getMovementTrendData(array $filters): array
    {
        $results = InventoryTransaction::query()
            ->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']])
            ->join('inventory_transaction_lines', 'inventory_transactions.id', '=', 'inventory_transaction_lines.inventory_transaction_id')
            ->selectRaw("
                TO_CHAR(transaction_date, 'YYYY-MM') as month,
                SUM(CASE WHEN effect = 'in' THEN quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN effect = 'out' THEN quantity ELSE 0 END) as total_out
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $results->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Masuk',
                    'data' => $results->pluck('total_in')->map(fn ($v) => (float) $v)->toArray(),
                ],
                [
                    'label' => 'Keluar',
                    'data' => $results->pluck('total_out')->map(fn ($v) => (float) $v)->toArray(),
                ],
            ],
        ];
    }

    private function getMovementByTypeData(array $filters): array
    {
        $results = InventoryTransaction::query()
            ->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']])
            ->selectRaw('transaction_type, COUNT(*) as count')
            ->groupBy('transaction_type')
            ->get();

        return [
            'labels' => $results->pluck('transaction_type')->map(fn ($t) => self::TRANSACTION_TYPE_LABELS[$t] ?? $t)->toArray(),
            'data' => $results->pluck('count')->map(fn ($v) => (int) $v)->toArray(),
        ];
    }

    private function applyInventoryItemFilters($query, array $filters): void
    {
        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->whereHas('productVariant.product', fn ($sub) => $sub->where('product_category_id', $filters['category_id']));
        }
        if (! empty($filters['branch_id'])) {
            $query->whereHas('location', fn ($sub) => $sub->whereIn('branch_id', (array) $filters['branch_id']));
        }
        if (! empty($filters['company_id'])) {
            $query->whereHas('location.branch.branchGroup', fn ($sub) => $sub->whereIn('company_id', (array) $filters['company_id']));
        }
    }

    private function applyTransactionBranchFilters($query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('locationFrom', fn ($sub) => $sub->whereIn('branch_id', (array) $filters['branch_id']))
                    ->orWhereHas('locationTo', fn ($sub) => $sub->whereIn('branch_id', (array) $filters['branch_id']));
            });
        }
        if (! empty($filters['company_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('locationFrom.branch.branchGroup', fn ($sub) => $sub->whereIn('company_id', (array) $filters['company_id']))
                    ->orWhereHas('locationTo.branch.branchGroup', fn ($sub) => $sub->whereIn('company_id', (array) $filters['company_id']));
            });
        }
    }

    private function applyCostLayerBranchFilters($query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->whereHas('location', fn ($sub) => $sub->whereIn('branch_id', (array) $filters['branch_id']));
        }
        if (! empty($filters['company_id'])) {
            $query->whereHas('location.branch.branchGroup', fn ($sub) => $sub->whereIn('company_id', (array) $filters['company_id']));
        }
    }

    private function getDefaultFilters(Request $request): array
    {
        $filters = $request->all();
        $filters['start_date'] = $filters['start_date'] ?? date('Y-m-01');
        $filters['end_date'] = $filters['end_date'] ?? date('Y-m-d');

        return $filters;
    }

    private function getBranches(array $filters)
    {
        $query = Branch::query();
        if (! empty($filters['company_id'])) {
            $query->whereHas('branchGroup', fn ($q) => $q->whereIn('company_id', (array) $filters['company_id']));
        }

        return $query->orderBy('name', 'asc')->get();
    }

    private function getLocations(array $filters)
    {
        $query = Location::query();
        if (! empty($filters['branch_id'])) {
            $query->whereIn('branch_id', (array) $filters['branch_id']);
        }

        return $query->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function getProducts()
    {
        return ProductVariant::with('product:id,name')
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->get()
            ->map(fn ($v) => [
                'value' => $v->id,
                'label' => $v->product?->name.($v->name ? ' - '.$v->name : ''),
            ]);
    }
}
