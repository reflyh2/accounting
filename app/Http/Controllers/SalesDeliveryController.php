<?php

namespace App\Http\Controllers;

use App\Enums\Documents\SalesDeliveryStatus;
use App\Enums\Documents\SalesOrderStatus;
use App\Exceptions\InventoryException;
use App\Exceptions\SalesOrderException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Location;
use App\Models\Partner;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Models\DocumentTemplate;
use App\Services\Sales\SalesService;
use App\Services\DocumentTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class SalesDeliveryController extends Controller
{
    public function __construct(private readonly SalesService $salesService)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('sales_deliveries.index_filters', []);
        Session::put('sales_deliveries.index_filters', $filters);

        $filterKeys = [
            'search',
            'company_id',
            'branch_id',
            'partner_id',
            'status',
            'from_date',
            'to_date',
            'per_page',
            'sort',
            'order',
        ];

        $filters = Arr::only($filters, $filterKeys);

        $query = SalesDelivery::query()
            ->with([
                'salesOrders',
                'partner',
                'branch.branchGroup.company',
                'location',
                'currency',
            ]);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->whereRaw('lower(delivery_number) like ?', ["%{$search}%"])
                    ->orWhereHas('salesOrders', function ($soQuery) use ($search) {
                        $soQuery->whereRaw('lower(order_number) like ?', ["%{$search}%"]);
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

        if ($statuses = Arr::wrap($filters['status'] ?? [])) {
            $query->whereIn('status', array_filter($statuses));
        }

        if ($filters['from_date'] ?? null) {
            $query->whereDate('delivery_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('delivery_date', '<=', $filters['to_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = max(5, min(100, $perPage));

        $sort = $filters['sort'] ?? 'delivery_date';
        $order = $filters['order'] ?? 'desc';
        $allowedSorts = ['delivery_date', 'delivery_number', 'status', 'total_amount'];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'delivery_date';
        }

        if (!in_array(strtolower($order), ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        $deliveries = $query->orderBy($sort, $order)
            ->orderBy('id', $order === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SalesDelivery $delivery) => $this->transformDeliveryListItem($delivery));

        return Inertia::render('SalesDeliveries/Index', [
            'deliveries' => $deliveries,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $selectedCompanyId = $request->integer('company_id') ?: null;
        $selectedBranchId = $request->integer('branch_id') ?: null;
        $selectedPartnerId = $request->integer('partner_id') ?: null;
        $selectedIds = $request->input('sales_order_ids', []);
        if (!is_array($selectedIds)) {
            $selectedIds = $selectedIds ? [$selectedIds] : [];
        }
        $selectedIds = array_filter(array_map('intval', $selectedIds));

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

        $selectedSalesOrders = [];
        $locations = [];

        // Get locations for selected branch
        if ($selectedBranchId) {
            $locations = $this->locationOptions($selectedBranchId);
        }

        if (!empty($selectedIds)) {
            $selectedSalesOrders = $this->salesOrdersDetail($selectedIds);
            // Update locations from SO branch if not already set
            if (empty($locations) && !empty($selectedSalesOrders)) {
                $branchId = $selectedSalesOrders[0]['branch']['id'] ?? null;
                if ($branchId) {
                    $locations = $this->locationOptions($branchId);
                }
            }
        }

        return Inertia::render('SalesDeliveries/Create', [
            'companies' => $companies,
            'branches' => $branches,
            'salesOrders' => $this->availableSalesOrders($selectedIds, $selectedPartnerId, $selectedCompanyId, $selectedBranchId),
            'selectedSalesOrders' => $selectedSalesOrders,
            'selectedCompanyId' => $selectedCompanyId,
            'selectedBranchId' => $selectedBranchId,
            'selectedPartnerId' => $selectedPartnerId,
            'customers' => $this->customerOptionsFiltered($selectedCompanyId),
            'locations' => $locations,
            'filters' => Session::get('sales_deliveries.index_filters', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'partner_id' => ['required', 'exists:partners,id'],
            'shipping_address_id' => ['nullable', 'exists:partner_addresses,id'],
            'sales_order_ids' => ['required', 'array', 'min:1'],
            'sales_order_ids.*' => ['required', 'exists:sales_orders,id'],
            'delivery_date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'exists:sales_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $delivery = $this->salesService->postDeliveryFromMultipleSOs($data, $request->user());
        } catch (SalesOrderException|InventoryException $exception) {
            return Redirect::back()
                ->withInput()
                ->withErrors(['lines' => $exception->getMessage()]);
        }

        return Redirect::route('sales-deliveries.show', $delivery->id)
            ->with('success', 'Pengiriman berhasil diposting.');
    }

    public function show(SalesDelivery $salesDelivery): Response
    {
        $salesDelivery->load([
            'salesOrders.partner',
            'salesOrders.branch.branchGroup.company',
            'partner',
            'currency',
            'location',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'lines.salesOrderLine',
        ]);

        $canModify = $this->salesService->canModifyDelivery($salesDelivery);

        return Inertia::render('SalesDeliveries/Show', [
            'delivery' => $this->transformDelivery($salesDelivery),
            'canModify' => $canModify,
            'filters' => Session::get('sales_deliveries.index_filters', []),
        ]);
    }

    public function edit(SalesDelivery $salesDelivery): Response|RedirectResponse
    {
        $salesDelivery->load([
            'salesOrders.lines.variant.product',
            'salesOrders.lines.uom',
            'salesOrders.partner',
            'salesOrders.branch.branchGroup.company',
            'lines.variant.product',
            'lines.uom',
            'lines.salesOrderLine',
            'location',
            'partner',
        ]);

        if (!$this->salesService->canModifyDelivery($salesDelivery)) {
            return Redirect::route('sales-deliveries.show', $salesDelivery->id)
                ->with('error', 'Pengiriman tidak dapat diubah karena sudah digunakan pada invoice.');
        }

        $locations = $this->locationOptions($salesDelivery->branch_id);

        // Get available SO lines for the delivery
        $selectedSalesOrders = $this->getSalesDeliverySalesOrders($salesDelivery);

        return Inertia::render('SalesDeliveries/Edit', [
            'delivery' => $this->transformDeliveryForEdit($salesDelivery),
            'selectedSalesOrders' => $selectedSalesOrders,
            'selectedPartnerId' => $salesDelivery->partner_id,
            'locations' => $locations,
            'filters' => Session::get('sales_deliveries.index_filters', []),
        ]);
    }

    public function update(Request $request, SalesDelivery $salesDelivery): RedirectResponse
    {
        $data = $request->validate([
            'delivery_date' => ['required', 'date'],
            'shipping_address_id' => ['nullable', 'exists:partner_addresses,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'exists:sales_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $salesDelivery = $this->salesService->updateSalesDelivery($salesDelivery, $data, $request->user());
        } catch (SalesOrderException|InventoryException $exception) {
            return Redirect::back()
                ->withInput()
                ->withErrors(['lines' => $exception->getMessage()]);
        }

        return Redirect::route('sales-deliveries.show', $salesDelivery->id)
            ->with('success', 'Pengiriman berhasil diperbarui.');
    }

    public function destroy(SalesDelivery $salesDelivery): RedirectResponse
    {
        try {
            $this->salesService->deleteSalesDelivery($salesDelivery);
        } catch (SalesOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::route('sales-deliveries.index')
            ->with('success', 'Pengiriman berhasil dihapus.');
    }

    /**
     * Display the print view for Sales Delivery.
     */
    public function print(SalesDelivery $salesDelivery, DocumentTemplateService $templateService): Response
    {
        $salesDelivery->load([
            'salesOrders',
            'partner',
            'currency',
            'location',
            'lines.variant.product',
            'lines.uom',
            'lines.salesOrderLine.salesOrder',
            'branch.branchGroup.company',
            'creator:global_id,name',
        ]);

        // Resolve template for this company or fallback to default
        $companyId = $salesDelivery->company_id ?? $salesDelivery->branch?->branchGroup?->company_id;
        $template = DocumentTemplate::resolveTemplate($companyId, 'sales_delivery');

        $renderedContent = null;
        if ($template) {
            $renderedContent = $templateService->renderTemplate($template, $salesDelivery);
        }

        return Inertia::render('SalesDeliveries/Print', [
            'salesDelivery' => $salesDelivery,
            'template' => $template,
            'renderedContent' => $renderedContent,
        ]);
    }

    private function transformDeliveryListItem(SalesDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'delivery_number' => $delivery->delivery_number,
            'delivery_date' => $delivery->delivery_date?->toDateString(),
            'status' => $delivery->status,
            'customer_name' => $delivery->partner?->name,
            'branch_name' => $delivery->branch?->name,
            'sales_orders' => $delivery->salesOrders->map(fn ($so) => [
                'id' => $so->id,
                'order_number' => $so->order_number,
            ])->values(),
            'total_quantity' => (float) $delivery->total_quantity,
            'total_amount' => (float) $delivery->total_amount,
            'total_cogs' => (float) $delivery->total_cogs,
        ];
    }

    private function transformDelivery(SalesDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'delivery_number' => $delivery->delivery_number,
            'delivery_date' => $delivery->delivery_date?->toDateString(),
            'status' => $delivery->status,
            'notes' => $delivery->notes,
            'total_quantity' => (float) $delivery->total_quantity,
            'total_amount' => (float) $delivery->total_amount,
            'total_cogs' => (float) $delivery->total_cogs,
            'currency' => [
                'code' => $delivery->currency?->code,
            ],
            'location' => [
                'name' => $delivery->location?->name,
                'code' => $delivery->location?->code,
            ],
            'partner' => $delivery->partner ? [
                'id' => $delivery->partner->id,
                'name' => $delivery->partner->name,
            ] : null,
            'sales_orders' => $delivery->salesOrders->map(fn ($so) => [
                'id' => $so->id,
                'order_number' => $so->order_number,
                'status' => $so->status,
                'partner' => $so->partner ? [
                    'name' => $so->partner->name,
                ] : null,
            ])->values(),
            'lines' => $delivery->lines->map(function ($line) {
                return [
                    'id' => $line->id,
                    'description' => $line->description,
                    'sku' => $line->variant?->sku,
                    'uom' => $line->uom?->code,
                    'base_uom' => $line->baseUom?->code,
                    'quantity' => (float) $line->quantity,
                    'quantity_base' => (float) $line->quantity_base,
                    'unit_price' => (float) $line->unit_price,
                    'unit_cost_base' => $line->unit_cost_base !== null ? (float) $line->unit_cost_base : null,
                    'line_total' => (float) $line->line_total,
                    'cogs_total' => (float) $line->cogs_total,
                    'sales_order_line' => $line->salesOrderLine ? [
                        'id' => $line->salesOrderLine->id,
                        'sales_order' => $line->salesOrderLine->salesOrder ? [
                            'order_number' => $line->salesOrderLine->salesOrder->order_number,
                        ] : null,
                    ] : null,
                ];
            })->values(),
        ];
    }

    private function availableSalesOrders(array $selectedIds = [], ?int $partnerId = null, ?int $companyId = null, ?int $branchId = null)
    {
        $query = SalesOrder::query()
            ->with(['partner', 'branch.branchGroup.company', 'lines'])
            ->whereIn('status', [
                SalesOrderStatus::CONFIRMED->value,
                SalesOrderStatus::PARTIALLY_DELIVERED->value,
            ])
            ->orderByDesc('order_date')
            ->limit(50);

        if ($partnerId) {
            $query->where('partner_id', $partnerId);
        }

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $salesOrders = $query->get();

        // Include any selected SOs not in query result
        if (!empty($selectedIds)) {
            $existingIds = $salesOrders->pluck('id')->toArray();
            $missingIds = array_diff($selectedIds, $existingIds);

            if (!empty($missingIds)) {
                $additionalSOs = SalesOrder::with(['partner', 'branch.branchGroup.company', 'lines'])
                    ->whereIn('id', $missingIds)
                    ->get();

                foreach ($additionalSOs as $so) {
                    $salesOrders->push($so);
                }
            }
        }

        return $salesOrders->map(function (SalesOrder $salesOrder) {
            $remainingQty = $salesOrder->lines->sum(function ($line) {
                return max(0, (float) $line->quantity - (float) $line->quantity_delivered);
            });

            return [
                'id' => $salesOrder->id,
                'order_number' => $salesOrder->order_number,
                'status' => $salesOrder->status,
                'order_date' => optional($salesOrder->order_date)?->toDateString(),
                'partner' => $salesOrder->partner ? [
                    'id' => $salesOrder->partner->id,
                    'name' => $salesOrder->partner->name,
                ] : null,
                'branch' => $salesOrder->branch ? [
                    'id' => $salesOrder->branch->id,
                    'name' => $salesOrder->branch->name,
                ] : null,
                'remaining_quantity' => $remainingQty,
            ];
        });
    }

    private function salesOrdersDetail(array $salesOrderIds): array
    {
        $salesOrders = SalesOrder::with([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'lines.reservationLocation',
        ])->whereIn('id', $salesOrderIds)->get();

        $result = [];

        foreach ($salesOrders as $salesOrder) {
            $lines = $salesOrder->lines->map(function ($line) {
                $remaining = max(0, (float) $line->quantity - (float) $line->quantity_delivered);
                return [
                    'id' => $line->id,
                    'line_number' => $line->line_number,
                    'description' => $line->description,
                    'variant' => $line->variant ? [
                        'id' => $line->variant->id,
                        'sku' => $line->variant->sku,
                        'product_name' => $line->variant->product?->name,
                    ] : null,
                    'uom' => [
                        'id' => $line->uom?->id,
                        'code' => $line->uom?->code,
                    ],
                    'ordered_quantity' => (float) $line->quantity,
                    'delivered_quantity' => (float) $line->quantity_delivered,
                    'remaining_quantity' => $remaining,
                    'unit_price' => (float) $line->unit_price,
                    'reservation_location' => $line->reservationLocation ? [
                        'id' => $line->reservationLocation->id,
                        'name' => $line->reservationLocation->name,
                        'code' => $line->reservationLocation->code,
                    ] : null,
                ];
            })->values();

            $result[] = [
                'id' => $salesOrder->id,
                'order_number' => $salesOrder->order_number,
                'status' => $salesOrder->status,
                'partner' => $salesOrder->partner ? [
                    'id' => $salesOrder->partner->id,
                    'name' => $salesOrder->partner->name,
                ] : null,
                'branch' => $salesOrder->branch ? [
                    'id' => $salesOrder->branch->id,
                    'name' => $salesOrder->branch->name,
                    'company' => $salesOrder->branch->branchGroup?->company ? [
                        'id' => $salesOrder->branch->branchGroup->company->id,
                        'name' => $salesOrder->branch->branchGroup->company->name,
                    ] : null,
                ] : null,
                'currency' => $salesOrder->currency ? [
                    'id' => $salesOrder->currency->id,
                    'code' => $salesOrder->currency->code,
                ] : null,
                'shipping_address_id' => $salesOrder->shipping_address_id,
                'invoice_address_id' => $salesOrder->invoice_address_id,
                'lines' => $lines,
            ];
        }

        return $result;
    }

    private function locationOptions(int $branchId)
    {
        return Location::where('branch_id', $branchId)
            ->orderBy('code')
            ->get()
            ->map(fn ($location) => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ])
            ->values();
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
                'company_id' => $branch->branchGroup?->company_id,
            ])
            ->values();
    }

    private function customerOptions()
    {
        return Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function customerOptionsFiltered(?int $companyId): array
    {
        $query = Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->whereHas('salesOrders', function ($q) use ($companyId) {
                $q->whereIn('status', [
                    SalesOrderStatus::CONFIRMED->value,
                    SalesOrderStatus::PARTIALLY_DELIVERED->value,
                ]);
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            })
            ->orderBy('name');

        return $query->get()
            ->map(fn ($partner) => [
                'value' => $partner->id,
                'label' => $partner->name,
                'code' => $partner->code,
            ])
            ->values()
            ->toArray();
    }

    private function statusOptions(): array
    {
        return collect(SalesDeliveryStatus::cases())
            ->mapWithKeys(fn (SalesDeliveryStatus $status) => [
                $status->value => $status->label(),
            ])
            ->toArray();
    }

    /**
     * API endpoint for customers with available Sales Orders.
     * Used by AppPopoverSearch component.
     */
    public function apiCustomersWithSOs(Request $request)
    {
        $query = Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->whereHas('salesOrders', function ($q) {
                $q->whereIn('status', [
                    SalesOrderStatus::CONFIRMED->value,
                    SalesOrderStatus::PARTIALLY_DELIVERED->value,
                    SalesOrderStatus::DELIVERED->value,
                ]);
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

    private function getSalesDeliverySalesOrders(SalesDelivery $salesDelivery): array
    {
        // Get SOs from pivot
        $salesOrders = $salesDelivery->salesOrders;

        $result = [];

        foreach ($salesOrders as $salesOrder) {
            // Calculate remaining quantity considering current delivery lines
            $sdLinesBySOLineId = $salesDelivery->lines->keyBy('sales_order_line_id');

            $lines = $salesOrder->lines->map(function ($line) use ($sdLinesBySOLineId) {
                $sdLine = $sdLinesBySOLineId->get($line->id);
                $currentSdQty = $sdLine ? (float) $sdLine->quantity : 0;

                // Remaining = ordered - delivered (from other deliveries) + current SD qty (to allow editing)
                $deliveredFromOthers = max(0, (float) $line->quantity_delivered - $currentSdQty);
                $remaining = max(0, (float) $line->quantity - $deliveredFromOthers);

                return [
                    'id' => $line->id,
                    'line_number' => $line->line_number,
                    'description' => $line->description,
                    'variant' => $line->variant ? [
                        'id' => $line->variant->id,
                        'sku' => $line->variant->sku,
                        'product_name' => $line->variant->product?->name,
                    ] : null,
                    'uom' => [
                        'id' => $line->uom?->id,
                        'code' => $line->uom?->code,
                    ],
                    'ordered_quantity' => (float) $line->quantity,
                    'delivered_quantity' => $deliveredFromOthers,
                    'remaining_quantity' => $remaining,
                    'current_sd_quantity' => $currentSdQty,
                    'unit_price' => (float) $line->unit_price,
                ];
            })->values();

            $result[] = [
                'id' => $salesOrder->id,
                'order_number' => $salesOrder->order_number,
                'status' => $salesOrder->status,
                'partner' => $salesOrder->partner ? [
                    'id' => $salesOrder->partner->id,
                    'name' => $salesOrder->partner->name,
                ] : null,
                'branch' => $salesOrder->branch ? [
                    'id' => $salesOrder->branch->id,
                    'name' => $salesOrder->branch->name,
                    'company' => $salesOrder->branch->branchGroup?->company ? [
                        'id' => $salesOrder->branch->branchGroup->company->id,
                        'name' => $salesOrder->branch->branchGroup->company->name,
                    ] : null,
                ] : null,
                'lines' => $lines,
            ];
        }

        return $result;
    }

    private function transformDeliveryForEdit(SalesDelivery $delivery): array
    {
        $lines = $delivery->lines->map(function ($line) {
            return [
                'id' => $line->id,
                'sales_order_line_id' => $line->sales_order_line_id,
                'quantity' => (float) $line->quantity,
                'description' => $line->description,
                'variant' => $line->variant ? [
                    'id' => $line->variant->id,
                    'sku' => $line->variant->sku,
                    'product_name' => $line->variant->product?->name,
                ] : null,
                'uom' => [
                    'id' => $line->uom?->id,
                    'code' => $line->uom?->code,
                ],
            ];
        })->values();

        return [
            'id' => $delivery->id,
            'delivery_number' => $delivery->delivery_number,
            'delivery_date' => optional($delivery->delivery_date)?->toDateString(),
            'location_id' => $delivery->location_id,
            'notes' => $delivery->notes,
            'partner' => $delivery->partner ? [
                'id' => $delivery->partner->id,
                'name' => $delivery->partner->name,
            ] : null,
            'location' => $delivery->location ? [
                'id' => $delivery->location->id,
                'code' => $delivery->location->code,
                'name' => $delivery->location->name,
            ] : null,
            'lines' => $lines,
        ];
    }
}

