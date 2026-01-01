<?php

namespace App\Http\Controllers;

use App\Enums\Documents\PurchaseOrderStatus;
use App\Enums\Documents\PurchasePlanStatus;
use App\Exceptions\PurchaseOrderException;
use App\Exports\PurchaseOrdersExport;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePlan;
use App\Models\Uom;
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

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('purchase_orders.index_filters', []);
        Session::put('purchase_orders.index_filters', $filters);

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

        $query = PurchaseOrder::query()
            ->with([
                'partner:id,name,code',
                'branch:id,name,branch_group_id',
                'branch.branchGroup:id,company_id',
                'currency:id,code',
            ]);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(order_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(notes) like ?', ["%{$search}%"])
                    ->orWhereHas('partner', function ($partnerQuery) use ($search) {
                        $partnerQuery->whereRaw('lower(name) like ?', ["%{$search}%"])
                            ->orWhereRaw('lower(code) like ?', ["%{$search}%"]);
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
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        $sort = $filters['sort'] ?? 'order_date';
        $order = $filters['order'] ?? 'desc';

        $allowedSorts = ['order_date', 'order_number', 'status', 'total_amount', 'expected_date'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'order_date';
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        $purchaseOrders = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
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
        $filters = Session::get('purchase_orders.index_filters', []);
        $formOptions = $this->formOptions();

        return Inertia::render('PurchaseOrders/Create', [
            'filters' => $filters,
            'companies' => $formOptions['companies'],
            'branches' => fn () => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'currencies' => $formOptions['currencies'],
            'suppliers' => $formOptions['suppliers'],
            'products' => $formOptions['products'],
            'uoms' => $formOptions['uoms'],
            'purchasePlans' => fn () => $this->getAvailablePurchasePlans($request->input('branch_id')),
        ]);
    }

    public function store(PurchaseOrderRequest $request, PurchaseService $service): RedirectResponse
    {
        try {
            $purchaseOrder = $service->create($request->validated());
        } catch (PurchaseOrderException $exception) {
            return Redirect::back()->withInput()->with('error', $exception->getMessage());
        }

        if ($request->input('create_another', false)) {
            return Redirect::route('purchase-orders.create')
                ->with('success', 'Purchase Order berhasil dibuat. Silahkan buat PO lainnya.');
        }

        return Redirect::route('purchase-orders.show', $purchaseOrder->id)
            ->with('success', 'Purchase Order berhasil dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder, PurchaseService $service): Response
    {
        $purchaseOrder->load([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
        ]);

        return Inertia::render('PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder,
            'filters' => Session::get('purchase_orders.index_filters', []),
            'allowedTransitions' => $service->allowedStatuses($purchaseOrder),
            'makerCheckerEnforced' => $service->shouldEnforceMakerChecker($purchaseOrder->company_id),
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'branch.branchGroup',
            'lines.variant',
            'lines.uom',
            'lines.baseUom',
        ]);

        $filters = Session::get('purchase_orders.index_filters', []);
        $formOptions = $this->formOptions();

        $companyId = $purchaseOrder->branch->branchGroup->company_id;

        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        return Inertia::render('PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'filters' => $filters,
            'companies' => $formOptions['companies'],
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'currencies' => $formOptions['currencies'],
            'suppliers' => $formOptions['suppliers'],
            'products' => $formOptions['products'],
            'uoms' => $formOptions['uoms'],
            'purchasePlans' => $this->getAvailablePurchasePlans($purchaseOrder->branch_id),
        ]);
    }

    public function update(
        PurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        PurchaseService $service
    ): RedirectResponse {
        try {
            $service->update($purchaseOrder, $request->validated());
        } catch (PurchaseOrderException $exception) {
            return Redirect::back()->withInput()->with('error', $exception->getMessage());
        }

        return Redirect::route('purchase-orders.show', $purchaseOrder->id)
            ->with('success', 'Purchase Order berhasil diperbarui.');
    }

    public function destroy(PurchaseOrder $purchaseOrder, PurchaseService $service): RedirectResponse
    {
        try {
            $service->delete($purchaseOrder);
        } catch (PurchaseOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::route('purchase-orders.index')
            ->with('success', 'Purchase Order berhasil dihapus.');
    }

    public function approve(PurchaseOrder $purchaseOrder, PurchaseService $service): RedirectResponse
    {
        try {
            $service->approve($purchaseOrder);
        } catch (PurchaseOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Purchase Order disetujui.');
    }

    public function send(PurchaseOrder $purchaseOrder, PurchaseService $service): RedirectResponse
    {
        try {
            $service->send($purchaseOrder);
        } catch (PurchaseOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Purchase Order ditandai sudah dikirim.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, PurchaseService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->cancel($purchaseOrder, reason: $data['reason'] ?? null);
        } catch (PurchaseOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Purchase Order dibatalkan.');
    }

    private function companyOptions()
    {
        return Company::orderBy('name')
            ->get(['id', 'name']);
    }

    private function branchOptions()
    {
        return Branch::with('branchGroup:id,company_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'company_id' => $branch->branchGroup?->company_id,
            ]);
    }

    private function supplierOptions()
    {
        return Partner::query()
            ->with('companies:id')
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->orderBy('name')
            ->get()
            ->map(function (Partner $partner) {
                return [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'code' => $partner->code,
                    'company_ids' => $partner->companies->pluck('id')->all(),
                ];
            });
    }

    private function formOptions(): array
    {
        return [
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'suppliers' => $this->supplierOptions(),
            'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
            'products' => Product::with(['variants.uom:id,code,name,company_id', 'companies:id'])
                ->orderBy('name')
                ->get()
                ->map(function (Product $product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'company_ids' => $product->companies->pluck('id')->all(),
                        'variants' => $product->variants->map(fn ($variant) => [
                            'id' => $variant->id,
                            'barcode' => $variant->barcode,
                            'sku' => $variant->sku,
                            'uom_id' => $variant->uom_id,
                            'uom' => [
                                'id' => $variant->uom?->id,
                                'code' => $variant->uom?->code,
                                'name' => $variant->uom?->name,
                                'kind' => $variant->uom?->kind,
                            ],
                        ]),
                    ];
                }),
            'uoms' => Uom::orderBy('code')->get(['id', 'code', 'name', 'company_id', 'kind']),
        ];
    }

    private function statusOptions(): array
    {
        return collect(PurchaseOrderStatus::cases())
            ->mapWithKeys(fn (PurchaseOrderStatus $status) => [
                $status->value => $status->label(),
            ])
            ->toArray();
    }

    /**
     * Get available purchase plans for a branch that have remaining items to order.
     */
    private function getAvailablePurchasePlans(?int $branchId): array
    {
        if (!$branchId) {
            return [];
        }

        return PurchasePlan::query()
            ->where('branch_id', $branchId)
            ->where('status', PurchasePlanStatus::CONFIRMED->value)
            ->with(['lines' => function ($query) {
                $query->whereRaw('planned_qty > ordered_qty')
                    ->with(['product:id,name', 'variant:id,sku,barcode', 'uom:id,code,name']);
            }])
            ->orderBy('plan_date', 'desc')
            ->get()
            ->filter(fn ($plan) => $plan->lines->isNotEmpty())
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'plan_number' => $plan->plan_number,
                    'plan_date' => $plan->plan_date->format('Y-m-d'),
                    'lines' => $plan->lines->map(fn ($line) => [
                        'id' => $line->id,
                        'product_id' => $line->product_id,
                        'product_name' => $line->product?->name,
                        'product_variant_id' => $line->product_variant_id,
                        'variant_sku' => $line->variant?->sku,
                        'uom_id' => $line->uom_id,
                        'uom_code' => $line->uom?->code,
                        'planned_qty' => (float) $line->planned_qty,
                        'ordered_qty' => (float) $line->ordered_qty,
                        'remaining_qty' => max(0, (float) $line->planned_qty - (float) $line->ordered_qty),
                        'description' => $line->description,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function bulkDelete(Request $request, PurchaseService $service): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'exists:purchase_orders,id'],
        ]);

        DB::transaction(function () use ($request, $service) {
            foreach ($request->ids as $id) {
                $purchaseOrder = PurchaseOrder::find($id);
                if ($purchaseOrder) {
                    $service->delete($purchaseOrder);
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('purchase-orders.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Purchase Orders berhasil dihapus.');
        }

        return Redirect::route('purchase-orders.index')
            ->with('success', 'Purchase Orders berhasil dihapus.');
    }

    public function exportXLSX(Request $request)
    {
        $purchaseOrders = $this->getFilteredPurchaseOrders($request);
        return Excel::download(new PurchaseOrdersExport($purchaseOrders), 'purchase-orders.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $purchaseOrders = $this->getFilteredPurchaseOrders($request);
        return Excel::download(new PurchaseOrdersExport($purchaseOrders), 'purchase-orders.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $purchaseOrders = $this->getFilteredPurchaseOrders($request);
        return Excel::download(new PurchaseOrdersExport($purchaseOrders), 'purchase-orders.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
    }

    private function getFilteredPurchaseOrders(Request $request)
    {
        $filters = $request->all() ?: Session::get('purchase_orders.index_filters', []);

        $query = PurchaseOrder::query()
            ->with(['partner', 'branch', 'currency']);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(order_number) like ?', ["%{$search}%"])
                    ->orWhereHas('partner', fn ($pq) => $pq->whereRaw('lower(name) like ?', ["%{$search}%"]));
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
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        $sort = $filters['sort'] ?? 'order_date';
        $order = $filters['order'] ?? 'desc';

        return $query->orderBy($sort, $order)->get();
    }

    /**
     * Display the print view for PO.
     */
    public function print(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.variant.product',
            'lines.uom',
            'creator:global_id,name',
            'approver:global_id,name',
        ]);

        return Inertia::render('PurchaseOrders/Print', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }
}
