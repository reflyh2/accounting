<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Journal;
use App\Models\BranchGroup;
use Illuminate\Http\Request;
use App\Exports\CompaniesExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('companies.index_filters', []);
        Session::put('companies.index_filters', $filters);

        $query = Company::withoutGlobalScope('userCompanies')
            ->withCount('branches');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(legal_name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(address)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(phone)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        if ($sortColumn === 'branches_count') {
            $query->orderBy('branches_count', $sortOrder);
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $companies = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('companies.index_filters', []);
        
        return Inertia::render('Companies/Create', [
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:unique:companies,tax_id',
            'business_registration_number' => 'nullable|string|max:255|unique:companies,business_registration_number',
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',
            'industry' => 'nullable|string|max:255',
            'year_established' => 'nullable|integer|min:1800|max:' . date('Y'),
            'business_license_number' => 'nullable|string|max:255',
            'business_license_expiry' => 'nullable|date',
            'tax_registration_number' => 'nullable|string|max:255',
            'social_security_number' => 'nullable|string|max:255',
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id;

        $company = Company::create($validated);

        if ($request->input('create_another', false)) {
            return redirect()->route('companies.create')
                ->with('success', 'Data perusahaan berhasil dibuat. Silakan buat perusahaan lainnya.');
        }

        return redirect()->route('companies.show', $company->id)
            ->with('success', 'Data perusahaan berhasil dibuat.');
    }

    public function show(Request $request, Company $company)
    {
        $filters = Session::get('companies.index_filters', []);
        
        return Inertia::render('Companies/Show', [
            'company' => $company,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, Company $company)
    {
        $filters = Session::get('companies.index_filters', []);
        
        return Inertia::render('Companies/Edit', [
            'company' => $company,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255|unique:companies,tax_id,' . $company->id,
            'business_registration_number' => 'nullable|string|max:255|unique:companies,business_registration_number,' . $company->id,
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',
            'industry' => 'nullable|string|max:255',
            'year_established' => 'nullable|integer|min:1800|max:' . date('Y'),
            'business_license_number' => 'nullable|string|max:255',
            'business_license_expiry' => 'nullable|date',
            'tax_registration_number' => 'nullable|string|max:255',
            'social_security_number' => 'nullable|string|max:255',
        ]);

        $company->update($validated);

        return redirect()->route('companies.edit', $company->id)
            ->with('success', 'Data perusahaan berhasil diubah.');
    }

    public function destroy(Request $request, Company $company)
    {
        // Check if the company has branches
        if ($company->branches()->exists()) {
            return redirect()->back()->with(['error' => 'Perusahaan tidak dapat dihapus karena memiliki cabang.']);
        }

        // Check if the company has journals through branches
        $hasJournals = $company->branches()->whereHas('journals')->exists();
        if ($hasJournals) {
            return redirect()->back()->with(['error' => 'Perusahaan tidak dapat dihapus karena memiliki transaksi.']);
        }

        $company->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('companies.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Data perusahaan berhasil dihapus.');
        } else {
            return Redirect::route('companies.index')
                ->with('success', 'Data perusahaan berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $companyBranchesCount = Branch::whereIn('branch_group_id', BranchGroup::whereIn('company_id', $request->ids)->pluck('id'))->count();
        $companyJournalsCount = Journal::whereIn('branch_id', Branch::whereIn('branch_group_id', BranchGroup::whereIn('company_id', $request->ids)->pluck('id'))->pluck('id'))->count();

        if ($companyBranchesCount > 0) {
            return redirect()->back()->with(['error' => 'Perusahaan tidak dapat dihapus karena memiliki cabang.']);
        }

        if ($companyJournalsCount > 0) {
            return redirect()->back()->with(['error' => 'Perusahaan tidak dapat dihapus karena memiliki transaksi.']);
        }

        Company::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('companies.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Data perusahaan berhasil dihapus.');
        } else {
            return Redirect::route('companies.index')
                ->with('success', 'Data perusahaan berhasil dihapus.');
        }
    }

    private function getFilteredCompanies(Request $request)
    {
        $filters = $request->all() ?: Session::get('companies.index_filters', []);

        $query = Company::query()->withoutGlobalScope('userCompanies');
        
        if ($request->filled('search')) {
            $query->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(legal_name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(address)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(phone)'), 'like', '%' . strtolower($filters['search']) . '%');
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        if ($sortColumn === 'branches_count') {
            $query->orderBy('branches_count', $sortOrder);
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $companies = $this->getFilteredCompanies($request);
        return Excel::download(new CompaniesExport($companies), 'companies.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $companies = $this->getFilteredCompanies($request);
        return Excel::download(new CompaniesExport($companies), 'companies.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $companies = $this->getFilteredCompanies($request);
        return Excel::download(new CompaniesExport($companies), 'companies.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}