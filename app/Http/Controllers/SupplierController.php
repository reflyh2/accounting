<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Models\BusinessRelation;
use App\Models\BusinessRelationCreditTerm;
use Illuminate\Support\Facades\DB;
use App\Exports\SuppliersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('suppliers.index_filters', []);
        Session::put('suppliers.index_filters', $filters);

        $query = BusinessRelation::query()
            ->where('type', 'supplier')
            ->with(['companies', 'creditTerms', 'tags']);
        
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(email)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(phone)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(tax_id)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('companies', function($q) use ($filters) {
                $q->whereIn('companies.id', $filters['company_id']);
            });
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);
        
        $suppliers = $query->paginate($perPage)->onEachSide(0)->withQueryString();
        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'companies' => $companies,
            'filters' => $filters,
            'perPage' => $perPage,
            'sortColumn' => $sortColumn,
            'sortOrder' => $sortOrder,
            'statuses' => BusinessRelation::STATUSES,
        ]);
    }

    public function create()
    {
        $filters = Session::get('suppliers.index_filters', []);
        
        return Inertia::render('Suppliers/Create', [
            'companies' => Company::orderBy('name', 'asc')->get(),
            'filters' => $filters,
            'statuses' => BusinessRelation::STATUSES,
            'paymentTermTypes' => BusinessRelationCreditTerm::PAYMENT_TERM_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'status' => 'required|string|in:' . implode(',', array_keys(BusinessRelation::STATUSES)),
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'credit_terms' => 'nullable|array',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        $supplier = DB::transaction(function () use ($validated) {
            $supplier = BusinessRelation::create([
                'type' => 'supplier',
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'tax_id' => $validated['tax_id'],
                'registration_number' => $validated['registration_number'],
                'industry' => $validated['industry'],
                'website' => $validated['website'],
                'status' => $validated['status'],
            ]);

            $supplier->companies()->attach($validated['company_ids']);

            if (!empty($validated['credit_terms'])) {
                $supplier->creditTerms()->create($validated['credit_terms']);
            }

            if (!empty($validated['tags'])) {
                foreach ($validated['tags'] as $tag) {
                    $supplier->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                foreach ($validated['custom_fields'] as $field) {
                    $supplier->customFields()->create($field);
                }
            }

            return $supplier;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('suppliers.create')
                ->with('success', 'Pemasok berhasil dibuat. Silahkan buat pemasok lainnya.');
        }

        return redirect()->route('suppliers.show', $supplier->id)
            ->with('success', 'Pemasok berhasil dibuat.');
    }

    public function show(BusinessRelation $supplier)
    {
        if ($supplier->type !== 'supplier') {
            abort(404);
        }

        $filters = Session::get('suppliers.index_filters', []);
        
        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'statuses' => BusinessRelation::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function edit(BusinessRelation $supplier)
    {
        if ($supplier->type !== 'supplier') {
            abort(404);
        }

        $filters = Session::get('suppliers.index_filters', []);
        
        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'companies' => Company::orderBy('name', 'asc')->get(),
            'filters' => $filters,
            'statuses' => BusinessRelation::STATUSES,
            'paymentTermTypes' => BusinessRelationCreditTerm::PAYMENT_TERM_TYPES,
        ]);
    }

    public function update(Request $request, BusinessRelation $supplier)
    {
        if ($supplier->type !== 'supplier') {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'status' => 'required|string|in:' . implode(',', array_keys(BusinessRelation::STATUSES)),
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'credit_terms' => 'nullable|array',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        DB::transaction(function () use ($supplier, $validated) {
            $supplier->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'tax_id' => $validated['tax_id'],
                'registration_number' => $validated['registration_number'],
                'industry' => $validated['industry'],
                'website' => $validated['website'],
                'status' => $validated['status'],
            ]);

            $supplier->companies()->sync($validated['company_ids']);

            if (!empty($validated['credit_terms'])) {
                $supplier->creditTerms()->updateOrCreate(
                    ['business_relation_id' => $supplier->id],
                    $validated['credit_terms']
                );
            }

            if (!empty($validated['tags'])) {
                $supplier->tags()->delete();
                foreach ($validated['tags'] as $tag) {
                    $supplier->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                $supplier->customFields()->delete();
                foreach ($validated['custom_fields'] as $field) {
                    $supplier->customFields()->create($field);
                }
            }
        });

        return redirect()->route('suppliers.edit', $supplier->id)
            ->with('success', 'Pemasok berhasil diubah.');
    }

    public function destroy(Request $request, BusinessRelation $supplier)
    {
        if ($supplier->type !== 'supplier') {
            abort(404);
        }

        $supplier->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('suppliers.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pemasok berhasil dihapus.');
        }

        return redirect()->route('suppliers.index')
            ->with('success', 'Pemasok berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        BusinessRelation::where('type', 'supplier')
            ->whereIn('id', $request->ids)
            ->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('suppliers.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pemasok yang dipilih berhasil dihapus.');
        }
    }

    private function getFilteredSuppliers(Request $request)
    {
        $query = BusinessRelation::query()
            ->where('type', 'supplier')
            ->with(['company', 'creditTerms', 'tags']);
        
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($request->search) . '%')
                  ->orWhere(DB::raw('lower(email)'), 'like', '%' . strtolower($request->search) . '%');
            });
        }

        if ($request->filled('company_id')) {
            $query->whereIn('company_id', $request->company_id);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $suppliers = $this->getFilteredSuppliers($request);
        return Excel::download(new SuppliersExport($suppliers), 'suppliers.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $suppliers = $this->getFilteredSuppliers($request);
        return Excel::download(new SuppliersExport($suppliers), 'suppliers.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $suppliers = $this->getFilteredSuppliers($request);
        return Excel::download(new SuppliersExport($suppliers), 'suppliers.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
} 