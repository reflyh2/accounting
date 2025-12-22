<?php

namespace App\Http\Controllers;

use App\Enums\Documents\SalesOrderStatus;
use App\Exceptions\SalesOrderException;
use App\Http\Requests\SalesOrderRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Uom;
use App\Services\Sales\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('sales_orders.index_filters', []);
        Session::put('sales_orders.index_filters', $filters);

        $filterKeys = [
            'search',
            'company_id',
            'branch_id',
            'partner_id',
            'status',
            'from_date',
            'to_date',
            'reserve_stock',
            'per_page',
            'sort',
            'order',
        ];

        $filters = Arr::only($filters, $filterKeys);

        $query = SalesOrder::query()
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
                    ->orWhereRaw('lower(customer_reference) like ?', ["%{$search}%"])
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

        if (($filters['reserve_stock'] ?? null) !== null && $filters['reserve_stock'] !== '') {
            $query->where('reserve_stock', (bool) $filters['reserve_stock']);
        }

        if ($filters['from_date'] ?? null) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        $sort = $filters['sort'] ?? 'order_date';
        $order = $filters['order'] ?? 'desc';
        $allowedSorts = ['order_date', 'order_number', 'status', 'total_amount'];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'order_date';
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        $salesOrders = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('SalesOrders/Index', [
            'salesOrders' => $salesOrders,
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

    public function create(): Response
    {
        return Inertia::render('SalesOrders/Create', [
            'filters' => Session::get('sales_orders.index_filters', []),
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function store(SalesOrderRequest $request, SalesService $service): RedirectResponse
    {
        try {
            $salesOrder = $service->create($request->validated());
        } catch (SalesOrderException $exception) {
            return Redirect::back()->withInput()->with('error', $exception->getMessage());
        }

        return Redirect::route('sales-orders.show', $salesOrder->id)
            ->with('success', 'Sales Order berhasil dibuat.');
    }

    public function show(SalesOrder $salesOrder, SalesService $service): Response
    {
        $salesOrder->load([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'lines.reservationLocation',
        ]);

        return Inertia::render('SalesOrders/Show', [
            'salesOrder' => $salesOrder,
            'filters' => Session::get('sales_orders.index_filters', []),
            'allowedTransitions' => $service->allowedStatuses($salesOrder),
        ]);
    }

    public function edit(SalesOrder $salesOrder): Response
    {
        $salesOrder->load([
            'lines.variant',
            'lines.uom',
            'lines.baseUom',
            'lines.reservationLocation',
        ]);

        return Inertia::render('SalesOrders/Edit', [
            'salesOrder' => $salesOrder,
            'filters' => Session::get('sales_orders.index_filters', []),
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function update(
        SalesOrderRequest $request,
        SalesOrder $salesOrder,
        SalesService $service
    ): RedirectResponse {
        try {
            $service->update($salesOrder, $request->validated());
        } catch (SalesOrderException $exception) {
            return Redirect::back()->withInput()->with('error', $exception->getMessage());
        }

        return Redirect::route('sales-orders.show', $salesOrder->id)
            ->with('success', 'Sales Order berhasil diperbarui.');
    }

    public function destroy(SalesOrder $salesOrder, SalesService $service): RedirectResponse
    {
        try {
            $service->delete($salesOrder);
        } catch (SalesOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::route('sales-orders.index')
            ->with('success', 'Sales Order berhasil dihapus.');
    }

    public function quote(SalesOrder $salesOrder, SalesService $service): RedirectResponse
    {
        try {
            $service->quote($salesOrder);
        } catch (SalesOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Sales Order ditandai sebagai Quote.');
    }

    public function confirm(SalesOrder $salesOrder, SalesService $service): RedirectResponse
    {
        try {
            $service->confirm($salesOrder);
        } catch (SalesOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Sales Order dikonfirmasi.');
    }

    public function cancel(Request $request, SalesOrder $salesOrder, SalesService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->cancel($salesOrder, reason: $data['reason'] ?? null);
        } catch (SalesOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Sales Order dibatalkan.');
    }

    public function reserve(SalesOrder $salesOrder, SalesService $service): RedirectResponse
    {
        try {
            $service->applyReservation($salesOrder);
        } catch (SalesOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Reservasi stok berhasil diterapkan.');
    }

    public function releaseReservation(SalesOrder $salesOrder, SalesService $service): RedirectResponse
    {
        try {
            $service->releaseReservation($salesOrder);
        } catch (SalesOrderException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }

        return Redirect::back()->with('success', 'Reservasi stok berhasil dibatalkan.');
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

    private function customerOptions()
    {
        return Partner::query()
            ->with('companies:id')
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
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

    private function locationOptions()
    {
        return Location::with('branch:id,name')
            ->orderBy('code')
            ->get()
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'branch_id' => $location->branch_id,
            ]);
    }

    private function formOptions(): array
    {
        return [
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
            'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
            'products' => Product::with(['variants.uom:id,code,name,company_id', 'companies:id', 'taxCategory'])
                ->orderBy('name')
                ->get()
                ->map(function (Product $product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'company_ids' => $product->companies->pluck('id')->all(),
                        'tax_category_id' => $product->tax_category_id,
                        'variants' => $product->variants->map(fn ($variant) => [
                            'id' => $variant->id,
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
            'locations' => $this->locationOptions(),
            'channels' => \App\Enums\SalesChannel::options(),
        ];
    }

    private function statusOptions(): array
    {
        return collect(SalesOrderStatus::cases())
            ->mapWithKeys(fn (SalesOrderStatus $status) => [
                $status->value => $status->label(),
            ])
            ->toArray();
    }

    /**
     * Display the print view for SO.
     */
    public function print(SalesOrder $salesOrder): Response
    {
        $salesOrder->load([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.variant.product',
            'lines.uom',
            'creator',
        ]);

        return Inertia::render('SalesOrders/Print', [
            'salesOrder' => $salesOrder,
        ]);
    }
}


