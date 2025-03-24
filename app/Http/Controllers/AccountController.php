<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Account;
use App\Models\Company;
use App\Models\Currency;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use App\Exports\AccountsExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('accounts.index_filters', []);
        Session::put('accounts.index_filters', $filters);

        $query = Account::query()->with(['parent', 'companies']);
        
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(type)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('companies', function ($q) use ($filters) {
                $q->whereIn('companies.id', $filters['company_id']);
            });
        }

        $perPage = $filters['per_page'] ?? 500;

        $sortColumn = $filters['sort'] ?? 'code';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);
        
        $accounts = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'companies' => $companies,
            'filters' => $filters,
            'perPage' => $perPage,
            'sortColumn' => $sortColumn,
            'sortOrder' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('accounts.index_filters', []);
        
        return Inertia::render('Accounts/Create', [
            'companies' => Company::orderBy('name', 'asc')->get(),
            'currencies' => Currency::orderBy('name', 'asc')->get(),
            'parentAccounts' => Account::orderBy('code', 'asc')->get(),
            'allAccounts' => Account::orderBy('code', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:accounts',
            'type' => 'required|string',
            'parent_id' => 'nullable|exists:accounts,id',
            'company_ids' => 'required|array',
            'company_ids.*' => 'exists:companies,id',
            'currency_ids' => 'required|array',
            'currency_ids.*' => 'exists:currencies,id',
        ]);

        DB::beginTransaction();
        try {
            $account = Account::create($validated);
            $account->companies()->attach($validated['company_ids']);
            $account->currencies()->attach($validated['currency_ids']);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with(['error' => $e->getMessage()]);
        }

        if ($request->input('create_another', false)) {
            return redirect()->route('accounts.create')
                ->with('success', 'Akun berhasil dibuat. Silakan buat akun lainnya.');
        }

        return redirect()->route('accounts.show', $account->id)
            ->with('success', 'Akun berhasil dibuat.');
    }    

    public function show(Account $account)
    {
        $filters = Session::get('accounts.index_filters', []);
        
        return Inertia::render('Accounts/Show', [
            'account' => $account->load(['parent', 'companies', 'currencies']),
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, Account $account)
    {
        $filters = Session::get('accounts.index_filters', []);

        return Inertia::render('Accounts/Edit', [
            'account' => $account->load(['parent', 'companies']),
            'companies' => Company::orderBy('name', 'asc')->get(),
            'currencies' => Currency::orderBy('name', 'asc')->get(),
            'parentAccounts' => Account::where('id', '!=', $account->id)->orderBy('code', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:accounts,code,' . $account->id,
            'type' => 'required|string',
            'parent_id' => 'nullable|exists:accounts,id',
            'company_ids' => 'required|array',
            'company_ids.*' => 'exists:companies,id',
            'currency_ids' => 'required|array',
            'currency_ids.*' => 'exists:currencies,id',
        ]);

        DB::beginTransaction();
        try {
            $account->update($validated);
            $account->companies()->sync($validated['company_ids']);
            $account->currencies()->sync($validated['currency_ids']);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with(['error' => 'Gagal mengubah akun. Silakan coba lagi.']);
        }

        return redirect()->route('accounts.show', $account->id)->with('success', 'Akun berhasil diubah.');
    }

    public function destroy(Request $request, Account $account)
    {
        if ($account->children()->exists()) {
            return redirect()->back()->with(['error' => 'Akun tidak dapat dihapus karena memiliki sub akun.']);
        }
        else if ($account->journalEntries()->exists()) {
            return redirect()->back()->with(['error' => 'Akun tidak dapat dihapus karena memiliki jurnal transaksi.']);
        }

        $account->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('accounts.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Akun berhasil dihapus.');
        } else {
            return Redirect::route('accounts.index')
                ->with('success', 'Akun berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $parentCount = Account::whereIn('id', $request->ids)->where('is_parent', true)->count();
        $entriesCount = JournalEntry::whereIn('account_id', $request->ids)->count();

        if ($parentCount > 0) {
            return redirect()->back()->with(['error' => 'Akun tidak dapat dihapus karena memiliki sub akun.']);
        }
        else if ($entriesCount > 0) {
            return redirect()->back()->with(['error' => 'Akun tidak dapat dihapus karena memiliki jurnal transaksi.']);
        }

        Account::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('accounts.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Akun berhasil dihapus.');
        }
    }

    private function getFilteredAccounts(Request $request)
    {
        $query = Account::query()->with(['parent', 'companies']);
        
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($request->search) . '%')
                  ->orWhere(DB::raw('lower(code)'), 'like', '%' . strtolower($request->search) . '%')
                  ->orWhere(DB::raw('lower(type)'), 'like', '%' . strtolower($request->search) . '%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('company_id')) {
            $query->whereHas('companies', function ($q) use ($request) {
                $q->whereIn('companies.id', $request->company_id);
            });
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $accounts = $this->getFilteredAccounts($request);
        return Excel::download(new AccountsExport($accounts), 'accounts.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $accounts = $this->getFilteredAccounts($request);
        return Excel::download(new AccountsExport($accounts), 'accounts.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $accounts = $this->getFilteredAccounts($request);
        return Excel::download(new AccountsExport($accounts), 'accounts.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
