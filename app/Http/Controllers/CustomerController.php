<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Models\BusinessRelation;
use App\Models\BusinessRelationCreditTerm;
use Illuminate\Support\Facades\DB;
use App\Exports\CustomersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('customers.index_filters', []);
        Session::put('customers.index_filters', $filters);

        $query = BusinessRelation::query()
            ->where('type', 'customer')
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
        
        $customers = $query->paginate($perPage)->onEachSide(0)->withQueryString();
        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
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
        $filters = Session::get('customers.index_filters', []);
        
        return Inertia::render('Customers/Create', [
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

        $customer = DB::transaction(function () use ($validated) {
            $customer = BusinessRelation::create([
                'type' => 'customer',
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

            $customer->companies()->attach($validated['company_ids']);

            if (!empty($validated['credit_terms'])) {
                $customer->creditTerms()->create($validated['credit_terms']);
            }

            if (!empty($validated['tags'])) {
                foreach ($validated['tags'] as $tag) {
                    $customer->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                foreach ($validated['custom_fields'] as $field) {
                    $customer->customFields()->create($field);
                }
            }

            return $customer;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('customers.create')
                ->with('success', 'Pelanggan berhasil dibuat. Silahkan buat pelanggan lainnya.');
        }

        return redirect()->route('customers.show', $customer->id)
            ->with('success', 'Pelanggan berhasil dibuat.');
    }

    public function show(BusinessRelation $customer)
    {
        if ($customer->type !== 'customer') {
            abort(404);
        }

        $filters = Session::get('customers.index_filters', []);
        
        return Inertia::render('Customers/Show', [
            'customer' => $customer->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'statuses' => BusinessRelation::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function edit(BusinessRelation $customer)
    {
        if ($customer->type !== 'customer') {
            abort(404);
        }

        $filters = Session::get('customers.index_filters', []);
        
        return Inertia::render('Customers/Edit', [
            'customer' => $customer->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'companies' => Company::orderBy('name', 'asc')->get(),
            'filters' => $filters,
            'statuses' => BusinessRelation::STATUSES,
            'paymentTermTypes' => BusinessRelationCreditTerm::PAYMENT_TERM_TYPES,
        ]);
    }

    public function update(Request $request, BusinessRelation $customer)
    {
        if ($customer->type !== 'customer') {
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

        DB::transaction(function () use ($customer, $validated) {
            $customer->update([
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

            $customer->companies()->sync($validated['company_ids']);

            if (!empty($validated['credit_terms'])) {
                $customer->creditTerms()->updateOrCreate(
                    ['business_relation_id' => $customer->id],
                    $validated['credit_terms']
                );
            }

            if (!empty($validated['tags'])) {
                $customer->tags()->delete();
                foreach ($validated['tags'] as $tag) {
                    $customer->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                $customer->customFields()->delete();
                foreach ($validated['custom_fields'] as $field) {
                    $customer->customFields()->create($field);
                }
            }
        });

        return redirect()->route('customers.edit', $customer->id)
            ->with('success', 'Pelanggan berhasil diubah.');
    }

    public function destroy(Request $request, BusinessRelation $customer)
    {
        if ($customer->type !== 'customer') {
            abort(404);
        }

        $customer->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('customers.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pelanggan berhasil dihapus.');
        }

        return redirect()->route('customers.index')
            ->with('success', 'Pelanggan berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        BusinessRelation::where('type', 'customer')
            ->whereIn('id', $request->ids)
            ->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('customers.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pelanggan yang dipilih berhasil dihapus.');
        }
    }

    private function getFilteredCustomers(Request $request)
    {
        $query = BusinessRelation::query()
            ->where('type', 'customer')
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
        $customers = $this->getFilteredCustomers($request);
        return Excel::download(new CustomersExport($customers), 'customers.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $customers = $this->getFilteredCustomers($request);
        return Excel::download(new CustomersExport($customers), 'customers.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $customers = $this->getFilteredCustomers($request);
        return Excel::download(new CustomersExport($customers), 'customers.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
} 