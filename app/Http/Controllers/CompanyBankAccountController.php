<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class CompanyBankAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('company_bank_accounts.index_filters', []);
        Session::put('company_bank_accounts.index_filters', $filters);

        $query = CompanyBankAccount::with(['company', 'account', 'currency']);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(bank_name) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(account_number) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(account_holder_name) like ?', ["%{$search}%"]);
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $bankAccounts = $query->orderBy('bank_name')->paginate(15)->withQueryString();

        return Inertia::render('CompanyBankAccounts/Index', [
            'bankAccounts' => $bankAccounts,
            'filters' => $filters,
            'companies' => $this->companyOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CompanyBankAccounts/Create', [
            'companies' => $this->companyOptions(),
            'accounts' => $this->accountOptions(),
            'currencies' => $this->currencyOptions(),
            'filters' => Session::get('company_bank_accounts.index_filters', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'account_id' => 'nullable|exists:accounts,id',
            'currency_id' => 'nullable|exists:currencies,id',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
            'account_holder_name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:20',
            'iban' => 'nullable|string|max:50',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $validated['is_primary'] = $validated['is_primary'] ?? false;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $bankAccount = CompanyBankAccount::create($validated);

        return Redirect::route('company-bank-accounts.show', $bankAccount->id)
            ->with('success', 'Rekening bank perusahaan berhasil ditambahkan.');
    }

    public function show(CompanyBankAccount $companyBankAccount): Response
    {
        $companyBankAccount->load(['company', 'account', 'currency']);

        return Inertia::render('CompanyBankAccounts/Show', [
            'bankAccount' => [
                'id' => $companyBankAccount->id,
                'bank_name' => $companyBankAccount->bank_name,
                'account_number' => $companyBankAccount->account_number,
                'account_holder_name' => $companyBankAccount->account_holder_name,
                'branch_name' => $companyBankAccount->branch_name,
                'swift_code' => $companyBankAccount->swift_code,
                'iban' => $companyBankAccount->iban,
                'is_primary' => $companyBankAccount->is_primary,
                'is_active' => $companyBankAccount->is_active,
                'notes' => $companyBankAccount->notes,
                'company' => $companyBankAccount->company ? [
                    'id' => $companyBankAccount->company->id,
                    'name' => $companyBankAccount->company->name,
                ] : null,
                'account' => $companyBankAccount->account ? [
                    'id' => $companyBankAccount->account->id,
                    'code' => $companyBankAccount->account->code,
                    'name' => $companyBankAccount->account->name,
                ] : null,
                'currency' => $companyBankAccount->currency ? [
                    'id' => $companyBankAccount->currency->id,
                    'code' => $companyBankAccount->currency->code,
                ] : null,
            ],
            'filters' => Session::get('company_bank_accounts.index_filters', []),
        ]);
    }

    public function edit(CompanyBankAccount $companyBankAccount): Response
    {
        $companyBankAccount->load(['company', 'account', 'currency']);

        return Inertia::render('CompanyBankAccounts/Edit', [
            'bankAccount' => $companyBankAccount,
            'companies' => $this->companyOptions(),
            'accounts' => $this->accountOptions(),
            'currencies' => $this->currencyOptions(),
            'filters' => Session::get('company_bank_accounts.index_filters', []),
        ]);
    }

    public function update(Request $request, CompanyBankAccount $companyBankAccount): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'account_id' => 'nullable|exists:accounts,id',
            'currency_id' => 'nullable|exists:currencies,id',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
            'account_holder_name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:20',
            'iban' => 'nullable|string|max:50',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $validated['is_primary'] = $validated['is_primary'] ?? false;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $companyBankAccount->update($validated);

        return Redirect::route('company-bank-accounts.show', $companyBankAccount->id)
            ->with('success', 'Rekening bank perusahaan berhasil diperbarui.');
    }

    public function destroy(CompanyBankAccount $companyBankAccount): RedirectResponse
    {
        $companyBankAccount->delete();

        return Redirect::route('company-bank-accounts.index')
            ->with('success', 'Rekening bank perusahaan berhasil dihapus.');
    }

    private function companyOptions(): array
    {
        return Company::orderBy('name')
            ->get()
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])
            ->toArray();
    }

    private function accountOptions(): array
    {
        return Account::orderBy('code')
            ->get()
            ->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->code} - {$a->name}"])
            ->toArray();
    }

    private function currencyOptions(): array
    {
        return Currency::orderBy('code')
            ->get()
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->code])
            ->toArray();
    }
}
