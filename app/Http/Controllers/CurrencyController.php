<?php

namespace App\Http\Controllers;

use App\Exports\CurrenciesExport;
use Inertia\Inertia;
use App\Models\Currency;
use App\Models\Company;
use App\Models\CompanyCurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

class CurrencyController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('currencies.index_filters', []);
        Session::put('currencies.index_filters', $filters);

        $query = Currency::query();
        
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 10;

        $sortColumn = $filters['sort'] ?? 'is_primary';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);
        $currencies = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('Currencies/Index', [
            'currencies' => $currencies,
            'filters' => $filters,
            'perPage' => $perPage,
            'sortColumn' => $sortColumn,
            'sortOrder' => $sortOrder,
        ]);
    }

    public function create()
    {
        $filters = Session::get('currencies.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();
        
        return Inertia::render('Currencies/Create', [
            'companies' => $companies,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:3|unique:currencies',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'is_primary' => 'boolean',
            'exchange_rates' => 'required|array',
            'exchange_rates.*.company_id' => 'required|exists:companies,id',
            'exchange_rates.*.rate' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $currency = Currency::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'symbol' => $validated['symbol'],
                'is_primary' => $validated['is_primary'] ?? false,
            ]);

            foreach ($validated['exchange_rates'] as $rate) {
                CompanyCurrencyRate::create([
                    'company_id' => $rate['company_id'],
                    'currency_id' => $currency->id,
                    'exchange_rate' => $rate['rate'],
                ]);
            }

            if ($currency->is_primary) {
                Currency::where('id', '!=', $currency->id)->update(['is_primary' => false]);
            }
        });

        if ($request->input('create_another')) {
            return redirect()->route('currencies.create')->with('success', 'Mata uang berhasil ditambahkan.');
        } else {
            return redirect()->route('currencies.index')->with('success', 'Mata uang berhasil ditambahkan.');
        }
    }

    public function show(Currency $currency)
    {
        $filters = Session::get('currencies.index_filters', []);
        $currency->load('companyRates.company');
        
        return Inertia::render('Currencies/Show', [
            'currency' => $currency,
            'filters' => $filters,
        ]);
    }

    public function edit(Currency $currency)
    {
        $filters = Session::get('currencies.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();
        $currency->load('companyRates');
        
        return Inertia::render('Currencies/Edit', [
            'currency' => $currency,
            'companies' => $companies,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Currency $currency)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:3|unique:currencies,code,' . $currency->id,
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'is_primary' => 'boolean',
            'exchange_rates' => 'required|array',
            'exchange_rates.*.company_id' => 'required|exists:companies,id',
            'exchange_rates.*.rate' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($currency, $validated) {
            $currency->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'symbol' => $validated['symbol'],
                'is_primary' => $validated['is_primary'] ?? false,
            ]);

            $currency->companyRates()->delete();

            foreach ($validated['exchange_rates'] as $rate) {
                CompanyCurrencyRate::create([
                    'company_id' => $rate['company_id'],
                    'currency_id' => $currency->id,
                    'exchange_rate' => $rate['rate'],
                ]);
            }

            if ($currency->is_primary) {
                Currency::where('id', '!=', $currency->id)->update(['is_primary' => false]);
            }
        });

        return redirect()->route('currencies.edit', $currency->id)->with('success', 'Mata uang berhasil diubah.');
    }

    public function destroy(Request $request, Currency $currency)
    {
        if ($currency->accounts()->exists()) {
            return redirect()->back()->with(['error' => 'Mata uang tidak dapat dihapus karena sedang digunakan.']);
        }

        $currency->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('currencies.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Mata uang berhasil dihapus.');
        } else {
            return Redirect::route('currencies.index')
                ->with('success', 'Mata uang berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $currencyAccountsCount = Currency::whereIn('id', $request->ids)
            ->whereHas('accounts')
            ->count();

        if ($currencyAccountsCount > 0) {
            return redirect()->back()->with(['error' => 'Beberapa mata uang tidak dapat dihapus karena sedang digunakan.']);
        }
        
        Currency::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('currencies.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Mata uang berhasil dihapus.');
        } else {
            return Redirect::route('currencies.index')
                ->with('success', 'Mata uang berhasil dihapus.');
        }
    }

    private function getFilteredCurrencies(Request $request)
    {
        $query = Currency::query();
        
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where(DB::raw('lower(code)'), 'like', '%' . strtolower($request->search) . '%')
                  ->orWhere(DB::raw('lower(name)'), 'like', '%' . strtolower($request->search) . '%');
            });
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $currencies = $this->getFilteredCurrencies($request);
        return Excel::download(new CurrenciesExport($currencies), 'currencies.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $currencies = $this->getFilteredCurrencies($request);
        return Excel::download(new CurrenciesExport($currencies), 'currencies.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $currencies = $this->getFilteredCurrencies($request);
        return Excel::download(new CurrenciesExport($currencies), 'currencies.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}