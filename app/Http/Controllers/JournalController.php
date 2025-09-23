<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Currency;
use Illuminate\Http\Request;
use App\Exports\JournalsExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('journals.index_filters', []);
        Session::put('journals.index_filters', $filters);

        $query = Journal::with(['branch.branchGroup.company', 'journalEntries.account'])
            ->withSum('journalEntries', 'primary_currency_debit')
            ->where('journal_type', 'general');

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

        $journals = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }
        return Inertia::render('Journals/Index', [
            'journals' => $journals,
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
        $filters = Session::get('journals.index_filters', []);
        $accounts = Account::all();
        
        return Inertia::render('Journals/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'accounts' => fn() => Account::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->where('is_parent', false)->with('currencies.companyRates')->orderBy('code', 'asc')->get(),
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
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => 'required|exists:accounts,id',
            'entries.*.debit' => 'required|numeric|min:0',
            'entries.*.credit' => 'required|numeric|min:0',
            'entries.*.currency_id' => 'required|exists:currencies,id',
            'entries.*.exchange_rate' => 'required|numeric|min:0',
        ]);

        // Calculate total debit and credit in primary currency
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($validated['entries'] as $entry) {
            $totalDebit += $entry['debit'] * $entry['exchange_rate'];
            $totalCredit += $entry['credit'] * $entry['exchange_rate'];
        }

        // Check if total debit equals total credit
        if (abs($totalDebit - $totalCredit) > 0.01) { // Using 0.01 to account for potential floating-point imprecision
            return redirect()->back()->with(['error' => 'Total debit dan kredit harus sama.']);
        }

        $journal = DB::transaction(function () use ($validated, $request) {
            $journal = Journal::create([
                'branch_id' => $validated['branch_id'],
                'user_global_id' => $request->user()->global_id,
                'date' => $validated['date'],
                'journal_type' => 'general',
                'reference_number' => $validated['reference_number'],
                'description' => $validated['description'],
            ]);

            foreach ($validated['entries'] as $entry) {
                $journal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'currency_id' => $entry['currency_id'],
                    'exchange_rate' => $entry['exchange_rate'],
                    'primary_currency_debit' => $entry['debit'] * $entry['exchange_rate'],
                    'primary_currency_credit' => $entry['credit'] * $entry['exchange_rate'],
                ]);
            }

            return $journal;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('journals.create')
                ->with('success', 'Jurnal berhasil dibuat. Silakan buat jurnal lainnya.');
        }

        return redirect()->route('journals.show', $journal->id)
            ->with('success', 'Jurnal berhasil dibuat.');
    }

    public function show(Request $request, Journal $journal)
    {
        $filters = Session::get('journals.index_filters', []);
        $journal->load(['branch', 'journalEntries.account', 'journalEntries.currency']);
        
        return Inertia::render('Journals/Show', [
            'journal' => $journal,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, Journal $journal)
    {
        $filters = Session::get('journals.index_filters', []);
        $journal->load(['branch.branchGroup', 'journalEntries.account', 'journalEntries.currency']);
        
        $companyId = $journal->branch->branchGroup->company_id;
        
        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        return Inertia::render('Journals/Edit', [
            'journal' => $journal,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'accounts' => Account::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->where('is_parent', false)->with('currencies.companyRates')->orderBy('code', 'asc')->get(),
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
        ]);
    }

    public function update(Request $request, Journal $journal)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'entries' => 'required|array|min:2',
            'entries.*.id' => 'nullable|exists:journal_entries,id',
            'entries.*.account_id' => 'required|exists:accounts,id',
            'entries.*.debit' => 'required|numeric|min:0',
            'entries.*.credit' => 'required|numeric|min:0',
            'entries.*.currency_id' => 'required|exists:currencies,id',
            'entries.*.exchange_rate' => 'required|numeric|min:0',
        ]);

        // Check if year has changed
        $oldYear = date('Y', strtotime($journal->date));
        $newYear = date('Y', strtotime($validated['date']));
        if ($oldYear !== $newYear) {
            return redirect()->back()->with('error', 'Tahun jurnal tidak dapat diubah.');
        }

        // Check if branch_id has changed
        if ($journal->branch_id !== $validated['branch_id']) {
            return redirect()->back()->with('error', 'Cabang jurnal tidak dapat diubah.');
        }

        // Check if company has changed
        $newBranch = Branch::with('branchGroup.company')->find($validated['branch_id']);
        $oldBranch = $journal->branch()->with('branchGroup.company')->first();
        
        if ($newBranch->branchGroup->company_id !== $oldBranch->branchGroup->company_id) {
            return redirect()->back()->with('error', 'Perusahaan jurnal tidak dapat diubah.');
        }

        // Calculate total debit and credit in primary currency
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($validated['entries'] as $entry) {
            $totalDebit += $entry['debit'] * $entry['exchange_rate'];
            $totalCredit += $entry['credit'] * $entry['exchange_rate'];
        }

        // Check if total debit equals total credit
        if (abs($totalDebit - $totalCredit) > 0.01) { // Using 0.01 to account for potential floating-point imprecision
            return redirect()->back()->with(['error' => 'Total debit dan kredit harus sama.']);
        }

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

            foreach ($validated['entries'] as $entry) {
                $journal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'currency_id' => $entry['currency_id'],
                    'exchange_rate' => $entry['exchange_rate'],
                    'primary_currency_debit' => $entry['debit'] * $entry['exchange_rate'],
                    'primary_currency_credit' => $entry['credit'] * $entry['exchange_rate'],
                ]);
            }
        });

        return redirect()->route('journals.edit', $journal->id)
            ->with('success', 'Jurnal berhasil diubah.');
    }

    public function destroy(Request $request, Journal $journal)
    {
        DB::transaction(function () use ($journal) {
            foreach ($journal->journalEntries as $entry) {
                $entry->delete();
            }
            $journal->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('journals.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Jurnal berhasil dihapus.');
        } else {
            return Redirect::route('journals.index')
                ->with('success', 'Jurnal berhasil dihapus.');
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
            $redirectUrl = route('journals.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Jurnal berhasil dihapus.');
        }
    }

    private function getFilteredJournals(Request $request)
    {
        $filters = $request->all() ?: Session::get('journals.index_filters', []);

        $query = Journal::with(['branch', 'journalEntries.account'])->withSum('journalEntries', 'primary_currency_debit');

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

        $sortColumn = $filters['sort'] ?? 'date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $journals = $this->getFilteredJournals($request);
        return Excel::download(new JournalsExport($journals), 'journals.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $journals = $this->getFilteredJournals($request);
        return Excel::download(new JournalsExport($journals), 'journals.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $journals = $this->getFilteredJournals($request);
        return Excel::download(new JournalsExport($journals), 'journals.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public function print(Journal $journal)
    {
        $journal->load(['user', 'branch.branchGroup.company', 'journalEntries.account', 'journalEntries.currency']);
        
        return Inertia::render('Journals/Print', [
            'journal' => $journal,
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
        ]);
    }
}