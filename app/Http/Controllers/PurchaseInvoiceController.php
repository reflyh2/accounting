<?php

namespace App\Http\Controllers;

use App\Enums\Documents\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exports\PurchaseInvoicesExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\GoodsReceiptLine;
use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Services\Purchasing\PurchaseInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly PurchaseInvoiceService $invoiceService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('purchase_invoices.index_filters', []);
        Session::put('purchase_invoices.index_filters', $filters);

        $query = PurchaseInvoice::with([
            'partner',
            'purchaseOrders',
            'currency',
            'branch.branchGroup.company',
        ]);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(invoice_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(vendor_invoice_number) like ?', ["%{$search}%"])
                    ->orWhereHas('purchaseOrders', function ($po) use ($search) {
                        $po->whereRaw('lower(order_number) like ?', ["%{$search}%"]);
                    });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', (array) $filters['branch_id']);
        }

        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', (array) $filters['partner_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
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

        return Inertia::render('PurchaseInvoices/Index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'suppliers' => $this->supplierOptions(),
            'statusOptions' => $this->statusOptions(),
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request): Response
    {
        $selectedPartnerId = $request->integer('partner_id') ?: null;
        $selectedIds = $request->input('purchase_order_ids', []);
        if (!is_array($selectedIds)) {
            $selectedIds = $selectedIds ? [$selectedIds] : [];
        }
        $selectedIds = array_filter(array_map('intval', $selectedIds));

        $selectedPurchaseOrders = [];
        $partnerBankAccounts = [];

        if (!empty($selectedIds)) {
            $selectedPurchaseOrders = $this->purchaseOrdersDetail($selectedIds);
            if (!empty($selectedPurchaseOrders) && !$selectedPartnerId) {
                $selectedPartnerId = $selectedPurchaseOrders[0]['partner']['id'] ?? null;
            }
        }

        if ($selectedPartnerId) {
            $partnerBankAccounts = Partner::find($selectedPartnerId)?->bankAccounts()->where('is_active', true)->get() ?? [];
        }

        $formOptions = $this->formOptions();

        return Inertia::render('PurchaseInvoices/Create', [
            'purchaseOrders' => $this->availablePurchaseOrders($selectedIds, $selectedPartnerId),
            'selectedPurchaseOrders' => $selectedPurchaseOrders,
            'selectedPartnerId' => $selectedPartnerId,
            'suppliers' => $this->supplierOptions(),
            'paymentMethods' => $this->paymentMethodOptions(),
            'partnerBankAccounts' => $partnerBankAccounts,
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
            'products' => $formOptions['products'],
            'uoms' => $formOptions['uoms'],
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'currencies' => Currency::select('id', 'code', 'name')->get()->map(fn($c) => ['value' => $c->id, 'label' => $c->code . ' - ' . $c->name]),
            'filters' => Session::get('purchase_invoices.index_filters', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request);

        $invoice = $this->invoiceService->create($validated, $request->user());

        if ($request->boolean('create_another', false)) {
            return Redirect::route('purchase-invoices.create')
                ->with('success', 'Faktur pembelian berhasil dibuat.');
        }

        return Redirect::route('purchase-invoices.show', $invoice)
            ->with('success', 'Faktur pembelian berhasil dibuat.');
    }

    public function show(PurchaseInvoice $purchaseInvoice): Response
    {
        $purchaseInvoice->load([
            'partner',
            'branch',
            'purchaseOrders.partner',
            'purchaseOrders.branch.branchGroup.company',
            'lines.purchaseOrderLine',
            'lines.goodsReceiptLine.goodsReceipt',
            'lines.productVariant.product',
            'lines.uom',
            'currency',
            'bankAccount',
            'inventoryTransaction',
        ]);

        return Inertia::render('PurchaseInvoices/Show', [
            'invoice' => $purchaseInvoice,
            'filters' => Session::get('purchase_invoices.index_filters', []),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
            'statusOptions' => $this->statusOptions(),
            'paymentMethods' => $this->paymentMethodOptions(),
            'canPost' => $purchaseInvoice->status === InvoiceStatus::DRAFT->value,
            'canEdit' => $purchaseInvoice->status === InvoiceStatus::DRAFT->value,
            'canDelete' => $purchaseInvoice->status === InvoiceStatus::DRAFT->value,
        ]);
    }

    public function edit(PurchaseInvoice $purchaseInvoice): Response
    {
        $this->authorizeDraft($purchaseInvoice);

        $purchaseInvoice->load([
            'purchaseOrders.partner',
            'purchaseOrders.lines.variant.product',
            'purchaseOrders.lines.uom',
            'lines.purchaseOrderLine',
            'lines.goodsReceiptLine.goodsReceipt',
            'currency',
        ]);

        $selectedPartnerId = $purchaseInvoice->partner_id;
        $selectedIds = $purchaseInvoice->purchaseOrders->pluck('id')->toArray();
        $selectedPurchaseOrders = $this->purchaseOrdersDetail($selectedIds);
        
        $partnerBankAccounts = Partner::find($selectedPartnerId)?->bankAccounts()->where('is_active', true)->get() ?? [];
        $formOptions = $this->formOptions();

        return Inertia::render('PurchaseInvoices/Edit', [
            'invoice' => $purchaseInvoice,
            'filters' => Session::get('purchase_invoices.index_filters', []),
            'purchaseOrders' => $this->availablePurchaseOrders($selectedIds, $selectedPartnerId),
            'selectedPurchaseOrders' => $selectedPurchaseOrders,
            'selectedPartnerId' => $selectedPartnerId,
            'suppliers' => $this->supplierOptions(),
            'paymentMethods' => $this->paymentMethodOptions(),
            'partnerBankAccounts' => $partnerBankAccounts,
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
            'products' => $formOptions['products'],
            'uoms' => $formOptions['uoms'],
            'companies' => $this->companyOptions(),
            'branches' => $this->branchOptions(),
            'currencies' => Currency::select('id', 'code', 'name')->get()->map(fn($c) => ['value' => $c->id, 'label' => $c->code . ' - ' . $c->name]),
        ]);
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->authorizeDraft($purchaseInvoice);

        $validated = $this->validatedPayload($request);

        $invoice = $this->invoiceService->update($purchaseInvoice, $validated, $request->user());

        return Redirect::route('purchase-invoices.show', $invoice)
            ->with('success', 'Faktur pembelian berhasil diperbarui.');
    }

    public function destroy(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->authorizeDraft($purchaseInvoice);

        $this->invoiceService->delete($purchaseInvoice);

        return Redirect::route('purchase-invoices.index')
            ->with('success', 'Faktur pembelian berhasil dihapus.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'exists:purchase_invoices,id'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
                foreach ($request->ids as $id) {
                    $invoice = PurchaseInvoice::find($id);
                    if ($invoice && $invoice->status === InvoiceStatus::DRAFT->value) {
                        $this->invoiceService->delete($invoice);
                    }
                }
            });
        } catch (\Exception $e) {
            return Redirect::back()->with('error', 'Terjadi kesalahan saat menghapus data.');
        }

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('purchase-invoices.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Faktur pembelian berhasil dihapus.');
        }

        return Redirect::route('purchase-invoices.index')
            ->with('success', 'Faktur pembelian berhasil dihapus.');
    }

    public function post(PurchaseInvoice $purchaseInvoice, Request $request): RedirectResponse
    {
        $this->authorizeDraft($purchaseInvoice);

        $this->invoiceService->post($purchaseInvoice, $request->user());

        return Redirect::route('purchase-invoices.show', $purchaseInvoice->id)
            ->with('success', 'Faktur pembelian berhasil diposting.');
    }

    /**
     * Display the print view for Invoice.
     */
    public function print(PurchaseInvoice $purchaseInvoice): Response
    {
        $purchaseInvoice->load([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'purchaseOrders',
            'lines.productVariant.product',
            'lines.uom',
            'creator:global_id,name',
        ]);

        return Inertia::render('PurchaseInvoices/Print', [
            'purchaseInvoice' => $purchaseInvoice,
        ]);
    }

    public function exportXLSX(Request $request)
    {
        $invoices = $this->getFilteredInvoices($request->all());
        return Excel::download(new PurchaseInvoicesExport($invoices), 'purchase-invoices.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $invoices = $this->getFilteredInvoices($request->all());
        return Excel::download(new PurchaseInvoicesExport($invoices), 'purchase-invoices.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $invoices = $this->getFilteredInvoices($request->all());
        return Excel::download(new PurchaseInvoicesExport($invoices), 'purchase-invoices.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    private function validatedPayload(Request $request): array
    {
        $isDirect = empty($request->input('purchase_order_ids'));

        $rules = [
            'partner_id' => 'required|exists:partners,id',
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'purchase_order_ids' => 'nullable|array',
            'purchase_order_ids.*' => 'exists:purchase_orders,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'vendor_invoice_number' => 'nullable|string|max:120',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'partner_bank_account_id' => 'nullable|exists:partner_bank_accounts,id',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'nullable|string',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
        ];

        if (!$isDirect) {
            $rules['lines.*.purchase_order_line_id'] = 'required|exists:purchase_order_lines,id';
            $rules['lines.*.goods_receipt_line_id'] = 'required|exists:goods_receipt_lines,id';
        } else {
            $rules['lines.*.product_variant_id'] = 'required|exists:product_variants,id';
            $rules['lines.*.uom_id'] = 'required|exists:uoms,id';
        }

        $validated = $request->validate($rules);

        $validated['lines'] = collect($validated['lines'])
            ->map(function ($line) {
                $line['tax_amount'] = $line['tax_amount'] ?? 0;
                return $line;
            })
            ->toArray();

        return $validated;
    }

    private function getFilteredInvoices(array $filters)
    {
        $query = PurchaseInvoice::with([
            'partner',
            'purchaseOrders',
            'branch.branchGroup.company',
            'currency',
        ]);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(invoice_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(vendor_invoice_number) like ?', ["%{$search}%"]);
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }
        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', (array) $filters['branch_id']);
        }
        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', (array) $filters['partner_id']);
        }

        return $query->orderBy('invoice_date', 'desc')->get();
    }

    private function statusOptions(): array
    {
        return collect(InvoiceStatus::cases())
            ->mapWithKeys(fn (InvoiceStatus $status) => [
                $status->value => $status->label(),
            ])
            ->toArray();
    }

    private function paymentMethodOptions(): array
    {
        return collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ])
            ->values()
            ->toArray();
    }
    
    private function companyOptions()
    {
        return Company::orderBy('name')->get()->map(fn($item) => [
            'value' => $item->id,
            'label' => $item->name,
        ])->values()->toArray();
    }

    private function branchOptions()
    {
        return Branch::with('branchGroup.company')
            ->orderBy('name')
            ->get()
            ->map(fn ($branch) => [
                'value' => $branch->id,
                'label' => $branch->name . ($branch->branchGroup?->company ? ' (' . $branch->branchGroup->company->name . ')' : ''),
            ])
            ->values()
            ->toArray();
    }

    private function supplierOptions(): array
    {
        return Partner::query()
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->orderBy('name')
            ->get()
            ->map(fn ($partner) => [
                'value' => $partner->id,
                'label' => $partner->name,
            ])
            ->values()
            ->toArray();
    }

    private function formOptions(): array
    {
        return [
            'products' => \App\Models\Product::with(['variants.uom:id,code,name', 'companies:id'])
                ->orderBy('name')
                ->get()
                ->map(function ($product) {
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
                            ],
                        ]),
                    ];
                }),
            'uoms' => \App\Models\Uom::orderBy('code')->get(['id', 'code', 'name']),
        ];
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

        // Get UOM conversion service for quantity conversion
        $uomConverter = app(\App\Services\Inventory\UomConversionService::class);

        $result = [];

        foreach ($purchaseOrders as $purchaseOrder) {
            // Need to fetch receipt lines availability for these POs
            // Load GRN line's UOM for conversion check
            $receiptLines = GoodsReceiptLine::with(['goodsReceipt', 'uom'])
                ->whereHas('goodsReceipt', function ($q) use ($purchaseOrder) {
                    $q->whereHas('purchaseOrders', function($q) use ($purchaseOrder) {
                        $q->where('purchase_orders.id', $purchaseOrder->id);
                    });
                })
                ->orderBy('id')
                ->get();

            $lines = [];
            foreach ($receiptLines as $line) {
                $poLine = $purchaseOrder->lines->firstWhere('id', $line->purchase_order_line_id);
                if (!$poLine) continue;

                // Calculate remaining quantities based on GRN line
                $remainingGrn = max(0.0, (float) $line->quantity - (float) $line->quantity_invoiced - (float) $line->quantity_returned);
                $remainingPo = max(0.0, ((float) $poLine->quantity_received - (float) $poLine->quantity_returned) - (float) $poLine->quantity_invoiced);
                $available = min($remainingGrn, $remainingPo);
                static $QTY_TOLERANCE = 0.0005;

                if ($available <= $QTY_TOLERANCE) continue;

                // Check if GRN line UOM differs from PO line UOM
                $grnUomId = (int) $line->uom_id;
                $poUomId = (int) $poLine->uom_id;
                $convertedAvailable = $available;

                if ($grnUomId !== $poUomId && $grnUomId && $poUomId) {
                    // Convert GRN quantity from GRN UOM to PO UOM
                    try {
                        $convertedAvailable = $uomConverter->convert($available, $grnUomId, $poUomId);
                    } catch (\Exception $e) {
                        // If conversion fails, use original quantity (this shouldn't happen for valid data)
                        $convertedAvailable = $available;
                    }
                }

                $lines[] = [
                    'purchase_order_line_id' => $poLine->id,
                    'goods_receipt_line_id' => $line->id,
                    'goods_receipt_number' => $line->goodsReceipt?->receipt_number,
                    'receipt_date' => optional($line->goodsReceipt?->receipt_date)->format('d/m/Y'),
                    'description' => $poLine->description,
                    'uom_label' => $poLine->uom?->name,
                    'product_variant_id' => $poLine->product_variant_id,
                    'uom_id' => $poLine->uom_id, // Always use PO's UOM for invoice
                    'available_quantity' => $convertedAvailable, // Converted to PO UOM
                    'quantity' => $convertedAvailable, // Default to available in PO UOM
                    'unit_price' => (float) $poLine->unit_price, // Use PO's unit price
                    'tax_rate' => $poLine->tax_rate,
                    'tax_amount' => $poLine->tax_amount,
                ];
            }

            if (empty($lines)) continue;

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



    private function availablePurchaseOrders(array $selectedIds = [], ?int $partnerId = null)
    {
        if (!$partnerId) return collect();

        $query = PurchaseOrder::query()
            ->with(['partner', 'branch.branchGroup.company', 'lines'])
            ->where('partner_id', $partnerId)
            ->whereIn('status', [
                PurchaseOrderStatus::SENT->value,
                PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
                PurchaseOrderStatus::RECEIVED->value,
            ])
            ->orderByDesc('order_date')
            ->limit(50);

        $purchaseOrders = $query->get();

        // Include selected IDs even if not in query
        if (!empty($selectedIds)) {
            $existingIds = $purchaseOrders->pluck('id')->toArray();
            $missingIds = array_diff($selectedIds, $existingIds);
            if (!empty($missingIds)) {
                $additional = PurchaseOrder::with(['partner', 'branch.branchGroup.company', 'lines'])
                    ->whereIn('id', $missingIds)
                    ->get();
                $purchaseOrders = $purchaseOrders->merge($additional);
            }
        }

        return $purchaseOrders->map(function (PurchaseOrder $purchaseOrder) {
             // Calculate approximate remaining quantity for display, or just show summary
             return [
                'id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'order_date' => optional($purchaseOrder->order_date)->format('d/m/Y'),
                'total_amount' => (float) $purchaseOrder->total_amount,
             ];
        });
    }

    private function authorizeDraft(PurchaseInvoice $invoice): void
    {
        abort_if(
            $invoice->status !== InvoiceStatus::DRAFT->value,
            400,
            'Faktur ini sudah diposting.'
        );
    }
}
