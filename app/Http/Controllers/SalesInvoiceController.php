<?php

namespace App\Http\Controllers;

use App\Enums\Documents\InvoiceStatus;
use App\Exports\SalesInvoicesExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\SalesDeliveryLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class SalesInvoiceController extends Controller
{
    private const QTY_TOLERANCE = 0.0005;

    public function __construct(
        private readonly SalesInvoiceService $invoiceService,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('sales_invoices.index_filters', []);
        Session::put('sales_invoices.index_filters', $filters);

        $query = SalesInvoice::with([
            'partner',
            'salesOrder',
            'currency',
            'branch.branchGroup.company',
        ]);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(invoice_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(customer_invoice_number) like ?', ["%{$search}%"])
                    ->orWhereHas('salesOrder', function ($so) use ($search) {
                        $so->whereRaw('lower(order_number) like ?', ["%{$search}%"]);
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

    public function create(Request $request)
    {
        $filters = Session::get('sales_invoices.index_filters', []);
        [$salesOrder, $defaultLines, $salesOrderLabel] = $this->buildSalesOrderPayload(
            $request->integer('sales_order_id')
        );

        return Inertia::render('SalesInvoices/Create', [
            'filters' => $filters,
            'salesOrder' => $salesOrder ? fn () => $salesOrder : null,
            'defaultLines' => fn () => $defaultLines,
            'selectedSalesOrderLabel' => $salesOrderLabel,
            'salesOrderSearchUrl' => route('api.sales-invoices.sales-orders'),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPayload($request);

        $invoice = $this->invoiceService->create($validated);

        if ($request->boolean('create_another', false)) {
            return Redirect::route('sales-invoices.create', [
                'sales_order_id' => $invoice->sales_order_id,
            ])->with('success', 'Faktur penjualan berhasil dibuat.');
        }

        return Redirect::route('sales-invoices.show', $invoice)
            ->with('success', 'Faktur penjualan berhasil dibuat.');
    }

    public function show(SalesInvoice $salesInvoice)
    {
        $salesInvoice->load([
            'salesOrder.partner',
            'salesOrder.branch',
            'lines.salesOrderLine',
            'lines.salesDeliveryLine.salesDelivery',
            'currency',
        ]);

        $filters = Session::get('sales_invoices.index_filters', []);

        return Inertia::render('SalesInvoices/Show', [
            'invoice' => $salesInvoice,
            'filters' => $filters,
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
            'statusOptions' => $this->statusOptions(),
            'canPost' => $salesInvoice->status === InvoiceStatus::DRAFT->value,
            'canEdit' => $salesInvoice->status === InvoiceStatus::DRAFT->value,
            'canDelete' => $salesInvoice->status === InvoiceStatus::DRAFT->value,
        ]);
    }

    public function edit(Request $request, SalesInvoice $salesInvoice)
    {
        $this->authorizeDraft($salesInvoice);

        $salesInvoice->load([
            'salesOrder.partner',
            'lines.salesOrderLine',
            'lines.salesDeliveryLine.salesDelivery',
            'currency',
        ]);

        [$salesOrder, $defaultLines, $salesOrderLabel] = $this->buildSalesOrderPayload(
            $salesInvoice->sales_order_id
        );

        return Inertia::render('SalesInvoices/Edit', [
            'invoice' => $salesInvoice,
            'filters' => Session::get('sales_invoices.index_filters', []),
            'salesOrder' => $salesOrder ? fn () => $salesOrder : null,
            'defaultLines' => fn () => $defaultLines,
            'selectedSalesOrderLabel' => $salesOrderLabel,
            'salesOrderSearchUrl' => route('api.sales-invoices.sales-orders'),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
        ]);
    }

    public function update(Request $request, SalesInvoice $salesInvoice)
    {
        $this->authorizeDraft($salesInvoice);

        $validated = $this->validatedPayload($request);

        $invoice = $this->invoiceService->update($salesInvoice, $validated);

        return Redirect::route('sales-invoices.show', $invoice)
            ->with('success', 'Faktur penjualan berhasil diperbarui.');
    }

    public function destroy(SalesInvoice $salesInvoice)
    {
        $this->authorizeDraft($salesInvoice);

        $this->invoiceService->delete($salesInvoice);

        return Redirect::route('sales-invoices.index')
            ->with('success', 'Faktur penjualan berhasil dihapus.');
    }

    public function post(Request $request, SalesInvoice $salesInvoice)
    {
        $this->authorizeDraft($salesInvoice);

        $this->invoiceService->post($salesInvoice);

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
        $validated = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'customer_invoice_number' => 'nullable|string|max:120',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.sales_order_line_id' => 'required|exists:sales_order_lines,id',
            'lines.*.sales_delivery_line_id' => 'required|exists:sales_delivery_lines,id',
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

    private function buildSalesOrderPayload(?int $salesOrderId): array
    {
        if (!$salesOrderId) {
            return [null, [], null];
        }

        $salesOrder = SalesOrder::with([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'lines.uom',
            'lines.baseUom',
        ])->find($salesOrderId);

        if (!$salesOrder) {
            return [null, [], null];
        }

        $deliveryLines = SalesDeliveryLine::with(['salesDelivery'])
            ->whereHas('salesDelivery', function ($q) use ($salesOrderId) {
                $q->where('sales_order_id', $salesOrderId);
            })
            ->orderBy('id')
            ->get();

        $defaultLines = [];

        foreach ($deliveryLines as $line) {
            $soLine = $salesOrder->lines->firstWhere('id', $line->sales_order_line_id);
            if (!$soLine) {
                continue;
            }

            $remainingDelivery = max(
                0.0,
                (float) $line->quantity
                    - (float) $line->quantity_invoiced
            );

            $remainingSo = max(
                0.0,
                ((float) $soLine->quantity_delivered)
                    - (float) $soLine->quantity_invoiced
            );

            $available = min($remainingDelivery, $remainingSo);

            if ($available <= self::QTY_TOLERANCE) {
                continue;
            }

            $defaultLines[] = [
                'sales_order_line_id' => $soLine->id,
                'sales_delivery_line_id' => $line->id,
                'delivery_number' => $line->salesDelivery?->delivery_number,
                'delivery_date' => optional($line->salesDelivery?->delivery_date)->format('d/m/Y'),
                'description' => $soLine->description,
                'uom_label' => $soLine->uom?->name,
                'quantity' => $this->roundQuantity($available),
                'available_quantity' => $this->roundQuantity($available),
                'max_quantity' => $this->roundQuantity($available),
                'ordered_quantity' => $this->roundQuantity((float) $soLine->quantity),
                'delivered_quantity' => $this->roundQuantity((float) $soLine->quantity_delivered),
                'invoiced_quantity' => $this->roundQuantity((float) $soLine->quantity_invoiced),
                'unit_price' => (float) $soLine->unit_price,
            ];
        }

        return [
            $salesOrder,
            array_values($defaultLines),
            sprintf('%s — %s', $salesOrder->order_number, optional($salesOrder->partner)->name),
        ];
    }

    private function getFilteredInvoices(array $filters)
    {
        $query = SalesInvoice::with([
            'partner',
            'salesOrder',
            'branch.branchGroup.company',
            'currency',
        ]);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(invoice_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(customer_invoice_number) like ?', ["%{$search}%"]);
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
