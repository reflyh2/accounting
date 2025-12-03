<?php

namespace App\Http\Controllers;

use App\Enums\Documents\InvoiceStatus;
use App\Exports\PurchaseInvoicesExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\GoodsReceiptLine;
use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseInvoiceController extends Controller
{
    private const QTY_TOLERANCE = 0.0005;

    public function __construct(
        private readonly PurchaseInvoiceService $invoiceService,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('purchase_invoices.index_filters', []);
        Session::put('purchase_invoices.index_filters', $filters);

        $query = PurchaseInvoice::with([
            'partner',
            'purchaseOrder',
            'currency',
            'branch.branchGroup.company',
        ]);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(invoice_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(vendor_invoice_number) like ?', ["%{$search}%"])
                    ->orWhereHas('purchaseOrder', function ($po) use ($search) {
                        $po->whereRaw('lower(order_number) like ?', ["%{$search}%"]);
                    });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('branch.branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', (array) $filters['company_id']);
            });
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

        $companies = Company::orderBy('name', 'asc')->get();
        $partners = Partner::orderBy('name', 'asc')->get();
        $branches = !empty($filters['company_id'])
            ? Branch::whereHas('branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', (array) $filters['company_id']);
            })->orderBy('name', 'asc')->get()
            : Branch::orderBy('name', 'asc')->get();

        return Inertia::render('PurchaseInvoices/Index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'companies' => $companies,
            'branches' => $branches,
            'suppliers' => $partners,
            'statusOptions' => $this->statusOptions(),
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('purchase_invoices.index_filters', []);
        [$purchaseOrder, $defaultLines, $purchaseOrderLabel] = $this->buildPurchaseOrderPayload(
            $request->integer('purchase_order_id')
        );

        return Inertia::render('PurchaseInvoices/Create', [
            'filters' => $filters,
            'purchaseOrder' => $purchaseOrder ? fn () => $purchaseOrder : null,
            'defaultLines' => fn () => $defaultLines,
            'selectedPurchaseOrderLabel' => $purchaseOrderLabel,
            'purchaseOrderSearchUrl' => route('api.purchase-invoices.purchase-orders'),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPayload($request);

        $invoice = $this->invoiceService->create($validated);

        if ($request->boolean('create_another', false)) {
            return Redirect::route('purchase-invoices.create', [
                'purchase_order_id' => $invoice->purchase_order_id,
            ])->with('success', 'Faktur pembelian berhasil dibuat.');
        }

        return Redirect::route('purchase-invoices.show', $invoice)
            ->with('success', 'Faktur pembelian berhasil dibuat.');
    }

    public function show(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->load([
            'purchaseOrder.partner',
            'purchaseOrder.branch',
            'lines.purchaseOrderLine',
            'lines.goodsReceiptLine.goodsReceipt',
            'currency',
        ]);

        $filters = Session::get('purchase_invoices.index_filters', []);

        return Inertia::render('PurchaseInvoices/Show', [
            'invoice' => $purchaseInvoice,
            'filters' => $filters,
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
            'statusOptions' => $this->statusOptions(),
            'canPost' => $purchaseInvoice->status === InvoiceStatus::DRAFT->value,
            'canEdit' => $purchaseInvoice->status === InvoiceStatus::DRAFT->value,
            'canDelete' => $purchaseInvoice->status === InvoiceStatus::DRAFT->value,
        ]);
    }

    public function edit(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $this->authorizeDraft($purchaseInvoice);

        $purchaseInvoice->load([
            'purchaseOrder.partner',
            'lines.purchaseOrderLine',
            'lines.goodsReceiptLine.goodsReceipt',
            'currency',
        ]);

        [$purchaseOrder, $defaultLines, $purchaseOrderLabel] = $this->buildPurchaseOrderPayload(
            $purchaseInvoice->purchase_order_id
        );

        return Inertia::render('PurchaseInvoices/Edit', [
            'invoice' => $purchaseInvoice,
            'filters' => Session::get('purchase_invoices.index_filters', []),
            'purchaseOrder' => $purchaseOrder ? fn () => $purchaseOrder : null,
            'defaultLines' => fn () => $defaultLines,
            'selectedPurchaseOrderLabel' => $purchaseOrderLabel,
            'purchaseOrderSearchUrl' => route('api.purchase-invoices.purchase-orders'),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
        ]);
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $this->authorizeDraft($purchaseInvoice);

        $validated = $this->validatedPayload($request);

        $invoice = $this->invoiceService->update($purchaseInvoice, $validated);

        return Redirect::route('purchase-invoices.show', $invoice)
            ->with('success', 'Faktur pembelian berhasil diperbarui.');
    }

    public function destroy(PurchaseInvoice $purchaseInvoice)
    {
        $this->authorizeDraft($purchaseInvoice);

        $this->invoiceService->delete($purchaseInvoice);

        return Redirect::route('purchase-invoices.index')
            ->with('success', 'Faktur pembelian berhasil dihapus.');
    }

    public function post(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $this->authorizeDraft($purchaseInvoice);

        $this->invoiceService->post($purchaseInvoice);

        return Redirect::route('purchase-invoices.show', $purchaseInvoice->id)
            ->with('success', 'Faktur pembelian berhasil diposting.');
    }

    public function exportXLSX(Request $request)
    {
        $filters = $request->all();
        $invoices = $this->getFilteredInvoices($filters);

        return Excel::download(new PurchaseInvoicesExport($invoices), 'purchase-invoices.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $filters = $request->all();
        $invoices = $this->getFilteredInvoices($filters);

        return Excel::download(
            new PurchaseInvoicesExport($invoices),
            'purchase-invoices.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function exportPDF(Request $request)
    {
        $filters = $request->all();
        $invoices = $this->getFilteredInvoices($filters);

        return Excel::download(
            new PurchaseInvoicesExport($invoices),
            'purchase-invoices.pdf',
            \Maatwebsite\Excel\Excel::MPDF
        );
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'vendor_invoice_number' => 'nullable|string|max:120',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.purchase_order_line_id' => 'required|exists:purchase_order_lines,id',
            'lines.*.goods_receipt_line_id' => 'required|exists:goods_receipt_lines,id',
            'lines.*.description' => 'nullable|string',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.tax_amount' => 'nullable|numeric|min:0',
        ]);

        $validated['lines'] = collect($validated['lines'])
            ->map(function ($line) {
                $line['tax_amount'] = $line['tax_amount'] ?? 0;
                return $line;
            })
            ->toArray();

        return $validated;
    }

    private function buildPurchaseOrderPayload(?int $purchaseOrderId): array
    {
        if (!$purchaseOrderId) {
            return [null, [], null];
        }

        $purchaseOrder = PurchaseOrder::with([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.uom',
            'lines.baseUom',
        ])->find($purchaseOrderId);

        if (!$purchaseOrder) {
            return [null, [], null];
        }

        $receiptLines = GoodsReceiptLine::with(['goodsReceipt'])
            ->whereHas('goodsReceipt', function ($q) use ($purchaseOrderId) {
                $q->where('purchase_order_id', $purchaseOrderId);
            })
            ->orderBy('id')
            ->get();

        $defaultLines = [];

        foreach ($receiptLines as $line) {
            $poLine = $purchaseOrder->lines->firstWhere('id', $line->purchase_order_line_id);
            if (!$poLine) {
                continue;
            }

            $remainingGrn = max(
                0.0,
                (float) $line->quantity
                    - (float) $line->quantity_invoiced
                    - (float) $line->quantity_returned
            );

            $remainingPo = max(
                0.0,
                ((float) $poLine->quantity_received - (float) $poLine->quantity_returned)
                    - (float) $poLine->quantity_invoiced
            );

            $available = min($remainingGrn, $remainingPo);

            if ($available <= self::QTY_TOLERANCE) {
                continue;
            }

            $defaultLines[] = [
                'purchase_order_line_id' => $poLine->id,
                'goods_receipt_line_id' => $line->id,
                'goods_receipt_number' => $line->goodsReceipt?->receipt_number,
                'receipt_date' => optional($line->goodsReceipt?->receipt_date)->format('d/m/Y'),
                'description' => $poLine->description,
                'uom_label' => $poLine->uom?->name,
                'quantity' => $this->roundQuantity($available),
                'available_quantity' => $this->roundQuantity($available),
                'max_quantity' => $this->roundQuantity($available),
                'ordered_quantity' => $this->roundQuantity((float) $poLine->quantity),
                'received_quantity' => $this->roundQuantity((float) $poLine->quantity_received),
                'invoiced_quantity' => $this->roundQuantity((float) $poLine->quantity_invoiced),
                'unit_price' => (float) $poLine->unit_price,
            ];
        }

        return [
            $purchaseOrder,
            array_values($defaultLines),
            sprintf('%s — %s', $purchaseOrder->order_number, optional($purchaseOrder->partner)->name),
        ];
    }

    private function getFilteredInvoices(array $filters)
    {
        $query = PurchaseInvoice::with([
            'partner',
            'purchaseOrder',
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
            $query->whereHas('branch.branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', (array) $filters['company_id']);
            });
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
        $options = [];
        foreach (InvoiceStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    private function authorizeDraft(PurchaseInvoice $invoice): void
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

