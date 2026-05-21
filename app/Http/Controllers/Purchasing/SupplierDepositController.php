<?php

namespace App\Http\Controllers\Purchasing;

use App\Exceptions\SupplierDepositException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\SupplierDeposit;
use App\Services\Purchasing\SupplierDepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class SupplierDepositController extends Controller
{
    public function __construct(
        private readonly SupplierDepositService $service,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('supplier_deposits.index_filters', []);
        Session::put('supplier_deposits.index_filters', $filters);

        $filters = Arr::only($filters, ['search', 'company_id', 'partner_id', 'status', 'per_page']);

        $query = SupplierDeposit::query()
            ->with([
                'partner:id,name,code',
                'company:id,name',
                'currency:id,code',
            ])
            ->when(! empty($filters['search']), fn ($q) => $q->where(function ($qq) use ($filters) {
                $search = strtolower($filters['search']);
                $qq->whereRaw('lower(deposit_number) like ?', ["%{$search}%"])
                    ->orWhereHas('partner', fn ($pq) => $pq->whereRaw('lower(name) like ?', ["%{$search}%"]));
            }))
            ->when(! empty($filters['company_id']), fn ($q) => $q->whereIn('company_id', (array) $filters['company_id']))
            ->when(! empty($filters['partner_id']), fn ($q) => $q->whereIn('partner_id', (array) $filters['partner_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->whereIn('status', (array) $filters['status']))
            ->orderByDesc('deposit_date');

        $perPage = (int) ($filters['per_page'] ?? 20);

        return Inertia::render('Purchasing/SupplierDeposits/Index', [
            'deposits' => $query->paginate($perPage)->withQueryString(),
            'filters' => $filters,
            'companies' => Company::orderBy('name')->get(['id', 'name'])->toArray(),
            'suppliers' => Partner::query()
                ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->toArray(),
            'statusOptions' => [
                ['value' => 'open', 'label' => 'Saldo Tersedia'],
                ['value' => 'exhausted', 'label' => 'Habis Dipakai'],
                ['value' => 'refunded', 'label' => 'Direfund'],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Purchasing/SupplierDeposits/Create', [
            'formOptions' => $this->formOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'partner_id' => ['required', 'exists:partners,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'deposit_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'company_bank_account_id' => ['nullable', 'exists:company_bank_accounts,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $deposit = $this->service->record([
                'company_id' => (int) $data['company_id'],
                'branch_id' => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
                'partner_id' => (int) $data['partner_id'],
                'currency_id' => (int) $data['currency_id'],
                'exchange_rate' => (float) ($data['exchange_rate'] ?? 1),
                'deposit_date' => $data['deposit_date'],
                'amount' => (float) $data['amount'],
                'payment_method' => $data['payment_method'] ?? null,
                'company_bank_account_id' => isset($data['company_bank_account_id']) ? (int) $data['company_bank_account_id'] : null,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (SupplierDepositException $e) {
            return Redirect::back()->withInput()->with('error', $e->getMessage());
        }

        return Redirect::route('supplier-deposits.show', $deposit->id)
            ->with('success', 'Deposit pemasok berhasil dicatat.');
    }

    public function show(SupplierDeposit $supplierDeposit): Response
    {
        $supplierDeposit->load([
            'partner',
            'company',
            'branch',
            'currency',
            'advanceAccount:id,code,name',
            'paymentAccount:id,code,name',
            'companyBankAccount',
            'consumptions',
        ]);

        return Inertia::render('Purchasing/SupplierDeposits/Show', [
            'deposit' => $supplierDeposit,
            'filters' => Session::get('supplier_deposits.index_filters', []),
        ]);
    }

    public function refund(Request $request, SupplierDeposit $supplierDeposit): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        try {
            $this->service->refund(
                $supplierDeposit,
                isset($data['amount']) ? (float) $data['amount'] : null,
            );
        } catch (SupplierDepositException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }

        return Redirect::route('supplier-deposits.show', $supplierDeposit->id)
            ->with('success', 'Refund berhasil dicatat.');
    }

    private function formOptions(): array
    {
        return [
            'companies' => Company::orderBy('name')->get(['id', 'name'])->toArray(),
            'branches' => Branch::with('branchGroup:id,company_id')
                ->orderBy('name')
                ->get(['id', 'name', 'branch_group_id'])
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'company_id' => $b->branchGroup?->company_id,
                ])->toArray(),
            'suppliers' => Partner::query()
                ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
                ->with('companies:id')
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Partner $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'company_ids' => $p->companies->pluck('id')->all(),
                ])->toArray(),
            'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name'])->toArray(),
            'paymentMethods' => collect(\App\Enums\PaymentMethod::cases())
                ->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])
                ->toArray(),
            'companyBankAccounts' => CompanyBankAccount::query()
                ->where('is_active', true)
                ->orderBy('bank_name')
                ->get(['id', 'company_id', 'bank_name', 'account_number', 'account_holder_name'])
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'company_id' => $b->company_id,
                    'label' => "{$b->bank_name} - {$b->account_number} ({$b->account_holder_name})",
                ])->toArray(),
        ];
    }
}
