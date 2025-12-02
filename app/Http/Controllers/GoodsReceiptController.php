<?php

namespace App\Http\Controllers;

use App\Enums\Documents\GoodsReceiptStatus;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

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
                'purchaseOrder.partner',
                'purchaseOrder.currency',
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
        $selectedId = $request->integer('purchase_order_id');
        $selectedPurchaseOrder = $selectedId ? $this->purchaseOrderDetail($selectedId) : null;
        $locations = $selectedPurchaseOrder
            ? $this->locationOptions($selectedPurchaseOrder['branch']['id'])
            : [];

        return Inertia::render('GoodsReceipts/Create', [
            'purchaseOrders' => $this->availablePurchaseOrders($selectedId),
            'selectedPurchaseOrder' => $selectedPurchaseOrder,
            'locations' => $locations,
            'valuationMethods' => $this->valuationOptions(),
            'defaultValuationMethod' => config('inventory.default_valuation_method', 'fifo'),
        ]);
    }

    public function store(Request $request, PurchaseService $purchaseService): RedirectResponse
    {
        $data = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'receipt_date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'valuation_method' => ['nullable', 'in:fifo,moving_avg'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'exists:purchase_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $purchaseOrder = PurchaseOrder::findOrFail($data['purchase_order_id']);

        try {
            $goodsReceipt = $purchaseService->postGrn($purchaseOrder, $data);
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
        $goodsReceipt->load([
            'purchaseOrder.partner',
            'purchaseOrder.branch.branchGroup.company',
            'purchaseOrder.currency',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'location',
            'currency',
            'inventoryTransaction',
        ]);

        return Inertia::render('GoodsReceipts/Show', [
            'goodsReceipt' => $this->transformGoodsReceipt($goodsReceipt, includeLines: true),
            'filters' => Session::get('goods_receipts.index_filters', []),
        ]);
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
            'location' => $receipt->location ? [
                'id' => $receipt->location->id,
                'code' => $receipt->location->code,
                'name' => $receipt->location->name,
            ] : null,
            'posted_at' => optional($receipt->posted_at)?->toDateTimeString(),
        ];

        if ($includeLines) {
            $data['lines'] = $receipt->lines->map(function ($line) {
                return [
                    'id' => $line->id,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'quantity_base' => (float) $line->quantity_base,
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
                ];
            })->values();
        }

        return $data;
    }

    private function availablePurchaseOrders(?int $selectedId = null)
    {
        $query = PurchaseOrder::query()
            ->with(['partner', 'branch.branchGroup.company', 'lines'])
            ->whereIn('status', [
                PurchaseOrderStatus::SENT->value,
                PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
            ])
            ->orderByDesc('order_date')
            ->limit(25);

        $purchaseOrders = $query->get();

        if ($selectedId && !$purchaseOrders->firstWhere('id', $selectedId)) {
            $selected = PurchaseOrder::with(['partner', 'branch.branchGroup.company', 'lines'])
                ->find($selectedId);

            if ($selected) {
                $purchaseOrders->push($selected);
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

    private function purchaseOrderDetail(int $purchaseOrderId): ?array
    {
        $purchaseOrder = PurchaseOrder::with([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
        ])->find($purchaseOrderId);

        if (!$purchaseOrder) {
            return null;
        }

        $hasRemaining = $purchaseOrder->lines->contains(function ($line) {
            return ((float) $line->quantity - (float) $line->quantity_received) > 0.0001;
        });

        if (!$hasRemaining) {
            return null;
        }

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

        return [
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

    private function locationOptions(int $branchId)
    {
        return Location::where('branch_id', $branchId)
            ->orderBy('code')
            ->get()
            ->map(fn ($location) => [
                'id' => $location->id,
                'label' => "{$location->code} — {$location->name}",
            ])
            ->values();
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

    private function supplierOptions()
    {
        return Partner::query()
            ->with('roles')
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();
    }
}


