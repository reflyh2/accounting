<?php

namespace App\Http\Controllers;

use App\Enums\Documents\PurchaseReturnStatus;
use App\Exports\PurchaseReturnsExport;
use App\Exceptions\PurchaseReturnException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Partner;
use App\Models\PurchaseReturn;
use App\Services\Purchasing\PurchaseReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseReturnController extends Controller
{
    private const QTY_TOLERANCE = 0.0005;

    public function __construct(
        private readonly PurchaseReturnService $purchaseReturnService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('purchase_returns.index_filters', []);
        Session::put('purchase_returns.index_filters', $filters);

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

        $query = PurchaseReturn::query()
            ->with([
                'purchaseOrder.partner',
                'goodsReceipt',
                'partner',
                'branch.branchGroup.company',
            ]);

        if ($search = trim(strtolower($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->whereRaw('lower(return_number) like ?', ["%{$search}%"])
                    ->orWhereHas('goodsReceipt', function ($q) use ($search) {
                        $q->whereRaw('lower(receipt_number) like ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('purchaseOrder', function ($q) use ($search) {
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

        $purchaseReturns = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PurchaseReturn $purchaseReturn) => $this->transformPurchaseReturn($purchaseReturn));

        return Inertia::render('PurchaseReturns/Index', [
            'purchaseReturns' => $purchaseReturns,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'suppliers' => $this->supplierOptions(),
            'reasonOptions' => $this->reasonOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $selectedCompanyId = $request->integer('company_id') ?: null;
        $selectedBranchId = $request->integer('branch_id') ?: null;
        $selectedSupplierId = $request->integer('supplier_id') ?: null;
        $selectedId = $request->integer('goods_receipt_id') ?: null;

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

        // Get suppliers filtered by company/branch (suppliers with returnable GRNs)
        $suppliers = $this->supplierOptionsFiltered($selectedCompanyId, $selectedBranchId);

        return Inertia::render('PurchaseReturns/Create', [
            'filters' => Session::get('purchase_returns.index_filters', []),
            'companies' => $companies,
            'branches' => $branches,
            'suppliers' => $suppliers,
            'selectedCompanyId' => $selectedCompanyId,
            'selectedBranchId' => $selectedBranchId,
            'selectedSupplierId' => $selectedSupplierId,
            'goodsReceipts' => fn () => $this->availableGoodsReceiptsFiltered($selectedId, $selectedCompanyId, $selectedBranchId, $selectedSupplierId),
            'selectedGoodsReceipt' => fn () => $selectedId ? $this->goodsReceiptDetail($selectedId) : null,
            'reasonOptions' => $this->reasonOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'goods_receipt_id' => ['required', 'exists:goods_receipts,id'],
            'return_date' => ['required', 'date'],
            'reason_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id' => ['required', 'exists:goods_receipt_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        try {
            $purchaseReturn = $this->purchaseReturnService->create($validated);
        } catch (PurchaseReturnException $exception) {
            return Redirect::back()
                ->withInput()
                ->withErrors(['lines' => $exception->getMessage()]);
        }

        return Redirect::route('purchase-returns.show', $purchaseReturn->id)
            ->with('success', 'Retur pembelian berhasil diposting.');
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load([
            'purchaseOrder.partner',
            'goodsReceipt',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'partner',
            'location',
            'currency',
        ]);

        return Inertia::render('PurchaseReturns/Show', [
            'purchaseReturn' => $this->transformPurchaseReturn($purchaseReturn, includeLines: true),
            'filters' => Session::get('purchase_returns.index_filters', []),
            'reasonOptions' => $this->reasonOptions(),
        ]);
    }

    /**
     * API endpoint for suppliers with returnable GRNs.
     * Used by AppPopoverSearch component.
     */
    public function apiSuppliersWithGRNs(Request $request)
    {
        $query = Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->whereHas('goodsReceipts', function ($q) {
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

    public function exportXLSX(Request $request)
    {
        $purchaseReturns = $this->getFilteredReturns($request->all());

        return Excel::download(new PurchaseReturnsExport($purchaseReturns), 'purchase-returns.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $purchaseReturns = $this->getFilteredReturns($request->all());

        return Excel::download(
            new PurchaseReturnsExport($purchaseReturns),
            'purchase-returns.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function exportPDF(Request $request)
    {
        $purchaseReturns = $this->getFilteredReturns($request->all());

        return Excel::download(
            new PurchaseReturnsExport($purchaseReturns),
            'purchase-returns.pdf',
            \Maatwebsite\Excel\Excel::MPDF
        );
    }

    private function transformPurchaseReturn(PurchaseReturn $purchaseReturn, bool $includeLines = false): array
    {
        $reasonLabels = $this->reasonLabels();

        $data = [
            'id' => $purchaseReturn->id,
            'return_number' => $purchaseReturn->return_number,
            'return_date' => optional($purchaseReturn->return_date)?->toDateString(),
            'status' => $purchaseReturn->status,
            'reason_code' => $purchaseReturn->reason_code,
            'reason_label' => $purchaseReturn->reason_code
                ? ($reasonLabels[$purchaseReturn->reason_code] ?? $purchaseReturn->reason_code)
                : null,
            'total_quantity' => (float) $purchaseReturn->total_quantity,
            'total_value' => (float) $purchaseReturn->total_value,
            'total_value_base' => (float) $purchaseReturn->total_value_base,
            'notes' => $purchaseReturn->notes,
            'posted_at' => optional($purchaseReturn->posted_at)?->toDateTimeString(),
            'purchase_order' => $purchaseReturn->purchaseOrder ? [
                'id' => $purchaseReturn->purchaseOrder->id,
                'order_number' => $purchaseReturn->purchaseOrder->order_number,
            ] : null,
            'goods_receipt' => $purchaseReturn->goodsReceipt ? [
                'id' => $purchaseReturn->goodsReceipt->id,
                'receipt_number' => $purchaseReturn->goodsReceipt->receipt_number,
            ] : null,
            'partner' => $purchaseReturn->partner ? [
                'id' => $purchaseReturn->partner->id,
                'name' => $purchaseReturn->partner->name,
            ] : null,
            'location' => $purchaseReturn->location ? [
                'id' => $purchaseReturn->location->id,
                'name' => $purchaseReturn->location->name,
                'code' => $purchaseReturn->location->code,
            ] : null,
        ];

        if ($includeLines) {
            $data['lines'] = $purchaseReturn->lines->map(function ($line) {
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
        return config('purchasing.return_reasons', []);
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
        return collect(PurchaseReturnStatus::cases())
            ->mapWithKeys(fn (PurchaseReturnStatus $status) => [$status->value => $status->label()])
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

    private function supplierOptions()
    {
        return Partner::query()
            ->with('roles')
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();
    }

    private function supplierOptionsFiltered(?int $companyId = null, ?int $branchId = null): array
    {
        $query = Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->whereHas('goodsReceipts', function ($q) use ($companyId, $branchId) {
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

    private function availableGoodsReceipts(?int $selectedId = null)
    {
        $query = GoodsReceipt::query()
            ->with(['purchaseOrder.partner', 'branch', 'lines'])
            ->where('status', 'posted')
            ->whereHas('lines', function ($builder) {
                $builder->whereRaw('(quantity - quantity_invoiced - quantity_returned) > ?', [self::QTY_TOLERANCE]);
            })
            ->orderByDesc('receipt_date')
            ->limit(25);

        $goodsReceipts = $query->get();

        if ($selectedId && !$goodsReceipts->firstWhere('id', $selectedId)) {
            $selected = GoodsReceipt::with(['purchaseOrder.partner', 'branch', 'lines'])
                ->find($selectedId);

            if ($selected) {
                $goodsReceipts->push($selected);
            }
        }

        return $goodsReceipts->map(function (GoodsReceipt $receipt) {
            $availableQty = $receipt->lines->sum(function ($line) {
                return max(0, (float) $line->quantity - (float) $line->quantity_invoiced - (float) $line->quantity_returned);
            });

            return [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'partner' => $receipt->purchaseOrder?->partner?->name,
                'branch' => $receipt->branch?->name,
                'available_quantity' => $availableQty,
            ];
        })->values();
    }

    private function availableGoodsReceiptsFiltered(?int $selectedId = null, ?int $companyId = null, ?int $branchId = null, ?int $supplierId = null)
    {
        $query = GoodsReceipt::query()
            ->with(['purchaseOrders.partner', 'branch.branchGroup.company', 'lines'])
            ->where('status', 'posted')
            ->whereHas('lines', function ($builder) {
                $builder->whereRaw('(quantity - quantity_invoiced - quantity_returned) > ?', [self::QTY_TOLERANCE]);
            })
            ->orderByDesc('receipt_date')
            ->limit(50);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $goodsReceipts = $query->get();

        if ($selectedId && !$goodsReceipts->firstWhere('id', $selectedId)) {
            $selected = GoodsReceipt::with(['purchaseOrders.partner', 'branch.branchGroup.company', 'lines'])
                ->find($selectedId);

            if ($selected) {
                $goodsReceipts->push($selected);
            }
        }

        return $goodsReceipts->map(function (GoodsReceipt $receipt) {
            $availableQty = $receipt->lines->sum(function ($line) {
                return max(0, (float) $line->quantity - (float) $line->quantity_invoiced - (float) $line->quantity_returned);
            });

            return [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'branch' => $receipt->branch?->name,
                'company' => $receipt->branch?->branchGroup?->company?->name,
                'available_quantity' => $availableQty,
            ];
        })->values();
    }

    private function goodsReceiptDetail(int $goodsReceiptId): ?array
    {
        $goodsReceipt = GoodsReceipt::with([
            'purchaseOrders.partner',
            'supplier',
            'branch.branchGroup.company',
            'currency',
            'location',
            'lines.variant.product',
            'lines.uom',
            'lines.purchaseOrderLine',
        ])->find($goodsReceiptId);

        if (!$goodsReceipt) {
            return null;
        }

        $lines = $goodsReceipt->lines->map(function ($line) {
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
                'ordered_quantity' => (float) $line->purchaseOrderLine?->quantity,
                'unit_price' => (float) $line->unit_price,
                'unit_cost_base' => (float) $line->unit_cost_base,
            ];
        })->filter(fn ($line) => $line['available_quantity'] > self::QTY_TOLERANCE)
            ->values();

        if (!$lines->count()) {
            return null;
        }

        // Get purchase order numbers from the many-to-many relationship
        $purchaseOrders = $goodsReceipt->purchaseOrders->map(fn ($po) => [
            'id' => $po->id,
            'order_number' => $po->order_number,
        ])->values();

        return [
            'id' => $goodsReceipt->id,
            'receipt_number' => $goodsReceipt->receipt_number,
            'receipt_date' => optional($goodsReceipt->receipt_date)?->toDateString(),
            'purchase_orders' => $purchaseOrders,
            'supplier' => $goodsReceipt->supplier ? [
                'id' => $goodsReceipt->supplier->id,
                'name' => $goodsReceipt->supplier->name,
            ] : null,
            'branch' => $goodsReceipt->branch ? [
                'id' => $goodsReceipt->branch->id,
                'name' => $goodsReceipt->branch->name,
                'company' => $goodsReceipt->branch->branchGroup?->company?->name,
            ] : null,
            'location' => $goodsReceipt->location ? [
                'id' => $goodsReceipt->location->id,
                'name' => $goodsReceipt->location->name,
            ] : null,
            'currency' => $goodsReceipt->currency ? [
                'code' => $goodsReceipt->currency->code,
            ] : null,
            'lines' => $lines,
        ];
    }

    private function getFilteredReturns(array $filters)
    {
        $query = PurchaseReturn::with([
            'purchaseOrder',
            'goodsReceipt',
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
                    ->orWhereHas('purchaseOrder', function ($q) use ($search) {
                        $q->whereRaw('lower(order_number) like ?', ["%{$search}%"]);
                    });
            });
        }

        return $query->orderByDesc('return_date')->get();
    }
}


