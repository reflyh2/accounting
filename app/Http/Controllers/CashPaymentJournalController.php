<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Currency;
use Illuminate\Http\Request;
use App\Exports\CashPaymentJournalsExport;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class CashPaymentJournalController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('cash_payment_journals.index_filters', []);
        Session::put('cash_payment_journals.index_filters', $filters);

        $query = Journal::with(['branch', 'journalEntries.account'])
            ->withSum('journalEntries', 'primary_currency_credit')
            ->where('journal_type', 'cash_payment');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(journal_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(reference_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('branch', function ($query) use ($filters) {
                $query->whereHas('branchGroup', function ($query) use ($filters) {
                    $query->whereIn('company_id', $filters['company_id']);
                });
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $cashPaymentJournals = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('CashPaymentJournals/Index', [
            'cashPaymentJournals' => $cashPaymentJournals,
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('cash_payment_journals.index_filters', []);
        
        return Inertia::render('CashPaymentJournals/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'accounts' => fn() => Account::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->where('is_parent', false)->with('currencies.companyRates')->orderBy('code', 'asc')->get(),
            'kasBankAccounts' => fn() => Account::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->where('is_parent', false)->where('type', 'kas_bank')->with('currencies.companyRates')->orderBy('code', 'asc')->get(),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'kas_bank_account_id' => 'required|exists:accounts,id',
            'kas_bank_account_currency_id' => 'required|exists:currencies,id',
            'kas_bank_account_exchange_rate' => 'required|numeric|min:0',
            'entries' => 'required|array|min:1',
            'entries.*.account_id' => 'required|exists:accounts,id',
            'entries.*.debit' => 'required|numeric|min:0',
            'entries.*.currency_id' => 'required|exists:currencies,id',
            'entries.*.exchange_rate' => 'required|numeric|min:0',
        ]);

        $cashPaymentJournal = DB::transaction(function () use ($validated, $request) {
            $cashPaymentJournal = Journal::create([
                'branch_id' => $validated['branch_id'],
                'journal_type' => 'cash_payment',
                'user_global_id' => $request->user()->global_id,
                'date' => $validated['date'],
                'reference_number' => $validated['reference_number'],
                'description' => $validated['description'],
            ]);

            $totalAmount = 0;

            foreach ($validated['entries'] as $entry) {
                $amount = $entry['debit'] * $entry['exchange_rate'];
                $totalAmount += $amount;

                $cashPaymentJournal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'currency_id' => $entry['currency_id'],
                    'exchange_rate' => $entry['exchange_rate'],
                    'primary_currency_debit' => $amount,
                ]);
            }

            // Create the credit entry for kas_bank account
            $cashPaymentJournal->journalEntries()->create([
                'account_id' => $validated['kas_bank_account_id'],
                'credit' => $validated['kas_bank_account_exchange_rate'] > 0 ? $totalAmount / $validated['kas_bank_account_exchange_rate'] : 0,
                'currency_id' => $validated['kas_bank_account_currency_id'],
                'exchange_rate' => $validated['kas_bank_account_exchange_rate'],
                'primary_currency_credit' => $totalAmount,
            ]);

            return $cashPaymentJournal;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('cash-payment-journals.create')
                ->with('success', 'Pengeluaran Kas berhasil dibuat. Silakan buat penerimaan kas lainnya.');
        }

        return redirect()->route('cash-payment-journals.show', $cashPaymentJournal->id)
            ->with('success', 'Pengeluaran Kas berhasil dibuat.');
    }

    public function show(Request $request, $journalId)
    {
        $journal = Journal::find($journalId);
        $filters = Session::get('cash_payment_journals.index_filters', []);
        $journal->load(['branch', 'journalEntries.account', 'journalEntries.currency']);
        
        return Inertia::render('CashPaymentJournals/Show', [
            'cashPaymentJournal' => $journal,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, $journalId)
    {
        $journal = Journal::find($journalId);
        $filters = Session::get('cash_payment_journals.index_filters', []);
        $journal->load(['branch.branchGroup', 'journalEntries.account', 'journalEntries.currency']);
        
        $companyId = $journal->branch->branchGroup->company_id;

        return Inertia::render('CashPaymentJournals/Edit', [
            'cashPaymentJournal' => $journal,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'accounts' => Account::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->where('is_parent', false)->with('currencies.companyRates')->orderBy('code', 'asc')->get(),
            'kasBankAccounts' => Account::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->where('is_parent', false)->where('type', 'kas_bank')->with('currencies.companyRates')->orderBy('code', 'asc')->get(),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
        ]);
    }

    public function update(Request $request, $journalId)
    {
        $journal = Journal::find($journalId);

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'kas_bank_account_id' => 'required|exists:accounts,id',
            'kas_bank_account_currency_id' => 'required|exists:currencies,id',
            'kas_bank_account_exchange_rate' => 'required|numeric|min:0',
            'entries' => 'required|array|min:1',
            'entries.*.id' => 'nullable|exists:journal_entries,id',
            'entries.*.account_id' => 'required|exists:accounts,id',
            'entries.*.debit' => 'required|numeric|min:0',
            'entries.*.currency_id' => 'required|exists:currencies,id',
            'entries.*.exchange_rate' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $journal) {
            $journal->update([
                'branch_id' => $validated['branch_id'],
                'date' => $validated['date'],
                'reference_number' => $validated['reference_number'],
                'description' => $validated['description'],
            ]);

            foreach ($journal->journalEntries as $entry) {
                $entry->delete();
            }

            $totalAmount = 0;

            foreach ($validated['entries'] as $entry) {
                $amount = $entry['debit'] * $entry['exchange_rate'];
                $totalAmount += $amount;

                $journal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'currency_id' => $entry['currency_id'],
                    'exchange_rate' => $entry['exchange_rate'],
                    'primary_currency_debit' => $amount,
                ]);
            }

            // Create the credit entry for kas_bank account
            $journal->journalEntries()->create([
                'account_id' => $validated['kas_bank_account_id'],
                'credit' => $validated['kas_bank_account_exchange_rate'] > 0 ? $totalAmount / $validated['kas_bank_account_exchange_rate'] : 0,
                'currency_id' => $validated['kas_bank_account_currency_id'],
                'exchange_rate' => $validated['kas_bank_account_exchange_rate'],
                'primary_currency_credit' => $totalAmount,
            ]);
        });

        return redirect()->route('cash-payment-journals.edit', $journal->id)
            ->with('success', 'Pengeluaran Kas berhasil diubah.');
    }

    public function destroy(Request $request, $journalId)
    {
        $journal = Journal::find($journalId);
        DB::transaction(function () use ($journal) {
            foreach ($journal->journalEntries as $entry) {
                $entry->delete();
            }
            $journal->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('cash-payment-journals.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pengeluaran Kas berhasil dihapus.');
        } else {
            return Redirect::route('cash-payment-journals.index')
                ->with('success', 'Pengeluaran Kas berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $journal = Journal::find($id);
                foreach ($journal->journalEntries as $entry) {
                    $entry->delete();
                }
                $journal->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('cash-payment-journals.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pengeluaran Kas berhasil dihapus.');
        } else {
            return Redirect::route('cash-payment-journals.index')
                ->with('success', 'Pengeluaran Kas berhasil dihapus.');
        }
    }

    private function getFilteredCashPaymentJournals(Request $request)
    {
        $filters = $request->all() ?: Session::get('cash_payment_journals.index_filters', []);

        $query = Journal::with(['branch', 'journalEntries.account'])
            ->withSum('journalEntries', 'primary_currency_debit')
            ->where('journal_type', 'cash_payment');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(journal_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(reference_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('branch', function ($query) use ($filters) {
                $query->whereHas('branchGroup', function ($query) use ($filters) {
                    $query->whereIn('company_id', $filters['company_id']);
                });
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $journals = $this->getFilteredCashPaymentJournals($request);
        return Excel::download(new CashPaymentJournalsExport($journals), 'cash_payment_journals.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $journals = $this->getFilteredCashPaymentJournals($request);
        return Excel::download(new CashPaymentJournalsExport($journals), 'cash_payment_journals.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $journals = $this->getFilteredCashPaymentJournals($request);
        return Excel::download(new CashPaymentJournalsExport($journals), 'cash_payment_journals.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public function print($journalId)
    {
        $journal = Journal::find($journalId);
        $journal->load(['user', 'branch.branchGroup.company', 'journalEntries.account', 'journalEntries.currency']); 
        return Inertia::render('CashPaymentJournals/Print', [ 'cashPaymentJournal' => $journal, ]);
    }
}