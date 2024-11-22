<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Currency;
use Illuminate\Http\Request;
use App\Exports\CashReceiptJournalsExport;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class CashReceiptJournalController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('cash_receipt_journals.index_filters', []);
        Session::put('cash_receipt_journals.index_filters', $filters);

        $query = Journal::with(['branch', 'journalEntries.account'])
            ->withSum('journalEntries', 'primary_currency_debit')
            ->where('journal_type', 'cash_receipt');

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

        $cashReceiptJournals = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('CashReceiptJournals/Index', [
            'cashReceiptJournals' => $cashReceiptJournals,
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
        $filters = Session::get('cash_receipt_journals.index_filters', []);
        
        return Inertia::render('CashReceiptJournals/Create', [
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
            'entries.*.credit' => 'required|numeric|min:0',
            'entries.*.currency_id' => 'required|exists:currencies,id',
            'entries.*.exchange_rate' => 'required|numeric|min:0',
        ]);

        $cashReceiptJournal = DB::transaction(function () use ($validated, $request) {
            $cashReceiptJournal = Journal::create([
                'branch_id' => $validated['branch_id'],
                'journal_type' => 'cash_receipt',
                'user_global_id' => $request->user()->global_id,
                'date' => $validated['date'],
                'reference_number' => $validated['reference_number'],
                'description' => $validated['description'],
            ]);

            $totalAmount = 0;

            foreach ($validated['entries'] as $entry) {
                $amount = $entry['credit'] * $entry['exchange_rate'];
                $totalAmount += $amount;

                $cashReceiptJournal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'credit' => $entry['credit'],
                    'currency_id' => $entry['currency_id'],
                    'exchange_rate' => $entry['exchange_rate'],
                    'primary_currency_credit' => $amount,
                ]);
            }

            // Create the debit entry for kas_bank account
            $cashReceiptJournal->journalEntries()->create([
                'account_id' => $validated['kas_bank_account_id'],
                'debit' => $validated['kas_bank_account_exchange_rate'] > 0 ? $totalAmount / $validated['kas_bank_account_exchange_rate'] : 0,
                'currency_id' => $validated['kas_bank_account_currency_id'],
                'exchange_rate' => $validated['kas_bank_account_exchange_rate'],
                'primary_currency_debit' => $totalAmount,
            ]);

            return $cashReceiptJournal;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('cash-receipt-journals.create')
                ->with('success', 'Penerimaan Kas berhasil dibuat. Silakan buat penerimaan kas lainnya.');
        }

        return redirect()->route('cash-receipt-journals.show', $cashReceiptJournal->id)
            ->with('success', 'Penerimaan Kas berhasil dibuat.');
    }

    public function show(Request $request, $journalId)
    {
        $journal = Journal::find($journalId);
        $filters = Session::get('cash_receipt_journals.index_filters', []);
        $journal->load(['branch', 'journalEntries.account', 'journalEntries.currency']);
        
        return Inertia::render('CashReceiptJournals/Show', [
            'cashReceiptJournal' => $journal,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, $journalId)
    {
        $journal = Journal::find($journalId);
        $filters = Session::get('cash_receipt_journals.index_filters', []);
        $journal->load(['branch.branchGroup', 'journalEntries.account', 'journalEntries.currency']);
        
        $companyId = $journal->branch->branchGroup->company_id;

        return Inertia::render('CashReceiptJournals/Edit', [
            'cashReceiptJournal' => $journal,
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
            'entries.*.credit' => 'required|numeric|min:0',
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
                $amount = $entry['credit'] * $entry['exchange_rate'];
                $totalAmount += $amount;

                $journal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'credit' => $entry['credit'],
                    'currency_id' => $entry['currency_id'],
                    'exchange_rate' => $entry['exchange_rate'],
                    'primary_currency_credit' => $amount,
                ]);
            }

            // Create the debit entry for kas_bank account
            $journal->journalEntries()->create([
                'account_id' => $validated['kas_bank_account_id'],
                'debit' => $validated['kas_bank_account_exchange_rate'] > 0 ? $totalAmount / $validated['kas_bank_account_exchange_rate'] : 0,
                'currency_id' => $validated['kas_bank_account_currency_id'],
                'exchange_rate' => $validated['kas_bank_account_exchange_rate'],
                'primary_currency_debit' => $totalAmount,
            ]);
        });

        return redirect()->route('cash-receipt-journals.edit', $journal->id)
            ->with('success', 'Penerimaan Kas berhasil diubah.');
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
            $redirectUrl = route('cash-receipt-journals.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Penerimaan Kas berhasil dihapus.');
        } else {
            return Redirect::route('cash-receipt-journals.index')
                ->with('success', 'Penerimaan Kas berhasil dihapus.');
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
            $redirectUrl = route('cash-receipt-journals.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Penerimaan Kas berhasil dihapus.');
        }
    }

    private function getFilteredCashReceiptJournals(Request $request)
    {
        $filters = $request->all() ?: Session::get('cash_receipt_journals.index_filters', []);

        $query = Journal::with(['branch', 'journalEntries.account'])
            ->withSum('journalEntries', 'primary_currency_debit')
            ->where('journal_type', 'cash_receipt');

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
        $journals = $this->getFilteredCashReceiptJournals($request);
        return Excel::download(new CashReceiptJournalsExport($journals), 'cash_receipt_journals.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $journals = $this->getFilteredCashReceiptJournals($request);
        return Excel::download(new CashReceiptJournalsExport($journals), 'cash_receipt_journals.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $journals = $this->getFilteredCashReceiptJournals($request);
        return Excel::download(new CashReceiptJournalsExport($journals), 'cash_receipt_journals.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public function print($journalId)
    {
        $journal = Journal::find($journalId);
        $journal->load(['user', 'branch.branchGroup.company', 'journalEntries.account', 'journalEntries.currency']); 
        return Inertia::render('CashReceiptJournals/Print', [ 'cashReceiptJournal' => $journal, ]);
    }
}