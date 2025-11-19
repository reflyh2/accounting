<?php

namespace App\Http\Controllers;

use App\Models\ExternalDebt;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Partner;
use App\Models\Currency;
use App\Models\Account;
use App\Events\Debt\ExternalDebtCreated;
use App\Events\Debt\ExternalDebtUpdated;
use App\Events\Debt\ExternalDebtDeleted;
use App\Enums\DebtStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class ExternalReceivableController extends Controller
{
    protected string $debtType = 'receivable';

    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('external_receivables.index_filters', []);
        Session::put('external_receivables.index_filters', $filters);

        $query = ExternalDebt::with(['branch.branchGroup.company', 'currency', 'partner'])
            ->where('type', $this->debtType);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(number)'), 'like', "%$search%")
                  ->orWhere(DB::raw('lower(notes)'), 'like', "%$search%")
                  ->orWhereHas('externalDebt.partner', function ($qp) use ($search) {
                      $qp->where(DB::raw('lower(name)'), 'like', "%$search%");
                  })
                  ->orWhereHas('branch', function ($qb) use ($search) {
                      $qb->where(DB::raw('lower(name)'), 'like', "%$search%");
                  });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('branch', function ($q) use ($filters) {
                $q->whereHas('branchGroup', function ($qq) use ($filters) {
                    $qq->whereIn('company_id', $filters['company_id']);
                });
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['partner_id'])) {
            $query->whereHas('externalDebt', function ($q) use ($filters) {
                $q->whereIn('partner_id', $filters['partner_id']);
            });
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('issue_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('issue_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'issue_date';
        $sortOrder = $filters['order'] ?? 'desc';

        if ($sortColumn === 'partner.name') {
            $query->join('external_debts', 'debts.id', '=', 'external_debts.debt_id')
                  ->join('partners', 'external_debts.partner_id', '=', 'partners.id')
                  ->orderBy('partners.name', $sortOrder)
                  ->select('debts.*');
        } elseif ($sortColumn === 'branch.name') {
            $query->join('branches', 'debts.branch_id', '=', 'branches.id')
                  ->orderBy('branches.name', $sortOrder)
                  ->select('debts.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $items = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        $partners = Partner::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', $filters['company_id']);
            })->orderBy('name', 'asc')->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('Debts/ExternalReceivables/Index', [
            'items' => $items,
            'companies' => $companies,
            'branches' => $branches,
            'partners' => $partners,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'statusOptions' => $this->statusOptions(),
            'statusStyles' => ExternalDebt::statusStyles(),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('external_receivables.index_filters', []);

        $companies = Company::orderBy('name', 'asc')->get();
        $primaryCurrency = Currency::where('is_primary', true)->first();

        $branches = collect();
        $partners = collect();
        $currencies = collect();
        $accounts = collect();
        $defaultDebtAccountId = null;

        if ($request->company_id) {
            $companyId = $request->company_id;

            $branches = Branch::whereHas('branchGroup', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get();

            $partners = Partner::orderBy('name', 'asc')->get();

            $currencies = Currency::whereHas('companyRates', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->with(['companyRates' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }])->orderBy('code', 'asc')->get();
            $accounts = Account::where('is_parent', false)->orderBy('code', 'asc')->get();
            $company = Company::find($companyId);
            $defaultDebtAccountId = $company?->default_receivable_account_id;
        }

        return Inertia::render('Debts/ExternalReceivables/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'branches' => fn() => $branches,
            'partners' => fn() => $partners,
            'currencies' => fn() => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'accounts' => fn() => $accounts,
            'defaultDebtAccountId' => $defaultDebtAccountId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'partner_id' => 'required|exists:partners,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'amount' => 'required|numeric|min:0',
            'offset_account_id' => 'nullable|exists:accounts,id',
            'debt_account_id' => 'nullable|exists:accounts,id',
            'notes' => 'nullable|string',
        ]);

        $debt = DB::transaction(function () use ($validated) {
            $companyId = Branch::with('branchGroup')->find($validated['branch_id'])->branchGroup->company_id;
            $company = Company::find($companyId);
            $defaultDebtAccountId = $company?->default_receivable_account_id;
            $debt = ExternalDebt::create([
                'type' => $this->debtType,
                'branch_id' => $validated['branch_id'],
                'partner_id' => $validated['partner_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'] ?? null,
                'amount' => $validated['amount'],
                'primary_currency_amount' => $validated['amount'] * $validated['exchange_rate'],
                'status' => 'open',
                'notes' => $validated['notes'] ?? null,
                'offset_account_id' => $validated['offset_account_id'] ?? null,
                'debt_account_id' => $validated['debt_account_id'] ?? $defaultDebtAccountId,
                'created_by' => auth()->user()->global_id,
            ]);

            ExternalDebtCreated::dispatch($debt);
            return $debt;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('external-receivables.create')
                ->with('success', 'Piutang eksternal berhasil dibuat.');
        }

        return redirect()->route('external-receivables.show', $debt->id)
            ->with('success', 'Piutang eksternal berhasil dibuat.');
    }

    public function show(Request $request, ExternalDebt $debt)
    {
        $filters = Session::get('external_receivables.index_filters', []);
        $debt->load(['branch.branchGroup.company', 'currency', 'partner', 'creator', 'updater']);

        return Inertia::render('Debts/ExternalReceivables/Show', [
            'item' => $debt,
            'filters' => $filters,
            'statusStyles' => ExternalDebt::statusStyles(),
        ]);
    }

    public function edit(Request $request, ExternalDebt $debt)
    {
        $filters = Session::get('external_receivables.index_filters', []);
        $debt->load(['branch.branchGroup', 'currency', 'partner']);

        $companyId = $debt->branch->branchGroup->company_id;
        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        $primaryCurrency = Currency::where('is_primary', true)->first();
        $currencies = Currency::whereHas('companyRates', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->with(['companyRates' => function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        }])->orderBy('code', 'asc')->get();

        return Inertia::render('Debts/ExternalReceivables/Edit', [
            'item' => $debt,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'partners' => Partner::orderBy('name', 'asc')->get(),
            'currencies' => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'accounts' => Account::where('is_parent', false)->orderBy('code', 'asc')->get(),
            'defaultDebtAccountId' => Company::find($companyId)?->default_receivable_account_id,
        ]);
    }

    public function update(Request $request, ExternalDebt $debt)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'partner_id' => 'required|exists:partners,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'amount' => 'required|numeric|min:0',
            'offset_account_id' => 'nullable|exists:accounts,id',
            'debt_account_id' => 'nullable|exists:accounts,id',
            'notes' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        if ($debt->branch_id != $validated['branch_id']) {
            return redirect()->back()->with('error', 'Cabang tidak dapat diubah.');
        }

        DB::transaction(function () use ($validated, $debt) {
            $debt->update([
                'currency_id' => $validated['currency_id'],
                'partner_id' => $validated['partner_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'] ?? null,
                'amount' => $validated['amount'],
                'primary_currency_amount' => $validated['amount'] * $validated['exchange_rate'],
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? $debt->status,
                'offset_account_id' => $validated['offset_account_id'] ?? null,
                'debt_account_id' => $validated['debt_account_id'] ?? $debt->debt_account_id,
                'updated_by' => auth()->user()->global_id,
            ]);

            ExternalDebtUpdated::dispatch($debt);
        });

        return redirect()->route('external-receivables.show', $debt->id)
            ->with('success', 'Piutang eksternal berhasil diubah.');
    }

    public function destroy(Request $request, ExternalDebt $debt)
    {
        DB::transaction(function () use ($debt) {
            ExternalDebtDeleted::dispatch($debt);
            $debt->delete();
        });

        return redirect()->route('external-receivables.index')
            ->with('success', 'Piutang eksternal berhasil dihapus.');
    }

    protected function statusOptions(): array
    {
        return DebtStatus::options();
    }
}


