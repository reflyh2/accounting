<?php

namespace App\Http\Controllers;

use App\Enums\Documents\SalesReturnStatus;
use App\Exports\SalesReturnsExport;
use App\Exceptions\SalesReturnException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Partner;
use App\Models\SalesDelivery;
use App\Models\SalesReturn;
use App\Services\Sales\SalesReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class SalesReturnController extends Controller
{
    private const QTY_TOLERANCE = 0.0005;

    public function __construct(
        private readonly SalesReturnService $salesReturnService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('sales_returns.index_filters', []);

        Session::put('sales_returns.index_filters', $filters);

        $filterKeys = [
            'search',
            'company_id',
            'branch_id',
            'partner_id',
            'reason_code',
            'status',
            'from_date',
            'to_date',
            'per_page',
            'sort',
            'order',
        ];

        $filters = Arr::only($filters, $filterKeys);

        $query = SalesReturn::query()
            ->with([
                'salesOrder.partner',
                'salesDelivery',
                'partner',
                'branch.branchGroup.company',
            ]);

        if ($search = trim(strtolower($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->whereRaw('lower(return_number) like ?', ["%{$search}%"])
                    ->orWhereHas('salesDelivery', function ($q) use ($search) {
                        $q->whereRaw('lower(delivery_number) like ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('salesOrder', function ($q) use ($search) {
                        $q->whereRaw('lower(order_number) like ?', ["%{$search}%"]);
                    });
            });
        }

        if ($companyIds = Arr::wrap($filters['company_id'] ?? [])) {
            $query->whereIn('company_id', array_filter($companyIds));
        }

        if ($branchIds = Arr::wrap($filters['branch_id'] ?? [])) {
            $query->whereIn('branch_id', array_filter($branchIds));
        }

        if ($partnerIds = Arr::wrap($filters['partner_id'] ?? [])) {
            $query->whereIn('partner_id', array_filter($partnerIds));
        }

        if ($reasonCodes = Arr::wrap($filters['reason_code'] ?? [])) {
            $query->whereIn('reason_code', array_filter($reasonCodes));
        }

        if ($statuses = Arr::wrap($filters['status'] ?? [])) {
            $query->whereIn('status', array_filter($statuses));
        }

        if ($filters['from_date'] ?? null) {
            $query->whereDate('return_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('return_date', '<=', $filters['to_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = max(5, min(100, $perPage));

        $sort = $filters['sort'] ?? 'return_date';
        $order = strtolower($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['return_date', 'return_number', 'total_value', 'total_value_base'];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'return_date';
        }

        $salesReturns = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SalesReturn $salesReturn) => $this->transformSalesReturn($salesReturn));

        return Inertia::render('SalesReturns/Index', [
            'salesReturns' => $salesReturns,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'reasonOptions' => $this->reasonOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $selectedCompanyId = $request->integer('company_id') ?: null;
        $selectedBranchId = $request->integer('branch_id') ?: null;
        $selectedCustomerId = $request->integer('customer_id') ?: null;
        $selectedId = $request->integer('sales_delivery_id') ?: null;

        // Get companies
        $companies = Company::orderBy('name')->get()->map(fn ($c) => [
            'value' => $c->id,
            'label' => $c->name,
        ]);

        // Get branches filtered by company
        $branchQuery = Branch::with('branchGroup.company')->orderBy('name');
        if ($selectedCompanyId) {
            $branchQuery->whereHas('branchGroup', fn ($q) => $q->where('company_id', $selectedCompanyId));
        }
        $branches = $branchQuery->get()->map(fn ($b) => [
            'value' => $b->id,
            'label' => $b->name,
        ]);

        // Get customers filtered by company/branch (customers with returnable deliveries)
        $customers = $this->customerOptionsFiltered($selectedCompanyId, $selectedBranchId);

        return Inertia::render('SalesReturns/Create', [
            'filters' => Session::get('sales_returns.index_filters', []),
            'companies' => $companies,
            'branches' => $branches,
            'customers' => $customers,
            'selectedCompanyId' => $selectedCompanyId,
            'selectedBranchId' => $selectedBranchId,
            'selectedCustomerId' => $selectedCustomerId,
            'salesDeliveries' => fn () => $this->availableSalesDeliveriesFiltered($selectedId, $selectedCompanyId, $selectedBranchId, $selectedCustomerId),
            'selectedSalesDelivery' => fn () => $selectedId ? $this->salesDeliveryDetail($selectedId) : null,
            'reasonOptions' => $this->reasonOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sales_delivery_id' => ['required', 'exists:sales_deliveries,id'],
            'return_date' => ['required', 'date'],
            'reason_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_delivery_line_id' => ['required', 'exists:sales_delivery_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        try {
            $salesReturn = $this->salesReturnService->create($validated);
        } catch (SalesReturnException $exception) {
            return Redirect::back()
                ->withInput()
                ->withErrors(['lines' => $exception->getMessage()]);
        }

        return Redirect::route('sales-returns.show', $salesReturn->id)
            ->with('success', 'Retur penjualan berhasil diposting.');
    }

    public function show(SalesReturn $salesReturn)
    {
        $salesReturn->load([
            'salesOrder.partner',
            'salesDelivery',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'partner',
            'location',
            'currency',
        ]);

        return Inertia::render('SalesReturns/Show', [
            'salesReturn' => $this->transformSalesReturn($salesReturn, includeLines: true),
            'filters' => Session::get('sales_returns.index_filters', []),
            'reasonOptions' => $this->reasonOptions(),
        ]);
    }

    public function exportXLSX(Request $request)
    {
        $salesReturns = $this->getFilteredReturns($request->all());

        return Excel::download(new SalesReturnsExport($salesReturns), 'sales-returns.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $salesReturns = $this->getFilteredReturns($request->all());

        return Excel::download(
            new SalesReturnsExport($salesReturns),
            'sales-returns.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function exportPDF(Request $request)
    {
        $salesReturns = $this->getFilteredReturns($request->all());

        return Excel::download(
            new SalesReturnsExport($salesReturns),
            'sales-returns.pdf',
            \Maatwebsite\Excel\Excel::MPDF
        );
    }

    private function transformSalesReturn(SalesReturn $salesReturn, bool $includeLines = false): array
    {
        $reasonLabels = $this->reasonLabels();

        $data = [
            'id' => $salesReturn->id,
            'return_number' => $salesReturn->return_number,
            'return_date' => optional($salesReturn->return_date)?->toDateString(),
            'status' => $salesReturn->status,
            'reason_code' => $salesReturn->reason_code,
            'reason_label' => $salesReturn->reason_code
                ? ($reasonLabels[$salesReturn->reason_code] ?? $salesReturn->reason_code)
                : null,
            'total_quantity' => (float) $salesReturn->total_quantity,
            'total_value' => (float) $salesReturn->total_value,
            'total_value_base' => (float) $salesReturn->total_value_base,
            'notes' => $salesReturn->notes,
            'posted_at' => optional($salesReturn->posted_at)?->toDateTimeString(),
            'sales_order' => $salesReturn->salesOrder ? [
                'id' => $salesReturn->salesOrder->id,
                'order_number' => $salesReturn->salesOrder->order_number,
            ] : null,
            'sales_delivery' => $salesReturn->salesDelivery ? [
                'id' => $salesReturn->salesDelivery->id,
                'delivery_number' => $salesReturn->salesDelivery->delivery_number,
            ] : null,
            'partner' => $salesReturn->partner ? [
                'id' => $salesReturn->partner->id,
                'name' => $salesReturn->partner->name,
            ] : null,
            'location' => $salesReturn->location ? [
                'id' => $salesReturn->location->id,
                'name' => $salesReturn->location->name,
                'code' => $salesReturn->location->code,
            ] : null,
        ];

        if ($includeLines) {
            $data['lines'] = $salesReturn->lines->map(function ($line) {
                return [
                    'id' => $line->id,
                    'description' => $line->description,
                    'variant' => $line->variant ? [
                        'sku' => $line->variant->sku,
                        'product_name' => $line->variant->product?->name,
                    ] : null,
                    'uom' => [
                        'code' => $line->uom?->code,
                    ],
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'line_total' => (float) $line->line_total,
                    'quantity_base' => (float) $line->quantity_base,
                    'unit_cost_base' => (float) $line->unit_cost_base,
                    'line_total_base' => (float) $line->line_total_base,
                ];
            })->values();
        }

        return $data;
    }

    private function reasonLabels(): array
    {
        return config('sales.return_reasons', []);
    }

    private function reasonOptions(): array
    {
        return collect($this->reasonLabels())
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->toArray();
    }

    private function statusOptions(): array
    {
        return collect(SalesReturnStatus::cases())
            ->mapWithKeys(fn (SalesReturnStatus $status) => [$status->value => $status->label()])
            ->toArray();
    }

    private function companyOptions()
    {
        return Company::orderBy('name')->get(['id', 'name']);
    }

    private function branchOptions()
    {
        return Branch::with('branchGroup.company')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'company' => $branch->branchGroup?->company?->name,
            ])
            ->values();
    }

    private function customerOptions()
    {
        return Partner::query()
            ->with('roles')
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();
    }

    /**
     * API endpoint for customers with returnable deliveries.
     * Used by AppPopoverSearch component.
     */
    public function apiCustomersWithDeliveries(Request $request)
    {
        $query = Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->whereHas('salesDeliveries', function ($q) {
                $q->where('status', 'posted')
                    ->whereHas('lines', function ($lineQ) {
                        $lineQ->whereRaw('(quantity - quantity_invoiced - quantity_returned) > ?', [self::QTY_TOLERANCE]);
                    });
            });

        if ($request->search) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(name) like ?', ["%{$search}%"])
                  ->orWhereRaw('lower(code) like ?', ["%{$search}%"]);
            });
        }

        $sort = $request->input('sort', 'name');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);

        return $query->paginate($request->input('per_page', 10))->withQueryString();
    }

    private function customerOptionsFiltered(?int $companyId = null, ?int $branchId = null): array
    {
        $query = Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->whereHas('salesDeliveries', function ($q) use ($companyId, $branchId) {
                $q->where('status', 'posted')
                    ->whereHas('lines', function ($lineQ) {
                        $lineQ->whereRaw('(quantity - quantity_invoiced - quantity_returned) > ?', [self::QTY_TOLERANCE]);
                    });

                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->orderBy('name');

        return $query->get()
            ->map(fn ($partner) => [
                'value' => $partner->id,
                'label' => $partner->name,
            ])
            ->values()
            ->toArray();
    }

    private function availableSalesDeliveriesFiltered(?int $selectedId = null, ?int $companyId = null, ?int $branchId = null, ?int $customerId = null)
    {
        $query = SalesDelivery::query()
            ->with(['salesOrders.partner', 'partner', 'branch.branchGroup.company', 'lines'])
            ->where('status', 'posted')
            ->whereHas('lines', function ($builder) {
                $builder->whereRaw('(quantity - quantity_invoiced - quantity_returned) > ?', [self::QTY_TOLERANCE]);
            })
            ->orderByDesc('delivery_date')
            ->limit(50);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($customerId) {
            $query->where('partner_id', $customerId);
        }

        $deliveries = $query->get();

        // Include selected if not in list
        if ($selectedId && !$deliveries->firstWhere('id', $selectedId)) {
            $selected = SalesDelivery::with(['salesOrders.partner', 'partner', 'branch.branchGroup.company', 'lines'])
                ->find($selectedId);

            if ($selected) {
                $deliveries->push($selected);
            }
        }

        return $deliveries->map(function (SalesDelivery $delivery) {
            $availableQty = $delivery->lines->sum(function ($line) {
                return max(0, (float) $line->quantity - (float) $line->quantity_invoiced - (float) $line->quantity_returned);
            });

            return [
                'id' => $delivery->id,
                'delivery_number' => $delivery->delivery_number,
                'branch' => $delivery->branch?->name,
                'company' => $delivery->branch?->branchGroup?->company?->name,
                'available_quantity' => $availableQty,
            ];
        })->values();
    }

    private function availableSalesDeliveries(?int $selectedId = null)
    {
        $query = SalesDelivery::query()
            ->with(['salesOrders.partner', 'partner', 'branch', 'lines'])
            ->where('status', 'posted')
            ->whereHas('lines', function ($builder) {
                $builder->whereRaw('(quantity - quantity_invoiced - quantity_returned) > ?', [self::QTY_TOLERANCE]);
            })
            ->orderByDesc('delivery_date')
            ->limit(25);

        $salesDeliveries = $query->get();

        if ($selectedId && !$salesDeliveries->firstWhere('id', $selectedId)) {
            $selected = SalesDelivery::with(['salesOrders.partner', 'partner', 'branch', 'lines'])
                ->find($selectedId);

            if ($selected) {
                $salesDeliveries->push($selected);
            }
        }

        return $salesDeliveries->map(function (SalesDelivery $delivery) {
            $availableQty = $delivery->lines->sum(function ($line) {
                return max(0, (float) $line->quantity - (float) $line->quantity_invoiced - (float) $line->quantity_returned);
            });

            return [
                'id' => $delivery->id,
                'delivery_number' => $delivery->delivery_number,
                'partner' => $delivery->partner?->name,
                'branch' => $delivery->branch?->name,
                'available_quantity' => $availableQty,
            ];
        })->values();
    }

    private function salesDeliveryDetail(int $salesDeliveryId): ?array
    {
        $salesDelivery = SalesDelivery::with([
            'salesOrders.partner',
            'partner',
            'branch.branchGroup.company',
            'currency',
            'location',
            'lines.variant.product',
            'lines.uom',
            'lines.salesOrderLine',
        ])->find($salesDeliveryId);

        if (!$salesDelivery) {
            return null;
        }

        $lines = $salesDelivery->lines->map(function ($line) {
            $available = max(
                0,
                (float) $line->quantity - (float) $line->quantity_invoiced - (float) $line->quantity_returned
            );

            return [
                'id' => $line->id,
                'description' => $line->description,
                'variant' => $line->variant ? [
                    'sku' => $line->variant->sku,
                    'product_name' => $line->variant->product?->name,
                ] : null,
                'uom' => [
                    'id' => $line->uom?->id,
                    'code' => $line->uom?->code,
                ],
                'available_quantity' => $available,
                'ordered_quantity' => (float) $line->salesOrderLine?->quantity,
                'unit_price' => (float) $line->unit_price,
                'unit_cost_base' => (float) $line->unit_cost_base,
            ];
        })->filter(fn ($line) => $line['available_quantity'] > self::QTY_TOLERANCE)
            ->values();

        if (!$lines->count()) {
            return null;
        }

        // Get first sales order for display or null
        $firstSalesOrder = $salesDelivery->salesOrders->first();

        return [
            'id' => $salesDelivery->id,
            'delivery_number' => $salesDelivery->delivery_number,
            'delivery_date' => optional($salesDelivery->delivery_date)?->toDateString(),
            'sales_order' => $firstSalesOrder ? [
                'id' => $firstSalesOrder->id,
                'order_number' => $firstSalesOrder->order_number,
                'partner' => $firstSalesOrder->partner?->name,
            ] : null,
            'branch' => $salesDelivery->branch ? [
                'id' => $salesDelivery->branch->id,
                'name' => $salesDelivery->branch->name,
                'company' => $salesDelivery->branch->branchGroup?->company?->name,
            ] : null,
            'location' => $salesDelivery->location ? [
                'id' => $salesDelivery->location->id,
                'name' => $salesDelivery->location->name,
            ] : null,
            'currency' => $salesDelivery->currency ? [
                'code' => $salesDelivery->currency->code,
            ] : null,
            'lines' => $lines,
        ];
    }

    private function getFilteredReturns(array $filters)
    {
        $query = SalesReturn::with([
            'salesOrder',
            'salesDelivery',
            'partner',
        ]);

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', (array) $filters['branch_id']);
        }

        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', (array) $filters['partner_id']);
        }

        if (!empty($filters['reason_code'])) {
            $query->whereIn('reason_code', (array) $filters['reason_code']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('return_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('return_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->whereRaw('lower(return_number) like ?', ["%{$search}%"])
                    ->orWhereHas('salesOrder', function ($q) use ($search) {
                        $q->whereRaw('lower(order_number) like ?', ["%{$search}%"]);
                    });
            });
        }

        return $query->orderByDesc('return_date')->get();
    }
}
