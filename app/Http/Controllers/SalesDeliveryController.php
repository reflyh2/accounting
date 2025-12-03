<?php

namespace App\Http\Controllers;

use App\Exceptions\InventoryException;
use App\Exceptions\SalesOrderException;
use App\Http\Requests\SalesDeliveryRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Location;
use App\Models\Partner;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Services\Sales\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class SalesDeliveryController extends Controller
{
    public function __construct(private readonly SalesService $salesService)
    {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->input('search'),
            'company_id' => Arr::wrap($request->input('company_id', [])),
            'branch_id' => Arr::wrap($request->input('branch_id', [])),
            'partner_id' => Arr::wrap($request->input('partner_id', [])),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'per_page' => (int) ($request->input('per_page') ?? 10),
            'sort' => $request->input('sort', 'delivery_date'),
            'order' => $request->input('order', 'desc'),
        ];

        $query = SalesDelivery::query()
            ->with([
                'salesOrder:id,order_number',
                'partner:id,name',
                'branch:id,name,branch_group_id',
                'branch.branchGroup:id,company_id',
            ]);

        if ($filters['search']) {
            $search = strtolower($filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->whereRaw('LOWER(delivery_number) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('salesOrder', function ($orderQuery) use ($search) {
                        $orderQuery->whereRaw('LOWER(order_number) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if (!empty(array_filter($filters['company_id']))) {
            $query->whereIn('company_id', array_filter($filters['company_id']));
        }

        if (!empty(array_filter($filters['branch_id']))) {
            $query->whereIn('branch_id', array_filter($filters['branch_id']));
        }

        if (!empty(array_filter($filters['partner_id']))) {
            $query->whereIn('partner_id', array_filter($filters['partner_id']));
        }

        if ($filters['date_from']) {
            $query->whereDate('delivery_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('delivery_date', '<=', $filters['date_to']);
        }

        $perPage = max(5, min(100, $filters['per_page']));
        $allowedSorts = ['delivery_date', 'delivery_number'];
        $sort = in_array($filters['sort'], $allowedSorts, true) ? $filters['sort'] : 'delivery_date';
        $order = in_array(strtolower($filters['order']), ['asc', 'desc'], true)
            ? strtolower($filters['order'])
            : 'desc';

        $deliveries = $query->orderBy($sort, $order)
            ->orderBy('id', $order === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SalesDelivery $delivery) => $this->transformDeliveryListItem($delivery));

        return Inertia::render('SalesDeliveries/Index', [
            'deliveries' => $deliveries,
            'filters' => array_filter(
                Arr::except($filters, ['sort', 'order']),
                fn ($value) => $value !== null && $value !== '' && $value !== []
            ),
            'sort' => $sort,
            'order' => $order,
            'perPage' => $perPage,
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'customers' => $this->customerOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $salesOrder = null;

        if ($request->filled('sales_order_id')) {
            $salesOrder = SalesOrder::query()
                ->with([
                    'partner:id,name,code',
                    'branch:id,name,branch_group_id',
                    'branch.branchGroup:id,company_id',
                    'currency:id,code',
                    'lines.variant:id,sku',
                    'lines.uom:id,code',
                    'lines.baseUom:id,code',
                    'lines.reservationLocation:id,name,code',
                ])
                ->findOrFail($request->input('sales_order_id'));
        }

        return Inertia::render('SalesDeliveries/Create', [
            'salesOrder' => $salesOrder ? $this->salesOrderResource($salesOrder) : null,
            'formOptions' => [
                'locations' => $salesOrder
                    ? $this->locationOptions($salesOrder->branch_id)
                    : [],
            ],
        ]);
    }

    public function store(SalesDeliveryRequest $request): RedirectResponse
    {
        /** @var SalesOrder $salesOrder */
        $salesOrder = SalesOrder::findOrFail($request->input('sales_order_id'));

        try {
            $delivery = $this->salesService->postDelivery($salesOrder, $request->validated());
        } catch (SalesOrderException|InventoryException $exception) {
            return Redirect::back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return Redirect::route('sales-deliveries.show', $delivery->id)
            ->with('success', 'Pengiriman berhasil diposting.');
    }

    public function show(SalesDelivery $salesDelivery): Response
    {
        $salesDelivery->load([
            'salesOrder.partner',
            'salesOrder.branch.branchGroup.company',
            'currency',
            'location',
            'lines.variant',
            'lines.uom',
            'lines.baseUom',
        ]);

        return Inertia::render('SalesDeliveries/Show', [
            'delivery' => $this->transformDelivery($salesDelivery),
        ]);
    }

    private function transformDeliveryListItem(SalesDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'delivery_number' => $delivery->delivery_number,
            'delivery_date' => $delivery->delivery_date?->toDateString(),
            'status' => $delivery->status,
            'sales_order_id' => $delivery->sales_order_id,
            'sales_order_number' => $delivery->salesOrder?->order_number,
            'customer_name' => $delivery->partner?->name,
            'branch_name' => $delivery->branch?->name,
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
            'sales_order' => $delivery->salesOrder ? [
                'id' => $delivery->salesOrder->id,
                'order_number' => $delivery->salesOrder->order_number,
                'partner' => [
                    'name' => $delivery->salesOrder->partner?->name,
                ],
            ] : null,
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
                ];
            })->values(),
        ];
    }

    private function salesOrderResource(SalesOrder $salesOrder): array
    {
        return [
            'id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
            'status' => $salesOrder->status,
            'order_date' => $salesOrder->order_date?->toDateString(),
            'partner' => [
                'name' => $salesOrder->partner?->name,
                'code' => $salesOrder->partner?->code,
            ],
            'branch' => [
                'id' => $salesOrder->branch?->id,
                'name' => $salesOrder->branch?->name,
            ],
            'currency' => [
                'code' => $salesOrder->currency?->code,
            ],
            'lines' => $salesOrder->lines->map(function ($line) {
                $remaining = max(0.0, (float) $line->quantity - (float) $line->quantity_delivered);
                return [
                    'id' => $line->id,
                    'description' => $line->description,
                    'sku' => $line->variant?->sku,
                    'uom' => $line->uom?->code,
                    'quantity' => (float) $line->quantity,
                    'quantity_delivered' => (float) $line->quantity_delivered,
                    'quantity_reserved' => (float) $line->quantity_reserved,
                    'quantity_base' => (float) $line->quantity_base,
                    'quantity_delivered_base' => (float) $line->quantity_delivered_base,
                    'remaining_quantity' => $remaining,
                    'reservation_location' => $line->reservationLocation ? [
                        'id' => $line->reservationLocation->id,
                        'name' => $line->reservationLocation->name,
                        'code' => $line->reservationLocation->code,
                    ] : null,
                ];
            })->values(),
        ];
    }

    private function locationOptions(?int $branchId)
    {
        if (!$branchId) {
            return [];
        }

        return Location::query()
            ->where('branch_id', $branchId)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'label' => trim("{$location->code} — {$location->name}"),
            ])
            ->values();
    }

    private function companyOptions()
    {
        return Company::orderBy('name')->get(['id', 'name']);
    }

    private function branchOptions()
    {
        return Branch::orderBy('name')->get(['id', 'name']);
    }

    private function customerOptions()
    {
        return Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}


