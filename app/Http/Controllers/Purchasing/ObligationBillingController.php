<?php

namespace App\Http\Controllers\Purchasing;

use App\Exceptions\ObligationBillingException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Partner;
use App\Services\Purchasing\ObligationBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ObligationBillingController extends Controller
{
    public function __construct(
        private readonly ObligationBillingService $service,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = $request->integer('company_id') ?: null;
        $partnerId = $request->integer('partner_id') ?: null;

        $outstanding = [];
        if ($companyId && $partnerId) {
            $outstanding = $this->service->outstandingObligations($companyId, $partnerId);
        }

        return Inertia::render('Purchasing/ObligationBilling/Index', [
            'filters' => [
                'company_id' => $companyId,
                'partner_id' => $partnerId,
            ],
            'companies' => Company::orderBy('name')->get(['id', 'name'])->toArray(),
            'suppliers' => Partner::query()
                ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Partner $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                ])
                ->toArray(),
            'branches' => $companyId
                ? Branch::with('branchGroup:id,company_id')
                    ->whereHas('branchGroup', fn ($q) => $q->where('company_id', $companyId))
                    ->orderBy('name')
                    ->get(['id', 'name', 'branch_group_id'])
                    ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
                    ->toArray()
                : [],
            'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name'])->toArray(),
            'outstanding' => $outstanding,
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'partner_id' => ['required', 'exists:partners,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'vendor_invoice_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'booking_line_ids' => ['required', 'array', 'min:1'],
            'booking_line_ids.*' => ['integer', 'exists:booking_lines,id'],
        ]);

        try {
            $invoice = $this->service->generatePurchaseInvoice(
                header: [
                    'company_id' => (int) $data['company_id'],
                    'branch_id' => (int) $data['branch_id'],
                    'partner_id' => (int) $data['partner_id'],
                    'currency_id' => (int) $data['currency_id'],
                    'invoice_date' => $data['invoice_date'],
                    'due_date' => $data['due_date'] ?? null,
                    'exchange_rate' => (float) ($data['exchange_rate'] ?? 1),
                    'vendor_invoice_number' => $data['vendor_invoice_number'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
                bookingLineIds: array_map('intval', $data['booking_line_ids']),
            );
        } catch (ObligationBillingException $e) {
            return Redirect::back()->withInput()->with('error', $e->getMessage());
        }

        return Redirect::route('purchase-invoices.edit', $invoice->id)
            ->with('success', 'PI draft berhasil dibuat dari tagihan supplier. Tinjau dan post bila sudah sesuai.');
    }
}
