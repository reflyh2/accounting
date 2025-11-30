<?php

namespace App\Http\Controllers;

use App\Exports\InternalDebtsExport;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InternalDebt;
use App\Enums\DebtStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class InternalDebtController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('internal_debts.index_filters', []);
        Session::put('internal_debts.index_filters', $filters);

        $query = InternalDebt::with([
            'branch.branchGroup.company',
            'counterpartyBranch.branchGroup.company',
            'currency',
            'creator',
            'updater',
        ]);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('branch', function ($q) use ($filters) {
                $q->whereHas('branchGroup', function ($sq) use ($filters) {
                    $sq->whereIn('company_id', $filters['company_id']);
                });
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['counterparty_company_id'])) {
            $query->whereHas('counterpartyBranch', function ($q) use ($filters) {
                $q->whereHas('branchGroup', function ($sq) use ($filters) {
                    $sq->whereIn('company_id', $filters['counterparty_company_id']);
                });
            });
        }

        if (!empty($filters['counterparty_branch_id'])) {
            $query->whereIn('counterparty_branch_id', $filters['counterparty_branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
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

        if ($sortColumn === 'branch.name') {
            $query->join('branches', 'internal_debts.branch_id', '=', 'branches.id')
                  ->orderBy('branches.name', $sortOrder)
                  ->select('internal_debts.*');
        } elseif ($sortColumn === 'counterparty_branch.name') {
            $query->join('branches as cpb', 'internal_debts.counterparty_branch_id', '=', 'cpb.id')
                  ->orderBy('cpb.name', $sortOrder)
                  ->select('internal_debts.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $debts = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        $branches = !empty($filters['company_id'])
            ? Branch::whereHas('branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', $filters['company_id']);
            })->orderBy('name', 'asc')->get()
            : Branch::orderBy('name', 'asc')->get();

        $counterpartyBranches = !empty($filters['counterparty_company_id'])
            ? Branch::whereHas('branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', $filters['counterparty_company_id']);
            })->orderBy('name', 'asc')->get()
            : Branch::orderBy('name', 'asc')->get();

        return Inertia::render('InternalDebts/Index', [
            'debts' => $debts,
            'companies' => $companies,
            'branches' => $branches,
            'counterpartyBranches' => $counterpartyBranches,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'statusOptions' => InternalDebt::statusOptions(),
            'statusStyles' => InternalDebt::statusStyles(),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('internal_debts.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();
        $primaryCurrency = Currency::where('is_primary', true)->first();

        $companyId = $request->company_id;
        $counterpartyCompanyId = $request->counterparty_company_id;

        $branches = collect();
        $counterpartyBranches = collect();
        $currencies = collect();
        $accounts = collect();
        $counterPartyAccounts = collect();

        if ($companyId) {
            $branches = Branch::whereHas('branchGroup', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get();

            $currencies = Currency::whereHas('companyRates', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->with(['companyRates' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }])->orderBy('code', 'asc')->get();

            // Load selectable accounts similar to External Payables
            $accounts =  Account::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->where('is_parent', false)->with('currencies.companyRates')->orderBy('code', 'asc')->get();
        }

        if ($counterpartyCompanyId) {
            $counterpartyBranches = Branch::whereHas('branchGroup', function ($q) use ($counterpartyCompanyId) {
                $q->where('company_id', $counterpartyCompanyId);
            })->orderBy('name', 'asc')->get();
            $counterPartyAccounts =  Account::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('counterparty_company_id'));
            })->where('is_parent', false)->with('currencies.companyRates')->orderBy('code', 'asc')->get();
        }

        return Inertia::render('InternalDebts/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'branches' => fn() => $branches,
            'counterpartyBranches' => fn() => $counterpartyBranches,
            'currencies' => fn() => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'accounts' => fn() => $accounts,
            'counterPartyAccounts' => fn() => $counterPartyAccounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'counterparty_company_id' => 'required|exists:companies,id',
            'counterparty_branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'amount' => 'required|numeric|min:0.01',
            'offset_account_id' => 'required|exists:accounts,id',
            'debt_account_id' => 'required|exists:accounts,id',
            'counterparty_offset_account_id' => 'required|exists:accounts,id',
            'counterparty_debt_account_id' => 'required|exists:accounts,id',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string',
        ]);

        $primaryCurrency = Currency::where('is_primary', true)->first();
        $primaryAmount = $validated['amount'];
        if ((int) $validated['currency_id'] !== (int) optional($primaryCurrency)->id) {
            $primaryAmount = $validated['amount'] * $validated['exchange_rate'];
        }

        $debt = DB::transaction(function () use ($validated, $primaryAmount) {
            return InternalDebt::create([
                'type' => 'payable', // creator is borrower
                'branch_id' => $validated['branch_id'],
                'counterparty_company_id' => $validated['counterparty_company_id'],
                'counterparty_branch_id' => $validated['counterparty_branch_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'amount' => $validated['amount'],
                'primary_currency_amount' => $primaryAmount,
                'offset_account_id' => $validated['offset_account_id'],
                'debt_account_id' => $validated['debt_account_id'],
                'counterparty_offset_account_id' => $validated['counterparty_offset_account_id'],
                'counterparty_debt_account_id' => $validated['counterparty_debt_account_id'],
                'notes' => $validated['notes'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'status' => 'pending',
                'created_by' => auth()->user()->global_id,
            ]);
        });

        return redirect()->route('internal-debts.show', $debt->id)
            ->with('success', 'Hutang Internal berhasil dibuat dan menunggu persetujuan.');
    }

    public function show(Request $request, InternalDebt $internalDebt)
    {
        $filters = Session::get('internal_debts.index_filters', []);
        $internalDebt->load(['branch.branchGroup.company', 'counterpartyBranch.branchGroup.company', 'currency', 'creator', 'updater', 'debtAccount', 'offsetAccount', 'counterpartyDebtAccount', 'counterpartyOffsetAccount']);

        return Inertia::render('InternalDebts/Show', [
            'debt' => $internalDebt,
            'filters' => $filters,
            'statusOptions' => InternalDebt::statusOptions(),
            'statusStyles' => InternalDebt::statusStyles(),
        ]);
    }

    public function edit(Request $request, InternalDebt $internalDebt)
    {
        $filters = Session::get('internal_debts.index_filters', []);
        $internalDebt->load(['branch.branchGroup.company', 'counterpartyBranch.branchGroup.company', 'currency']);

        if ($internalDebt->status !== 'pending') {
            return redirect()->route('internal-debts.show', $internalDebt->id)
                ->with('error', 'Hanya hutang dengan status Menunggu Persetujuan yang dapat diubah.');
        }

        $companyId = $internalDebt->branch->branchGroup->company_id;
        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        $counterpartyCompanyId = $internalDebt->counterpartyBranch->branchGroup->company_id;
        if ($request->counterparty_company_id) {
            $counterpartyCompanyId = $request->counterparty_company_id;
        }

        $primaryCurrency = Currency::where('is_primary', true)->first();
        $currencies = Currency::whereHas('companyRates', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->with(['companyRates' => function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        }])->orderBy('code', 'asc')->get();

        return Inertia::render('InternalDebts/Edit', [
            'debt' => $internalDebt,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'counterpartyBranches' => Branch::whereHas('branchGroup', function ($q) use ($counterpartyCompanyId) {
                $q->where('company_id', $counterpartyCompanyId);
            })->orderBy('name', 'asc')->get(),
            'currencies' => $currencies,
            'primaryCurrency' => $primaryCurrency,
            // Load selectable accounts similar to External Payables
            'accounts' =>  Account::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->where('is_parent', false)->with('currencies.companyRates')->orderBy('code', 'asc')->get(),
            'counterPartyAccounts' => Account::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('counterparty_company_id'));
            })->where('is_parent', false)->with('currencies.companyRates')->orderBy('code', 'asc')->get(),
        ]);
    }

    public function update(Request $request, InternalDebt $internalDebt)
    {
        if ($internalDebt->status !== 'pending') {
            return redirect()->back()->with('error', 'Hanya hutang dengan status Menunggu Persetujuan yang dapat diubah.');
        }

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'counterparty_company_id' => 'required|exists:companies,id',
            'counterparty_branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'amount' => 'required|numeric|min:0.01',
            'offset_account_id' => 'required|exists:accounts,id',
            'debt_account_id' => 'required|exists:accounts,id',
            'counterparty_offset_account_id' => 'required|exists:accounts,id',
            'counterparty_debt_account_id' => 'required|exists:accounts,id',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string',
        ]);

        if ($internalDebt->branch_id != $validated['branch_id']) {
            return redirect()->back()->with('error', 'Cabang peminjam tidak dapat diubah.');
        }

        $primaryCurrency = Currency::where('is_primary', true)->first();
        $primaryAmount = $validated['amount'];
        if ((int) $validated['currency_id'] !== (int) optional($primaryCurrency)->id) {
            $primaryAmount = $validated['amount'] * $validated['exchange_rate'];
        }

        DB::transaction(function () use ($validated, $internalDebt, $primaryAmount) {
            $internalDebt->update([
                'counterparty_company_id' => $validated['counterparty_company_id'],
                'counterparty_branch_id' => $validated['counterparty_branch_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'amount' => $validated['amount'],
                'primary_currency_amount' => $primaryAmount,
                'offset_account_id' => $validated['offset_account_id'],
                'debt_account_id' => $validated['debt_account_id'],
                'counterparty_offset_account_id' => $validated['counterparty_offset_account_id'],
                'counterparty_debt_account_id' => $validated['counterparty_debt_account_id'],
                'notes' => $validated['notes'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'updated_by' => auth()->user()->global_id,
            ]);
        });

        return redirect()->route('internal-debts.show', $internalDebt->id)
            ->with('success', 'Hutang Internal berhasil diubah.');
    }

    public function destroy(Request $request, InternalDebt $internalDebt)
    {
        DB::transaction(function () use ($internalDebt) {
            $internalDebt->update(['updated_by' => auth()->user()->global_id]);
            \App\Events\Debt\InternalDebtDeleted::dispatch($internalDebt);
            $internalDebt->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('internal-debts.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Hutang Internal berhasil dihapus.');
        } else {
            return Redirect::route('internal-debts.index')
                ->with('success', 'Hutang Internal berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:internal_debts,id',
        ]);

        DB::transaction(function () use ($validated) {
            $debts = InternalDebt::whereIn('id', $validated['ids'])->get();
            foreach ($debts as $debt) {
                if ($debt->status === DebtStatus::APPROVED) {
                    return Redirect::back()->with('error', 'Tidak dapat menghapus hutang yang sudah disetujui.');
                }
            }

            foreach ($debts as $debt) {
                InternalDebtDeleted::dispatch($debt);
                $debt->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('internal-debts.index') . ($currentQuery ? '?' . $currentQuery : '');
            return Redirect::to($redirectUrl)
                ->with('success', 'Hutang Internal terpilih berhasil dihapus.');
        }
        return redirect()->route('internal-debts.index')->with('success', 'Hutang Internal terpilih berhasil dihapus.');
    }

    public function approve(InternalDebt $internalDebt)
    {
        if ($internalDebt->status !== DebtStatus::PENDING) {
            return back()->with('error', 'Hanya hutang dengan status Menunggu Persetujuan yang dapat disetujui.');
        }
        $internalDebt->update(['status' => DebtStatus::OPEN]);
        \App\Events\Debt\InternalDebtApproved::dispatch($internalDebt->fresh());
        return back()->with('success', 'Permintaan hutang disetujui.');
    }

    public function reject(InternalDebt $internalDebt)
    {
        if ($internalDebt->status !== DebtStatus::PENDING) {
            return back()->with('error', 'Hanya hutang dengan status Menunggu Persetujuan yang dapat ditolak.');
        }
        $internalDebt->update(['status' => DebtStatus::CANCELLED]);
        return back()->with('success', 'Permintaan hutang ditolak.');
    }

    private function getFilteredDebts(Request $request)
    {
        $filters = $request->all() ?: Session::get('internal_debts.index_filters', []);
        $query = InternalDebt::with(['branch.branchGroup.company', 'counterpartyBranch.branchGroup.company', 'currency'])
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($sq) use ($filters) {
                    $sq->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                      ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%');
                });
            })
            ->when(!empty($filters['company_id']), function ($q) use ($filters) {
                $q->whereHas('branch', function ($bq) use ($filters) {
                    $bq->whereHas('branchGroup', function ($gq) use ($filters) {
                        $gq->whereIn('company_id', $filters['company_id']);
                    });
                });
            })
            ->when(!empty($filters['branch_id']), function ($q) use ($filters) {
                $q->whereIn('branch_id', $filters['branch_id']);
            })
            ->when(!empty($filters['counterparty_company_id']), function ($q) use ($filters) {
                $q->whereHas('counterpartyBranch', function ($bq) use ($filters) {
                    $bq->whereHas('branchGroup', function ($gq) use ($filters) {
                        $gq->whereIn('company_id', $filters['counterparty_company_id']);
                    });
                });
            })
            ->when(!empty($filters['counterparty_branch_id']), function ($q) use ($filters) {
                $q->whereIn('counterparty_branch_id', $filters['counterparty_branch_id']);
            })
            ->when(!empty($filters['status']), function ($q) use ($filters) {
                $q->whereIn('status', (array) $filters['status']);
            })
            ->when(!empty($filters['from_date']), function ($q) use ($filters) {
                $q->whereDate('issue_date', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($q) use ($filters) {
                $q->whereDate('issue_date', '<=', $filters['to_date']);
            })
            ->orderBy($filters['sort'] ?? 'issue_date', $filters['order'] ?? 'desc');

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $debts = $this->getFilteredDebts($request);
        return Excel::download(new InternalDebtsExport($debts), 'internal-debts.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $debts = $this->getFilteredDebts($request);
        return Excel::download(new InternalDebtsExport($debts), 'internal-debts.csv', \Maatwebsite\Excel\Excel::CSV);
    }
}


