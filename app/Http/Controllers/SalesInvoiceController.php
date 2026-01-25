<?php

namespace App\Http\Controllers;

use App\Enums\Documents\InvoiceStatus;
use App\Enums\Documents\SalesOrderStatus;
use App\Enums\TaxInvoiceCode;
use App\Exports\SalesInvoicesExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostItem;
use App\Models\Currency;
use App\Models\DocumentTemplate;
use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesDeliveryLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Uom;
use App\Models\User;
use App\Services\DocumentTemplateService;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class SalesInvoiceController extends Controller
{
    private const QTY_TOLERANCE = 0.0005;

    public function __construct(
        private readonly SalesInvoiceService $invoiceService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('sales_invoices.index_filters', []);
        Session::put('sales_invoices.index_filters', $filters);

        $query = SalesInvoice::with([
            'partner',
            'salesOrders',
            'currency',
            'branch.branchGroup.company',
        ]);

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(invoice_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(customer_invoice_number) like ?', ["%{$search}%"])
                    ->orWhereHas('salesOrders', function ($so) use ($search) {
                        $so->whereRaw('lower(order_number) like ?', ["%{$search}%"]);
                    });
            });
        }

        if (! empty($filters['company_id'])) {
            $query->whereHas('branch.branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', (array) $filters['company_id']);
            });
        }

        if (! empty($filters['branch_id'])) {
            $query->whereIn('branch_id', (array) $filters['branch_id']);
        }

        if (! empty($filters['partner_id'])) {
            $query->whereIn('partner_id', (array) $filters['partner_id']);
        }

        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'invoice_date';
        $sortOrder = $filters['order'] ?? 'desc';

        if (in_array($sortColumn, ['invoice_number', 'invoice_date', 'total_amount', 'status'], true)) {
            $query->orderBy($sortColumn, $sortOrder);
        } else {
            $query->orderBy('invoice_date', 'desc');
        }

        $invoices = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        // Transform for frontend
        $invoices->through(fn ($invoice) => $this->transformInvoiceListItem($invoice));

        $companies = Company::orderBy('name', 'asc')->get();
        $partners = Partner::orderBy('name', 'asc')->get();
        $branches = ! empty($filters['company_id'])
            ? Branch::whereHas('branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', (array) $filters['company_id']);
            })->orderBy('name', 'asc')->get()
            : Branch::orderBy('name', 'asc')->get();

        return Inertia::render('SalesInvoices/Index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'companies' => $companies,
            'branches' => $branches,
            'customers' => $partners,
            'statusOptions' => $this->statusOptions(),
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request): Response
    {
        $selectedPartnerId = $request->integer('partner_id') ?: null;
        $selectedIds = $request->input('sales_order_ids', []);
        if (! is_array($selectedIds)) {
            $selectedIds = $selectedIds ? [$selectedIds] : [];
        }
        $selectedIds = array_filter(array_map('intval', $selectedIds));

        $selectedSalesOrders = [];

        if (! empty($selectedIds)) {
            $selectedSalesOrders = $this->salesOrdersDetail($selectedIds);
            if (! empty($selectedSalesOrders) && ! $selectedPartnerId) {
                $selectedPartnerId = $selectedSalesOrders[0]['partner']['id'] ?? null;
            }
        }

        return Inertia::render('SalesInvoices/Create', [
            'salesOrders' => $this->availableSalesOrders($selectedIds, $selectedPartnerId),
            'selectedSalesOrders' => $selectedSalesOrders,
            'selectedPartnerId' => $selectedPartnerId,
            'customers' => $this->customerOptions(),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'currencies' => Currency::select('id', 'code', 'name')->get()->map(fn ($c) => [
                'value' => $c->id,
                'label' => $c->code.' - '.$c->name,
            ]),
            'products' => $this->productOptions(),
            'uoms' => $this->uomOptions(),
            'paymentMethods' => collect(\App\Enums\PaymentMethod::cases())
                ->map(fn (\App\Enums\PaymentMethod $method) => [
                    'value' => $method->value,
                    'label' => $method->label(),
                ])
                ->values()
                ->toArray(),
            'companyBankAccounts' => \App\Models\CompanyBankAccount::active()
                ->orderBy('bank_name')
                ->get()
                ->map(fn ($ba) => [
                    'value' => $ba->id,
                    'label' => "{$ba->bank_name} - {$ba->account_number} ({$ba->account_holder_name})",
                    'company_id' => $ba->company_id,
                ]),
            'costItems' => CostItem::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'company_id']),
            'users' => $this->userOptions(),
            'taxInvoiceCodeOptions' => TaxInvoiceCode::options(),
            'defaultTaxInvoiceCode' => TaxInvoiceCode::default()->value,
            'filters' => Session::get('sales_invoices.index_filters', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request);

        $invoice = $this->invoiceService->create($validated);

        if ($request->boolean('create_another', false)) {
            return Redirect::route('sales-invoices.create', [
                'sales_order_ids' => $invoice->salesOrders->pluck('id')->toArray(),
            ])->with('success', 'Faktur penjualan berhasil dibuat.');
        }

        return Redirect::route('sales-invoices.show', $invoice)
            ->with('success', 'Faktur penjualan berhasil dibuat.');
    }

    public function show(SalesInvoice $salesInvoice): Response
    {
        $salesInvoice->load([
            'salesOrders.partner',
            'salesOrders.branch',
            'partner',
            'lines.salesOrderLine',
            'lines.salesDeliveryLine.salesDelivery',
            'currency',
            'costs.costItem',
        ]);

        $filters = Session::get('sales_invoices.index_filters', []);

        return Inertia::render('SalesInvoices/Show', [
            'invoice' => $this->transformInvoice($salesInvoice),
            'filters' => $filters,
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
            'statusOptions' => $this->statusOptions(),
            'canPost' => $salesInvoice->status === InvoiceStatus::DRAFT->value,
            'canEdit' => $salesInvoice->status === InvoiceStatus::DRAFT->value,
            'canDelete' => $salesInvoice->status === InvoiceStatus::DRAFT->value,
        ]);
    }

    public function edit(SalesInvoice $salesInvoice): Response|RedirectResponse
    {
        $this->authorizeDraft($salesInvoice);

        $salesInvoice->load([
            'salesOrders.lines.uom',
            'salesOrders.lines.baseUom',
            'salesOrders.partner',
            'salesOrders.branch.branchGroup.company',
            'lines.salesOrderLine',
            'lines.salesDeliveryLine.salesDelivery',
            'partner',
            'currency',
        ]);

        $isDirectInvoice = $salesInvoice->salesOrders->isEmpty();

        $selectedSalesOrders = [];
        if (! $isDirectInvoice) {
            $selectedSalesOrders = $this->salesOrdersDetail($salesInvoice->salesOrders->pluck('id')->toArray());
        }

        return Inertia::render('SalesInvoices/Edit', [
            'invoice' => $this->transformInvoiceForEdit($salesInvoice),
            'isDirectInvoice' => $isDirectInvoice,
            'selectedSalesOrders' => $selectedSalesOrders,
            'selectedPartnerId' => $salesInvoice->partner_id,
            'customers' => $this->customerOptions(),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'currencies' => Currency::select('id', 'code', 'name')->get()->map(fn ($c) => [
                'value' => $c->id,
                'label' => $c->code.' - '.$c->name,
            ]),
            'products' => $this->productOptions(),
            'uoms' => $this->uomOptions(),
            'paymentMethods' => collect(\App\Enums\PaymentMethod::cases())
                ->map(fn (\App\Enums\PaymentMethod $method) => [
                    'value' => $method->value,
                    'label' => $method->label(),
                ])
                ->values()
                ->toArray(),
            'companyBankAccounts' => \App\Models\CompanyBankAccount::active()
                ->orderBy('bank_name')
                ->get()
                ->map(fn ($ba) => [
                    'value' => $ba->id,
                    'label' => "{$ba->bank_name} - {$ba->account_number} ({$ba->account_holder_name})",
                    'company_id' => $ba->company_id,
                ]),
            'costItems' => CostItem::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'company_id']),
            'users' => $this->userOptions(),
            'taxInvoiceCodeOptions' => TaxInvoiceCode::options(),
            'filters' => Session::get('sales_invoices.index_filters', []),
        ]);
    }

    public function update(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $this->authorizeDraft($salesInvoice);

        $validated = $this->validatedPayload($request);

        $invoice = $this->invoiceService->update($salesInvoice, $validated);

        return Redirect::route('sales-invoices.show', $invoice)
            ->with('success', 'Faktur penjualan berhasil diperbarui.');
    }

    public function destroy(SalesInvoice $salesInvoice): RedirectResponse
    {
        $this->authorizeDraft($salesInvoice);

        $this->invoiceService->delete($salesInvoice);

        return Redirect::route('sales-invoices.index')
            ->with('success', 'Faktur penjualan berhasil dihapus.');
    }

    public function post(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $this->authorizeDraft($salesInvoice);

        try {
            $this->invoiceService->post($salesInvoice);
        } catch (\App\Exceptions\SalesInvoiceException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        return Redirect::route('sales-invoices.show', $salesInvoice->id)
            ->with('success', 'Faktur penjualan berhasil diposting.');
    }

    public function exportXLSX(Request $request)
    {
        $filters = $request->all();
        $invoices = $this->getFilteredInvoices($filters);

        return Excel::download(new SalesInvoicesExport($invoices), 'sales-invoices.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $filters = $request->all();
        $invoices = $this->getFilteredInvoices($filters);

        return Excel::download(
            new SalesInvoicesExport($invoices),
            'sales-invoices.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function exportPDF(Request $request)
    {
        $filters = $request->all();
        $invoices = $this->getFilteredInvoices($filters);

        return Excel::download(
            new SalesInvoicesExport($invoices),
            'sales-invoices.pdf',
            \Maatwebsite\Excel\Excel::MPDF
        );
    }

    private function validatedPayload(Request $request): array
    {
        $isDirectInvoice = $request->boolean('is_direct_invoice', false);

        if ($isDirectInvoice) {
            $validated = $request->validate([
                'company_id' => 'required|exists:companies,id',
                'branch_id' => 'required|exists:branches,id',
                'partner_id' => 'required|exists:partners,id',
                'currency_id' => 'required|exists:currencies,id',
                'invoice_date' => 'required|date',
                'due_date' => 'nullable|date|after_or_equal:invoice_date',
                'customer_invoice_number' => 'nullable|string|max:120',
                'tax_invoice_code' => 'nullable|string|in:01,02,03,04,05,06,07,08,09,10',
                'exchange_rate' => 'required|numeric|min:0.000001',
                'notes' => 'nullable|string',
                'payment_method' => 'nullable|string|in:cash,transfer,cek,giro',
                'company_bank_account_id' => 'nullable|exists:company_bank_accounts,id|required_if:payment_method,transfer',
                'sales_person_id' => 'nullable|exists:users,global_id',
                'shipping_address_id' => 'nullable|exists:partner_addresses,id',
                'invoice_address_id' => 'nullable|exists:partner_addresses,id',
                'lines' => 'required|array|min:1',
                'lines.*.product_id' => 'nullable|exists:products,id',
                'lines.*.product_variant_id' => 'nullable|exists:product_variants,id',
                'lines.*.description' => 'required|string',
                'lines.*.uom_label' => 'nullable|string',
                'lines.*.quantity' => 'required|numeric|min:0.0001',
                'lines.*.unit_price' => 'required|numeric|min:0',
                'lines.*.discount_rate' => 'nullable|numeric|min:0|max:100',
                'lines.*.tax_rate' => 'nullable|numeric|min:0',
            ]);

            $validated['sales_order_ids'] = [];
        } else {
            $validated = $request->validate([
                'sales_order_ids' => 'required|array|min:1',
                'sales_order_ids.*' => 'exists:sales_orders,id',
                'invoice_date' => 'required|date',
                'due_date' => 'nullable|date|after_or_equal:invoice_date',
                'customer_invoice_number' => 'nullable|string|max:120',
                'tax_invoice_code' => 'nullable|string|in:01,02,03,04,05,06,07,08,09,10',
                'exchange_rate' => 'required|numeric|min:0.000001',
                'notes' => 'nullable|string',
                'payment_method' => 'nullable|string|in:cash,transfer,cek,giro',
                'company_bank_account_id' => 'nullable|exists:company_bank_accounts,id|required_if:payment_method,transfer',
                'sales_person_id' => 'nullable|exists:users,global_id',
                'shipping_address_id' => 'nullable|exists:partner_addresses,id',
                'invoice_address_id' => 'nullable|exists:partner_addresses,id',
                'lines' => 'required|array|min:1',
                'lines.*.sales_order_line_id' => 'required|exists:sales_order_lines,id',
                'lines.*.sales_delivery_line_id' => 'required|exists:sales_delivery_lines,id',
                'lines.*.description' => 'nullable|string',
                'lines.*.quantity' => 'required|numeric|min:0.0001',
                'lines.*.unit_price' => 'required|numeric|min:0',
                'lines.*.discount_rate' => 'nullable|numeric|min:0|max:100',
                'lines.*.tax_rate' => 'nullable|numeric|min:0',
            ]);
        }

        $validated['lines'] = collect($validated['lines'])
            ->map(function ($line) {
                $line['discount_rate'] = $line['discount_rate'] ?? 0;
                $line['tax_rate'] = $line['tax_rate'] ?? 0;

                return $line;
            })
            ->toArray();

        // Validate costs separately (applies to both direct and SO-based invoices)
        $costsValidation = $request->validate([
            'costs' => 'nullable|array',
            'costs.*.description' => 'nullable|string|max:255',
            'costs.*.cost_item_id' => 'nullable|exists:cost_items,id',
            'costs.*.amount' => 'required|numeric|min:0',
            'costs.*.currency_id' => 'nullable|exists:currencies,id',
            'costs.*.exchange_rate' => 'nullable|numeric|min:0.0001',
        ]);
        $validated['costs'] = $costsValidation['costs'] ?? [];

        return $validated;
    }

    private function salesOrdersDetail(array $salesOrderIds): array
    {
        if (empty($salesOrderIds)) {
            return [];
        }

        $salesOrders = SalesOrder::with([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.uom',
            'lines.baseUom',
            'costs.costItem',
        ])->whereIn('id', $salesOrderIds)->get();

        $result = [];

        foreach ($salesOrders as $salesOrder) {
            $deliveryLines = SalesDeliveryLine::with(['salesDelivery'])
                ->whereIn('sales_order_line_id', $salesOrder->lines->pluck('id'))
                ->orderBy('id')
                ->get();

            $lines = [];

            foreach ($deliveryLines as $line) {
                $soLine = $salesOrder->lines->firstWhere('id', $line->sales_order_line_id);
                if (! $soLine) {
                    continue;
                }

                $remainingDelivery = max(
                    0.0,
                    (float) $line->quantity - (float) $line->quantity_invoiced
                );

                $remainingSo = max(
                    0.0,
                    ((float) $soLine->quantity_delivered) - (float) $soLine->quantity_invoiced
                );

                $available = min($remainingDelivery, $remainingSo);

                if ($available <= self::QTY_TOLERANCE) {
                    continue;
                }

                $lines[] = [
                    'sales_order_line_id' => $soLine->id,
                    'sales_delivery_line_id' => $line->id,
                    'delivery_number' => $line->salesDelivery?->delivery_number,
                    'delivery_date' => optional($line->salesDelivery?->delivery_date)?->format('d/m/Y'),
                    'description' => $soLine->description,
                    'uom_label' => $soLine->uom?->name,
                    'quantity' => $this->roundQuantity($available),
                    'available_quantity' => $this->roundQuantity($available),
                    'max_quantity' => $this->roundQuantity($available),
                    'ordered_quantity' => $this->roundQuantity((float) $soLine->quantity),
                    'delivered_quantity' => $this->roundQuantity((float) $soLine->quantity_delivered),
                    'invoiced_quantity' => $this->roundQuantity((float) $soLine->quantity_invoiced),
                    'unit_price' => (float) $soLine->unit_price,
                    'discount_rate' => (float) $soLine->discount_rate,
                    'discount_amount' => (float) $soLine->discount_amount,
                    'tax_rate' => (float) $soLine->tax_rate,
                    'tax_amount' => (float) $soLine->tax_amount,
                ];
            }

            $result[] = [
                'id' => $salesOrder->id,
                'order_number' => $salesOrder->order_number,
                'order_date' => optional($salesOrder->order_date)?->format('d/m/Y'),
                'status' => $salesOrder->status,
                'total_amount' => (float) $salesOrder->total_amount,
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
                'exchange_rate' => (float) $salesOrder->exchange_rate,
                'payment_method' => $salesOrder->payment_method,
                'company_bank_account_id' => $salesOrder->company_bank_account_id,
                'shipping_address_id' => $salesOrder->shipping_address_id,
                'invoice_address_id' => $salesOrder->invoice_address_id,
                'lines' => $lines,
                'costs' => $salesOrder->costs->map(fn ($cost) => [
                    'id' => $cost->id,
                    'sales_order_cost_id' => $cost->id,
                    'description' => $cost->description,
                    'cost_item_id' => $cost->cost_item_id,
                    'cost_item_name' => $cost->costItem?->name,
                    'amount' => (float) $cost->amount,
                    'currency_id' => $cost->currency_id,
                    'exchange_rate' => (float) $cost->exchange_rate,
                ])->values()->toArray(),
            ];
        }

        return $result;
    }

    private function availableSalesOrders(array $selectedIds = [], ?int $partnerId = null): array
    {
        if (! $partnerId) {
            return [];
        }

        $query = SalesOrder::query()
            ->with(['partner', 'branch.branchGroup.company', 'lines'])
            ->where('partner_id', $partnerId)
            ->whereIn('status', [
                SalesOrderStatus::PARTIALLY_DELIVERED->value,
                SalesOrderStatus::DELIVERED->value,
            ])
            ->orderByDesc('order_date')
            ->limit(50);

        $salesOrders = $query->get();

        // Include selected IDs even if not in query
        if (! empty($selectedIds)) {
            $existingIds = $salesOrders->pluck('id')->toArray();
            $missingIds = array_diff($selectedIds, $existingIds);
            if (! empty($missingIds)) {
                $additional = SalesOrder::with(['partner', 'branch.branchGroup.company', 'lines'])
                    ->whereIn('id', $missingIds)
                    ->get();
                $salesOrders = $salesOrders->merge($additional);
            }
        }

        return $salesOrders->map(function (SalesOrder $salesOrder) {
            return [
                'id' => $salesOrder->id,
                'order_number' => $salesOrder->order_number,
                'order_date' => optional($salesOrder->order_date)?->format('d/m/Y'),
                'total_amount' => (float) $salesOrder->total_amount,
            ];
        })->values()->toArray();
    }

    private function transformInvoiceListItem(SalesInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'customer_name' => $invoice->partner?->name,
            'branch_name' => $invoice->branch?->name,
            'sales_orders' => $invoice->salesOrders->map(fn ($so) => [
                'id' => $so->id,
                'order_number' => $so->order_number,
            ])->values(),
            'is_direct_invoice' => $invoice->salesOrders->isEmpty(),
            'subtotal' => (float) $invoice->subtotal,
            'tax_total' => (float) $invoice->tax_total,
            'total_amount' => (float) $invoice->total_amount,
            'currency_code' => $invoice->currency?->code,
        ];
    }

    private function transformInvoice(SalesInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer_invoice_number' => $invoice->customer_invoice_number,
            'tax_invoice_code' => $invoice->tax_invoice_code?->value,
            'tax_invoice_code_label' => $invoice->taxInvoiceCodeLabel(),
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'notes' => $invoice->notes,
            'exchange_rate' => (float) $invoice->exchange_rate,
            'subtotal' => (float) $invoice->subtotal,
            'tax_total' => (float) $invoice->tax_total,
            'total_amount' => (float) $invoice->total_amount,
            'delivery_value_base' => (float) $invoice->delivery_value_base,
            'revenue_variance' => (float) $invoice->revenue_variance,
            'partner' => $invoice->partner ? [
                'id' => $invoice->partner->id,
                'name' => $invoice->partner->name,
            ] : null,
            'currency' => $invoice->currency ? [
                'id' => $invoice->currency->id,
                'code' => $invoice->currency->code,
            ] : null,
            'sales_orders' => $invoice->salesOrders->map(fn ($so) => [
                'id' => $so->id,
                'order_number' => $so->order_number,
            ])->values(),
            'costs' => $invoice->costs->map(fn ($cost) => [
                'id' => $cost->id,
                'cost_item' => $cost->costItem,
                'description' => $cost->description,
                'amount' => $cost->amount,
            ])->values(),
            'is_direct_invoice' => $invoice->salesOrders->isEmpty(),
            'lines' => $invoice->lines->map(fn ($line) => [
                'id' => $line->id,
                'line_number' => $line->line_number,
                'description' => $line->description,
                'uom_label' => $line->uom_label,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'discount_rate' => (float) $line->discount_rate,
                'discount_amount' => (float) $line->discount_amount,
                'tax_rate' => (float) $line->tax_rate,
                'tax_amount' => (float) $line->tax_amount,
                'line_total' => (float) $line->line_total,
                'line_total_base' => (float) $line->line_total_base,
                'delivery_number' => $line->salesDeliveryLine?->salesDelivery?->delivery_number,
                'so_order_number' => $line->salesOrderLine?->salesOrder?->order_number ?? null,
            ])->values(),
        ];
    }

    private function transformInvoiceForEdit(SalesInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'customer_invoice_number' => $invoice->customer_invoice_number,
            'tax_invoice_code' => $invoice->tax_invoice_code?->value,
            'exchange_rate' => (float) $invoice->exchange_rate,
            'notes' => $invoice->notes,
            'company_id' => $invoice->company_id,
            'branch_id' => $invoice->branch_id,
            'partner_id' => $invoice->partner_id,
            'currency_id' => $invoice->currency_id,
            'partner' => $invoice->partner ? [
                'id' => $invoice->partner->id,
                'name' => $invoice->partner->name,
            ] : null,
            'lines' => $invoice->lines->map(fn ($line) => [
                'id' => $line->id,
                'sales_order_line_id' => $line->sales_order_line_id,
                'sales_delivery_line_id' => $line->sales_delivery_line_id,
                'product_id' => $line->product_id,
                'product_variant_id' => $line->product_variant_id,
                'description' => $line->description,
                'uom_label' => $line->uom_label,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'discount_rate' => (float) $line->discount_rate,
                'discount_amount' => (float) $line->discount_amount,
                'tax_rate' => (float) $line->tax_rate,
                'tax_amount' => (float) $line->tax_amount,
                'delivery_number' => $line->salesDeliveryLine?->salesDelivery?->delivery_number,
            ])->values(),
        ];
    }

    private function getFilteredInvoices(array $filters)
    {
        $query = SalesInvoice::with([
            'partner',
            'salesOrders',
            'branch.branchGroup.company',
            'currency',
        ]);

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(invoice_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(customer_invoice_number) like ?', ["%{$search}%"]);
            });
        }

        if (! empty($filters['company_id'])) {
            $query->whereHas('branch.branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', (array) $filters['company_id']);
            });
        }

        if (! empty($filters['branch_id'])) {
            $query->whereIn('branch_id', (array) $filters['branch_id']);
        }

        if (! empty($filters['partner_id'])) {
            $query->whereIn('partner_id', (array) $filters['partner_id']);
        }

        return $query->orderBy('invoice_date', 'desc')->get();
    }

    private function statusOptions(): array
    {
        $options = [];
        foreach (InvoiceStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    private function companyOptions(): array
    {
        return Company::orderBy('name')
            ->get()
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])
            ->toArray();
    }

    private function branchOptions(): array
    {
        return Branch::with('branchGroup.company')
            ->orderBy('name')
            ->get()
            ->map(fn ($b) => [
                'value' => $b->id,
                'label' => $b->name,
                'company_id' => $b->branchGroup?->company_id,
            ])
            ->toArray();
    }

    private function customerOptions(): array
    {
        return Partner::whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])
            ->toArray();
    }

    private function productOptions(): array
    {
        return Product::with(['variants.uom:id,code,name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'variants' => $product->variants->map(fn ($variant) => [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'barcode' => $variant->barcode,
                        'uom_id' => $variant->uom_id,
                        'uom' => [
                            'id' => $variant->uom?->id,
                            'code' => $variant->uom?->code,
                            'name' => $variant->uom?->name,
                        ],
                    ]),
                ];
            })
            ->toArray();
    }

    private function uomOptions(): array
    {
        return Uom::orderBy('code')->get(['id', 'code', 'name'])->toArray();
    }

    private function userOptions(): array
    {
        return User::orderBy('name')
            ->get(['global_id', 'name', 'email'])
            ->map(fn (User $user) => [
                'value' => $user->global_id,
                'label' => $user->name,
                'email' => $user->email,
            ])
            ->toArray();
    }

    public function print(SalesInvoice $salesInvoice, DocumentTemplateService $templateService): Response
    {
        $salesInvoice->load([
            'partner',
            'salesOrders',
            'currency',
            'branch.branchGroup.company',
            'lines',
            'creator',
        ]);

        // Resolve template for this company or fallback to default
        $companyId = $salesInvoice->company_id ?? $salesInvoice->branch?->branchGroup?->company_id;
        $template = DocumentTemplate::resolveTemplate($companyId, 'sales_invoice');

        $renderedContent = null;
        if ($template) {
            $renderedContent = $templateService->renderTemplate($template, $salesInvoice);
        }

        return Inertia::render('SalesInvoices/Print', [
            'salesInvoice' => [
                'id' => $salesInvoice->id,
                'invoice_number' => $salesInvoice->invoice_number,
                'invoice_date' => $salesInvoice->invoice_date?->format('Y-m-d'),
                'due_date' => $salesInvoice->due_date?->format('Y-m-d'),
                'status' => $salesInvoice->status,
                'subtotal' => (float) $salesInvoice->subtotal,
                'tax_total' => (float) $salesInvoice->tax_total,
                'total_amount' => (float) $salesInvoice->total_amount,
                'exchange_rate' => (float) $salesInvoice->exchange_rate,
                'notes' => $salesInvoice->notes,
                'is_direct_invoice' => $salesInvoice->isDirectInvoice(),
                'partner' => $salesInvoice->partner ? [
                    'id' => $salesInvoice->partner->id,
                    'name' => $salesInvoice->partner->name,
                    'code' => $salesInvoice->partner->code,
                    'address' => $salesInvoice->partner->address,
                ] : null,
                'currency' => $salesInvoice->currency ? [
                    'id' => $salesInvoice->currency->id,
                    'code' => $salesInvoice->currency->code,
                ] : null,
                'branch' => $salesInvoice->branch ? [
                    'id' => $salesInvoice->branch->id,
                    'name' => $salesInvoice->branch->name,
                    'branch_group' => $salesInvoice->branch->branchGroup ? [
                        'company' => $salesInvoice->branch->branchGroup->company ? [
                            'name' => $salesInvoice->branch->branchGroup->company->name,
                        ] : null,
                    ] : null,
                ] : null,
                'company' => $salesInvoice->branch?->branchGroup?->company ? [
                    'name' => $salesInvoice->branch->branchGroup->company->name,
                    'address' => $salesInvoice->branch->branchGroup->company->address,
                    'phone' => $salesInvoice->branch->branchGroup->company->phone,
                ] : null,
                'sales_orders' => $salesInvoice->salesOrders->map(fn ($so) => [
                    'id' => $so->id,
                    'order_number' => $so->order_number,
                ])->toArray(),
                'lines' => $salesInvoice->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'line_number' => $line->line_number,
                    'description' => $line->description,
                    'uom_label' => $line->uom_label,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'discount_rate' => (float) $line->discount_rate,
                    'discount_amount' => (float) $line->discount_amount,
                    'tax_rate' => (float) $line->tax_rate,
                    'tax_amount' => (float) $line->tax_amount,
                    'line_total' => (float) $line->line_total,
                ])->toArray(),
                'creator' => $salesInvoice->creator ? [
                    'name' => $salesInvoice->creator->name,
                ] : null,
            ],
            'template' => $template,
            'renderedContent' => $renderedContent,
        ]);
    }

    private function authorizeDraft(SalesInvoice $invoice): void
    {
        abort_if(
            $invoice->status !== InvoiceStatus::DRAFT->value,
            400,
            'Faktur ini sudah diposting.'
        );
    }

    private function roundQuantity(float $value): float
    {
        return round($value, 3);
    }
}
