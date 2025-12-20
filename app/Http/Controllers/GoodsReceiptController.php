<?php

namespace App\Http\Controllers;

use App\Enums\Documents\GoodsReceiptStatus;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderException;
use App\Exports\GoodsReceiptsExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Lot;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\Serial;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('goods_receipts.index_filters', []);
        Session::put('goods_receipts.index_filters', $filters);

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

        $query = GoodsReceipt::query()
            ->with([
                'purchaseOrders',
                'supplier',
                'branch.branchGroup.company',
                'location',
                'currency',
            ]);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->whereRaw('lower(receipt_number) like ?', ["%{$search}%"])
                    ->orWhereHas('purchaseOrder', function ($poQuery) use ($search) {
                        $poQuery->whereRaw('lower(order_number) like ?', ["%{$search}%"]);
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
            $query->whereHas('purchaseOrder', function ($poQuery) use ($partnerIds) {
                $poQuery->whereIn('partner_id', array_filter($partnerIds));
            });
        }

        if ($statuses = Arr::wrap($filters['status'] ?? [])) {
            $query->whereIn('status', array_filter($statuses));
        }

        if ($filters['from_date'] ?? null) {
            $query->whereDate('receipt_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('receipt_date', '<=', $filters['to_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = max(5, min(100, $perPage));

        $sort = $filters['sort'] ?? 'receipt_date';
        $order = $filters['order'] ?? 'desc';
        $allowedSorts = ['receipt_date', 'receipt_number', 'status', 'total_value', 'total_value_base'];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'receipt_date';
        }

        if (!in_array(strtolower($order), ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        $goodsReceipts = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (GoodsReceipt $receipt) => $this->transformGoodsReceipt($receipt));

        return Inertia::render('GoodsReceipts/Index', [
            'goodsReceipts' => $goodsReceipts,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'suppliers' => $this->supplierOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $selectedCompanyId = $request->integer('company_id') ?: null;
        $selectedBranchId = $request->integer('branch_id') ?: null;
        $selectedPartnerId = $request->integer('partner_id') ?: null;
        $selectedIds = $request->input('purchase_order_ids', []);
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

        $selectedPurchaseOrders = [];
        $locations = [];

        // Get locations for selected branch
        if ($selectedBranchId) {
            $locations = $this->locationOptions($selectedBranchId);
        }

        if (!empty($selectedIds)) {
            $selectedPurchaseOrders = $this->purchaseOrdersDetail($selectedIds);
            // Update locations from PO branch if not already set
            if (empty($locations) && !empty($selectedPurchaseOrders)) {
                $branchId = $selectedPurchaseOrders[0]['branch']['id'] ?? null;
                if ($branchId) {
                    $locations = $this->locationOptions($branchId);
                }
            }
        }

        return Inertia::render('GoodsReceipts/Create', [
            'companies' => $companies,
            'branches' => $branches,
            'purchaseOrders' => $this->availablePurchaseOrders($selectedIds, $selectedPartnerId, $selectedCompanyId, $selectedBranchId),
            'selectedPurchaseOrders' => $selectedPurchaseOrders,
            'selectedCompanyId' => $selectedCompanyId,
            'selectedBranchId' => $selectedBranchId,
            'selectedPartnerId' => $selectedPartnerId,
            'suppliers' => $this->supplierOptionsFiltered($selectedCompanyId),
            'locations' => $locations,
            'filters' => Session::get('goods_receipts.index_filters', []),
        ]);
    }

    public function store(Request $request, PurchaseService $purchaseService): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:partners,id'],
            'purchase_order_ids' => ['required', 'array', 'min:1'],
            'purchase_order_ids.*' => ['required', 'exists:purchase_orders,id'],
            'receipt_date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'exists:purchase_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $goodsReceipt = $purchaseService->createGoodsReceipt(
                $data['purchase_order_ids'],
                $data
            );
        } catch (PurchaseOrderException $exception) {
            return Redirect::back()
                ->withInput()
                ->withErrors(['lines' => $exception->getMessage()]);
        }

        return Redirect::route('goods-receipts.show', $goodsReceipt->id)
            ->with('success', 'Penerimaan Barang berhasil disimpan.');
    }

    public function show(GoodsReceipt $goodsReceipt): Response
    {
        $goodsReceipt->loadMissing([
            'purchaseOrder',
            'purchaseOrder.partner',
            'purchaseOrder.branch.branchGroup.company',
            'purchaseOrder.currency',
            'purchaseOrder:*',
            'purchaseOrders.partner',
            'purchaseOrders.branch.branchGroup.company',
            'lines:*',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'lines.lot',
            'lines.serial',
            'lines.purchaseOrderLine',
            'location',
            'currency',
            'inventoryTransaction',
        ]);

        return Inertia::render('GoodsReceipts/Show', [
            'goodsReceipt' => $this->transformGoodsReceipt($goodsReceipt, includeLines: true),
            'filters' => Session::get('goods_receipts.index_filters', []),
        ]);
    }

    public function edit(GoodsReceipt $goodsReceipt): Response
    {
        $goodsReceipt->load([
            'purchaseOrders.lines.variant.product',
            'purchaseOrders.lines.uom',
            'purchaseOrders.partner',
            'purchaseOrders.branch.branchGroup.company',
            'purchaseOrder.lines.variant.product',
            'purchaseOrder.lines.uom',
            'purchaseOrder.partner',
            'purchaseOrder.branch.branchGroup.company',
            'lines.variant.product',
            'lines.uom',
            'lines.purchaseOrderLine',
            'location',
        ]);

        $locations = $this->locationOptions($goodsReceipt->branch_id);

        $selectedIds = $goodsReceipt->purchaseOrders()->pluck('purchase_orders.id')->toArray();

        // Get available PO lines for the goods receipt
        $selectedPurchaseOrders = $this->getGoodsReceiptPurchaseOrders($goodsReceipt);

        return Inertia::render('GoodsReceipts/Edit', [
            'goodsReceipt' => $this->transformGoodsReceiptForEdit($goodsReceipt),
            'selectedPurchaseOrders' => $selectedPurchaseOrders,
            'selectedPartnerId' => $goodsReceipt->supplier_id,
            'purchaseOrders' => $this->availablePurchaseOrders($selectedIds, $goodsReceipt->supplier_id),
            'suppliers' => $this->supplierOptions(),
            'locations' => $locations,
            'filters' => Session::get('goods_receipts.index_filters', []),
        ]);
    }

    public function update(Request $request, GoodsReceipt $goodsReceipt, PurchaseService $purchaseService): RedirectResponse
    {
        $data = $request->validate([
            'receipt_date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'exists:purchase_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.lot_id' => ['nullable', 'exists:lots,id'],
            'lines.*.serial_id' => ['nullable', 'exists:serials,id'],
        ]);

        try {
            $goodsReceipt = $purchaseService->updateGoodsReceipt($goodsReceipt, $data, $request->user());
        } catch (PurchaseOrderException $exception) {
            return Redirect::back()
                ->withInput()
                ->withErrors(['lines' => $exception->getMessage()]);
        }

        return Redirect::route('goods-receipts.show', $goodsReceipt->id)
            ->with('success', 'Penerimaan Barang berhasil diperbarui.');
    }

    private function transformGoodsReceipt(GoodsReceipt $receipt, bool $includeLines = false): array
    {
        $data = [
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_date' => optional($receipt->receipt_date)?->toDateString(),
            'status' => $receipt->status,
            'total_quantity' => (float) $receipt->total_quantity,
            'total_value' => (float) $receipt->total_value,
            'total_value_base' => (float) $receipt->total_value_base,
            'notes' => $receipt->notes,
            'valuation_method' => $receipt->valuation_method,
            'returnable_quantity' => $receipt->lines->sum(function ($line) {
                return max(
                    0,
                    (float) $line->quantity - (float) $line->quantity_invoiced - (float) $line->quantity_returned
                );
            }),
            'inventory_transaction' => $receipt->inventoryTransaction ? [
                'id' => $receipt->inventoryTransaction->id,
                'transaction_number' => $receipt->inventoryTransaction->transaction_number,
            ] : null,
            'currency' => $receipt->currency ? [
                'id' => $receipt->currency->id,
                'code' => $receipt->currency->code,
            ] : null,
            'purchase_order' => $receipt->purchaseOrder ? [
                'id' => $receipt->purchaseOrder->id,
                'order_number' => $receipt->purchaseOrder->order_number,
                'status' => $receipt->purchaseOrder->status,
                'partner' => $receipt->purchaseOrder->partner ? [
                    'id' => $receipt->purchaseOrder->partner->id,
                    'name' => $receipt->purchaseOrder->partner->name,
                ] : null,
                'branch' => $receipt->purchaseOrder->branch ? [
                    'id' => $receipt->purchaseOrder->branch->id,
                    'name' => $receipt->purchaseOrder->branch->name,
                    'company' => $receipt->purchaseOrder->branch->branchGroup?->company ? [
                        'id' => $receipt->purchaseOrder->branch->branchGroup->company->id,
                        'name' => $receipt->purchaseOrder->branch->branchGroup->company->name,
                    ] : null,
                ] : null,
                'currency' => $receipt->purchaseOrder->currency ? [
                    'id' => $receipt->purchaseOrder->currency->id,
                    'code' => $receipt->purchaseOrder->currency->code,
                ] : null,
            ] : null,
            'purchase_orders' => $receipt->purchaseOrders->map(function ($po) {
                return [
                    'id' => $po->id,
                    'order_number' => $po->order_number,
                    'status' => $po->status,
                    'partner' => $po->partner ? [
                        'id' => $po->partner->id,
                        'name' => $po->partner->name,
                    ] : null,
                ];
            })->values(),
            'supplier' => $receipt->supplier ? [
                'id' => $receipt->supplier->id,
                'name' => $receipt->supplier->name,
            ] : null,
            'location' => $receipt->location ? [
                'id' => $receipt->location->id,
                'code' => $receipt->location->code,
                'name' => $receipt->location->name,
            ] : null,
            'posted_at' => optional($receipt->posted_at)?->toDateTimeString(),
        ];

        if ($includeLines) {
            $data['lines'] = $receipt->lines->map(function ($line) {
                $availableForReturn = max(
                    0,
                    (float) $line->quantity - (float) $line->quantity_invoiced - (float) $line->quantity_returned
                );

                return [
                    'id' => $line->id,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'quantity_base' => (float) $line->quantity_base,
                    'quantity_invoiced' => (float) $line->quantity_invoiced,
                    'quantity_returned' => (float) $line->quantity_returned,
                    'available_for_return' => $availableForReturn,
                    'unit_price' => (float) $line->unit_price,
                    'unit_cost_base' => (float) $line->unit_cost_base,
                    'line_total' => (float) $line->line_total,
                    'line_total_base' => (float) $line->line_total_base,
                    'uom' => [
                        'id' => $line->uom?->id,
                        'code' => $line->uom?->code,
                    ],
                    'base_uom' => [
                        'id' => $line->baseUom?->id,
                        'code' => $line->baseUom?->code,
                    ],
                    'variant' => $line->variant ? [
                        'id' => $line->variant->id,
                        'sku' => $line->variant->sku,
                        'product_name' => $line->variant->product?->name,
                    ] : null,
                    'lot' => $line->lot ? [
                        'id' => $line->lot->id,
                        'lot_code' => $line->lot->lot_code,
                        'expiry_date' => $line->lot->expiry_date,
                    ] : null,
                    'serial' => $line->serial ? [
                        'id' => $line->serial->id,
                        'serial_no' => $line->serial->serial_no,
                    ] : null,
                ];
            })->values();
        }

        return $data;
    }

    private function availablePurchaseOrders(array $selectedIds = [], ?int $partnerId = null, ?int $companyId = null, ?int $branchId = null)
    {
        $query = PurchaseOrder::query()
            ->with(['partner', 'branch.branchGroup.company', 'lines'])
            ->whereIn('status', [
                PurchaseOrderStatus::SENT->value,
                PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
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

        $purchaseOrders = $query->get();

        // Include any selected POs that aren't in the query result
        if (!empty($selectedIds)) {
            $existingIds = $purchaseOrders->pluck('id')->toArray();
            $missingIds = array_diff($selectedIds, $existingIds);

            if (!empty($missingIds)) {
                $additionalPOs = PurchaseOrder::with(['partner', 'branch.branchGroup.company', 'lines'])
                    ->whereIn('id', $missingIds)
                    ->get();

                foreach ($additionalPOs as $po) {
                    $purchaseOrders->push($po);
                }
            }
        }

        return $purchaseOrders->map(function (PurchaseOrder $purchaseOrder) {
            $remainingQty = $purchaseOrder->lines->sum(function ($line) {
                return max(0, (float) $line->quantity - (float) $line->quantity_received);
            });

            return [
                'id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'status' => $purchaseOrder->status,
                'order_date' => optional($purchaseOrder->order_date)?->toDateString(),
                'partner' => $purchaseOrder->partner ? [
                    'id' => $purchaseOrder->partner->id,
                    'name' => $purchaseOrder->partner->name,
                ] : null,
                'branch' => $purchaseOrder->branch ? [
                    'id' => $purchaseOrder->branch->id,
                    'name' => $purchaseOrder->branch->name,
                ] : null,
                'remaining_quantity' => $remainingQty,
            ];
        });
    }

    private function purchaseOrdersDetail(array $purchaseOrderIds): array
    {
        $purchaseOrders = PurchaseOrder::with([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
        ])->whereIn('id', $purchaseOrderIds)->get();

        $result = [];

        foreach ($purchaseOrders as $purchaseOrder) {
            $lines = $purchaseOrder->lines->map(function ($line) {
                $remaining = max(0, (float) $line->quantity - (float) $line->quantity_received);
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
                    'received_quantity' => (float) $line->quantity_received,
                    'remaining_quantity' => $remaining,
                    'unit_price' => (float) $line->unit_price,
                ];
            })->values();

            $result[] = [
                'id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'status' => $purchaseOrder->status,
                'partner' => $purchaseOrder->partner ? [
                    'id' => $purchaseOrder->partner->id,
                    'name' => $purchaseOrder->partner->name,
                ] : null,
                'branch' => $purchaseOrder->branch ? [
                    'id' => $purchaseOrder->branch->id,
                    'name' => $purchaseOrder->branch->name,
                    'company' => $purchaseOrder->branch->branchGroup?->company ? [
                        'id' => $purchaseOrder->branch->branchGroup->company->id,
                        'name' => $purchaseOrder->branch->branchGroup->company->name,
                    ] : null,
                ] : null,
                'currency' => $purchaseOrder->currency ? [
                    'id' => $purchaseOrder->currency->id,
                    'code' => $purchaseOrder->currency->code,
                ] : null,
                'lines' => $lines,
            ];
        }

        return $result;
    }

    private function purchaseOrderDetail(int $purchaseOrderId): ?array
    {
        $result = $this->purchaseOrdersDetail([$purchaseOrderId]);
        return $result[0] ?? null;
    }

    private function getGoodsReceiptPurchaseOrders(GoodsReceipt $goodsReceipt): array
    {
        // Try multi-PO relationship first
        $purchaseOrders = $goodsReceipt->purchaseOrders;

        // Fallback to single PO
        if ($purchaseOrders->isEmpty() && $goodsReceipt->purchaseOrder) {
            $purchaseOrders = collect([$goodsReceipt->purchaseOrder]);
        }

        $result = [];

        foreach ($purchaseOrders as $purchaseOrder) {
            // Calculate remaining quantity considering current GRN lines
            $grnLinesByPoLineId = $goodsReceipt->lines->keyBy('purchase_order_line_id');

            $lines = $purchaseOrder->lines->map(function ($line) use ($grnLinesByPoLineId) {
                $grnLine = $grnLinesByPoLineId->get($line->id);
                $currentGrnQty = $grnLine ? (float) $grnLine->quantity : 0;

                // Remaining = ordered - received (from other GRNs) + current GRN qty (to allow editing)
                $receivedFromOthers = max(0, (float) $line->quantity_received - $currentGrnQty);
                $remaining = max(0, (float) $line->quantity - $receivedFromOthers);

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
                    'received_quantity' => $receivedFromOthers,
                    'remaining_quantity' => $remaining,
                    'current_grn_quantity' => $currentGrnQty,
                    'unit_price' => (float) $line->unit_price,
                ];
            })->values();

            $result[] = [
                'id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'status' => $purchaseOrder->status,
                'partner' => $purchaseOrder->partner ? [
                    'id' => $purchaseOrder->partner->id,
                    'name' => $purchaseOrder->partner->name,
                ] : null,
                'branch' => $purchaseOrder->branch ? [
                    'id' => $purchaseOrder->branch->id,
                    'name' => $purchaseOrder->branch->name,
                    'company' => $purchaseOrder->branch->branchGroup?->company ? [
                        'id' => $purchaseOrder->branch->branchGroup->company->id,
                        'name' => $purchaseOrder->branch->branchGroup->company->name,
                    ] : null,
                ] : null,
                'lines' => $lines,
            ];
        }

        return $result;
    }

    private function transformGoodsReceiptForEdit(GoodsReceipt $receipt): array
    {
        $lines = $receipt->lines->map(function ($line) {
            return [
                'id' => $line->id,
                'purchase_order_line_id' => $line->purchase_order_line_id,
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
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'receipt_date' => optional($receipt->receipt_date)?->toDateString(),
            'location_id' => $receipt->location_id,
            'notes' => $receipt->notes,
            'location' => $receipt->location ? [
                'id' => $receipt->location->id,
                'code' => $receipt->location->code,
                'name' => $receipt->location->name,
            ] : null,
            'lines' => $lines,
        ];
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

    private function supplierOptions(): array
    {
        return Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->whereHas('purchaseOrders', function ($q) {
                $q->whereIn('status', [
                    PurchaseOrderStatus::SENT->value,
                    PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
                ]);
            })
            ->orderBy('name')
            ->get()
            ->map(fn ($partner) => [
                'value' => $partner->id,
                'label' => $partner->name,
            ])
            ->values()
            ->toArray();
    }

    private function supplierOptionsFiltered(?int $companyId): array
    {
        $query = Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->whereHas('purchaseOrders', function ($q) use ($companyId) {
                $q->whereIn('status', [
                    PurchaseOrderStatus::SENT->value,
                    PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
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
            ])
            ->values()
            ->toArray();
    }

    private function statusOptions(): array
    {
        return collect(GoodsReceiptStatus::cases())
            ->mapWithKeys(fn (GoodsReceiptStatus $status) => [
                $status->value => $status->label(),
            ])
            ->toArray();
    }

    private function valuationOptions(): array
    {
        return [
            ['value' => 'fifo', 'label' => 'FIFO'],
            ['value' => 'moving_avg', 'label' => 'Moving Average'],
        ];
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

    /**
     * API endpoint for suppliers with available Purchase Orders.
     * Used by AppPopoverSearch component.
     */
    public function apiSuppliersWithPOs(Request $request)
    {
        $query = Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->whereHas('purchaseOrders', function ($q) {
                $q->whereIn('status', [
                    PurchaseOrderStatus::SENT->value,
                    PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
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

    public function destroy(GoodsReceipt $goodsReceipt, PurchaseService $purchaseService): RedirectResponse
    {
        try {
            $purchaseService->deleteGoodsReceipt($goodsReceipt);
        } catch (PurchaseOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::route('goods-receipts.index')
            ->with('success', 'Penerimaan Barang berhasil dihapus.');
    }

    public function bulkDelete(Request $request, PurchaseService $purchaseService): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'exists:goods_receipts,id'],
        ]);

        DB::transaction(function () use ($request, $purchaseService) {
            foreach ($request->ids as $id) {
                $goodsReceipt = GoodsReceipt::find($id);
                if ($goodsReceipt) {
                    $purchaseService->deleteGoodsReceipt($goodsReceipt);
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('goods-receipts.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Penerimaan Barang berhasil dihapus.');
        }

        return Redirect::route('goods-receipts.index')
            ->with('success', 'Penerimaan Barang berhasil dihapus.');
    }

    public function exportXLSX(Request $request)
    {
        $goodsReceipts = $this->getFilteredGoodsReceipts($request);
        return Excel::download(new GoodsReceiptsExport($goodsReceipts), 'goods-receipts.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $goodsReceipts = $this->getFilteredGoodsReceipts($request);
        return Excel::download(new GoodsReceiptsExport($goodsReceipts), 'goods-receipts.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $goodsReceipts = $this->getFilteredGoodsReceipts($request);
        return Excel::download(new GoodsReceiptsExport($goodsReceipts), 'goods-receipts.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
    }

    private function getFilteredGoodsReceipts(Request $request)
    {
        $filters = $request->all() ?: Session::get('goods_receipts.index_filters', []);

        $query = GoodsReceipt::query()
            ->with(['supplier', 'location', 'branch']);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(receipt_number) like ?', ["%{$search}%"])
                    ->orWhereHas('supplier', fn ($sq) => $sq->whereRaw('lower(name) like ?', ["%{$search}%"]));
            });
        }

        if ($companyIds = Arr::wrap($filters['company_id'] ?? [])) {
            $query->whereIn('company_id', array_filter($companyIds));
        }

        if ($branchIds = Arr::wrap($filters['branch_id'] ?? [])) {
            $query->whereIn('branch_id', array_filter($branchIds));
        }

        if ($statuses = Arr::wrap($filters['status'] ?? [])) {
            $query->whereIn('status', array_filter($statuses));
        }

        if ($filters['from_date'] ?? null) {
            $query->whereDate('receipt_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('receipt_date', '<=', $filters['to_date']);
        }

        $sort = $filters['sort'] ?? 'receipt_date';
        $order = $filters['order'] ?? 'desc';

        return $query->orderBy($sort, $order)->get();
    }

    /**
     * API: Get lots for a product variant (filter: expiry not reached)
     */
    public function apiLots(Request $request)
    {
        $query = Lot::query();

        if ($request->product_variant_id) {
            $query->where('product_variant_id', $request->product_variant_id);
        }

        // Filter lots where expiry_date is null or >= receipt_date
        $receiptDate = $request->input('receipt_date', now()->toDateString());
        $query->where(function ($q) use ($receiptDate) {
            $q->whereNull('expiry_date')
              ->orWhere('expiry_date', '>=', $receiptDate);
        });

        if ($request->search) {
            $search = strtolower($request->search);
            $query->whereRaw('lower(lot_code) like ?', ["%{$search}%"]);
        }

        return $query->orderBy('lot_code')->get(['id', 'lot_code', 'mfg_date', 'expiry_date']);
    }

    /**
     * API: Create a new lot
     */
    public function apiStoreLot(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'lot_code' => ['required', 'string', 'max:100'],
            'mfg_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $lot = Lot::create($data);

        return response()->json($lot, 201);
    }

    /**
     * API: Get serials for a product variant
     */
    public function apiSerials(Request $request)
    {
        $query = Serial::query();

        if ($request->product_variant_id) {
            $query->where('product_variant_id', $request->product_variant_id);
        }

        if ($request->search) {
            $search = strtolower($request->search);
            $query->whereRaw('lower(serial_no) like ?', ["%{$search}%"]);
        }

        return $query->orderBy('serial_no')->get(['id', 'serial_no']);
    }

    /**
     * API: Create a new serial
     */
    public function apiStoreSerial(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'serial_no' => ['required', 'string', 'max:100'],
        ]);

        $serial = Serial::create($data);

        return response()->json($serial, 201);
    }

    /**
     * Display the print view for GR.
     */
    public function print(GoodsReceipt $goodsReceipt): Response
    {
        $goodsReceipt->load([
            'purchaseOrder',
            'purchaseOrders',
            'lines.variant.product',
            'lines.uom',
            'lines.lot',
            'lines.serial',
            'location',
            'creator:global_id,name',
            'branch',
            'company',
            'supplier',
        ]);

        return Inertia::render('GoodsReceipts/Print', [
            'goodsReceipt' => $goodsReceipt,
        ]);
    }
}


