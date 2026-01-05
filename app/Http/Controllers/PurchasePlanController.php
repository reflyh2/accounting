<?php

namespace App\Http\Controllers;

use App\Enums\Documents\PurchasePlanStatus;
use App\Http\Requests\PurchasePlanRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchasePlan;
use App\Models\Uom;
use App\Services\Purchasing\PurchasePlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class PurchasePlanController extends Controller
{
    public function __construct(
        private readonly PurchasePlanService $service
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('purchase_plans.index_filters', []);
        Session::put('purchase_plans.index_filters', $filters);

        $filterKeys = [
            'search',
            'company_id',
            'branch_id',
            'status',
            'from_date',
            'to_date',
            'per_page',
            'sort',
            'order',
        ];

        $filters = Arr::only($filters, $filterKeys);

        $query = PurchasePlan::query()
            ->with([
                'branch:id,name,branch_group_id',
                'branch.branchGroup:id,company_id',
                'branch.branchGroup.company:id,name',
            ]);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(plan_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(notes) like ?', ["%{$search}%"]);
            });
        }

        if ($companyIds = Arr::wrap($filters['company_id'] ?? [])) {
            $query->whereHas('branch.branchGroup', function ($q) use ($companyIds) {
                $q->whereIn('company_id', array_filter($companyIds));
            });
        }

        if ($branchIds = Arr::wrap($filters['branch_id'] ?? [])) {
            $query->whereIn('branch_id', array_filter($branchIds));
        }

        if ($statuses = Arr::wrap($filters['status'] ?? [])) {
            $query->whereIn('status', array_filter($statuses));
        }

        if ($filters['from_date'] ?? null) {
            $query->whereDate('plan_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('plan_date', '<=', $filters['to_date']);
        }

        $sort = $filters['sort'] ?? 'plan_date';
        $order = $filters['order'] ?? 'desc';

        $allowedSorts = ['plan_date', 'plan_number', 'status', 'required_date'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'plan_date';
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        $purchasePlans = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('PurchasePlans/Index', [
            'purchasePlans' => $purchasePlans,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $filters = Session::get('purchase_plans.index_filters', []);
        $formOptions = $this->formOptions();

        return Inertia::render('PurchasePlans/Create', [
            'filters' => $filters,
            'companies' => $formOptions['companies'],
            'branches' => $formOptions['branches'],
            'products' => $formOptions['products'],
            'uoms' => $formOptions['uoms'],
        ]);
    }

    public function store(PurchasePlanRequest $request): RedirectResponse
    {
        $purchasePlan = $this->service->create($request->validated());

        if ($request->input('create_another', false)) {
            return Redirect::route('purchase-plans.create')
                ->with('success', 'Rencana Pembelian berhasil dibuat. Silahkan buat lagi.');
        }

        return Redirect::route('purchase-plans.show', $purchasePlan->id)
            ->with('success', 'Rencana Pembelian berhasil dibuat.');
    }

    public function show(PurchasePlan $purchasePlan): Response
    {
        $purchasePlan->load([
            'branch.branchGroup.company',
            'lines.product',
            'lines.variant',
            'lines.uom',
        ]);

        return Inertia::render('PurchasePlans/Show', [
            'purchasePlan' => $purchasePlan,
            'filters' => Session::get('purchase_plans.index_filters', []),
            'allowedTransitions' => $this->service->allowedStatuses($purchasePlan),
        ]);
    }

    public function edit(Request $request, PurchasePlan $purchasePlan): Response
    {
        $purchasePlan->load([
            'branch.branchGroup',
            'lines.product',
            'lines.variant',
            'lines.uom',
        ]);

        $filters = Session::get('purchase_plans.index_filters', []);
        $formOptions = $this->formOptions();

        $companyId = $purchasePlan->branch->branchGroup->company_id;

        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        return Inertia::render('PurchasePlans/Edit', [
            'purchasePlan' => $purchasePlan,
            'filters' => $filters,
            'companies' => $formOptions['companies'],
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'products' => $formOptions['products'],
            'uoms' => $formOptions['uoms'],
        ]);
    }

    public function update(PurchasePlanRequest $request, PurchasePlan $purchasePlan): RedirectResponse
    {
        $this->service->update($purchasePlan, $request->validated());

        return Redirect::route('purchase-plans.show', $purchasePlan->id)
            ->with('success', 'Rencana Pembelian berhasil diperbarui.');
    }

    public function destroy(PurchasePlan $purchasePlan): RedirectResponse
    {
        try {
            $this->service->delete($purchasePlan);
        } catch (\App\Exceptions\PurchaseOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::route('purchase-plans.index')
            ->with('success', 'Rencana Pembelian berhasil dihapus.');
    }

    public function confirm(PurchasePlan $purchasePlan): RedirectResponse
    {
        try {
            $this->service->confirm($purchasePlan);
        } catch (\App\Exceptions\PurchaseOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Rencana Pembelian dikonfirmasi.');
    }

    public function close(PurchasePlan $purchasePlan): RedirectResponse
    {
        try {
            $this->service->close($purchasePlan);
        } catch (\App\Exceptions\PurchaseOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Rencana Pembelian ditutup.');
    }

    public function cancel(Request $request, PurchasePlan $purchasePlan): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->cancel($purchasePlan, null, $data['reason'] ?? null);
        } catch (\App\Exceptions\PurchaseOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Rencana Pembelian dibatalkan.');
    }

    private function companyOptions()
    {
        return Company::orderBy('name')->get(['id', 'name']);
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

    private function formOptions(): array
    {
        return [
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'products' => Product::with(['variants.uom:id,code,name,company_id', 'companies:id', 'defaultUom:id,code,name'])
                ->orderBy('name')
                ->get()
                ->map(function (Product $product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'default_uom_id' => $product->default_uom_id,
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
                        'default_uom' => [
                            'id' => $product->defaultUom?->id,
                            'code' => $product->defaultUom?->code,
                            'name' => $product->defaultUom?->name,
                            'kind' => $product->defaultUom?->kind,
                        ],
                    ];
                }),
            'uoms' => Uom::orderBy('code')->get(['id', 'code', 'name', 'company_id', 'kind']),
        ];
    }

    private function statusOptions(): array
    {
        return collect(PurchasePlanStatus::cases())
            ->mapWithKeys(fn (PurchasePlanStatus $status) => [
                $status->value => $status->label(),
            ])
            ->toArray();
    }
}
