<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Models\BusinessRelation;
use App\Models\BusinessRelationCreditTerm;
use Illuminate\Support\Facades\DB;
use App\Exports\EmployeesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('employees.index_filters', []);
        Session::put('employees.index_filters', $filters);

        $query = BusinessRelation::query()
            ->where('type', 'employee')
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
        
        $employees = $query->paginate($perPage)->onEachSide(0)->withQueryString();
        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
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
        $filters = Session::get('employees.index_filters', []);
        
        return Inertia::render('Employees/Create', [
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
            'registration_number' => 'nullable|string|max:255', // Could be employee ID
            'industry' => 'nullable|string|max:255', // Could be department
            'website' => 'nullable|string|max:255',
            'status' => 'required|string|in:' . implode(',', array_keys(BusinessRelation::STATUSES)),
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'credit_terms' => 'nullable|array', // Could be salary information
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        $employee = DB::transaction(function () use ($validated) {
            $employee = BusinessRelation::create([
                'type' => 'employee',
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

            $employee->companies()->attach($validated['company_ids']);

            if (!empty($validated['credit_terms'])) {
                $employee->creditTerms()->create($validated['credit_terms']);
            }

            if (!empty($validated['tags'])) {
                foreach ($validated['tags'] as $tag) {
                    $employee->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                foreach ($validated['custom_fields'] as $field) {
                    $employee->customFields()->create($field);
                }
            }

            return $employee;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('employees.create')
                ->with('success', 'Karyawan berhasil dibuat. Silahkan buat karyawan lainnya.');
        }

        return redirect()->route('employees.show', $employee->id)
            ->with('success', 'Karyawan berhasil dibuat.');
    }

    public function show(BusinessRelation $employee)
    {
        if ($employee->type !== 'employee') {
            abort(404);
        }

        $filters = Session::get('employees.index_filters', []);
        
        return Inertia::render('Employees/Show', [
            'employee' => $employee->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'statuses' => BusinessRelation::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function edit(BusinessRelation $employee)
    {
        if ($employee->type !== 'employee') {
            abort(404);
        }

        $filters = Session::get('employees.index_filters', []);
        
        return Inertia::render('Employees/Edit', [
            'employee' => $employee->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'companies' => Company::orderBy('name', 'asc')->get(),
            'filters' => $filters,
            'statuses' => BusinessRelation::STATUSES,
            'paymentTermTypes' => BusinessRelationCreditTerm::PAYMENT_TERM_TYPES,
        ]);
    }

    public function update(Request $request, BusinessRelation $employee)
    {
        if ($employee->type !== 'employee') {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255', // Employee ID
            'industry' => 'nullable|string|max:255', // Department
            'website' => 'nullable|string|max:255',
            'status' => 'required|string|in:' . implode(',', array_keys(BusinessRelation::STATUSES)),
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'credit_terms' => 'nullable|array', // Salary information
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        DB::transaction(function () use ($employee, $validated) {
            $employee->update([
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

            $employee->companies()->sync($validated['company_ids']);

            if (!empty($validated['credit_terms'])) {
                $employee->creditTerms()->updateOrCreate(
                    ['business_relation_id' => $employee->id],
                    $validated['credit_terms']
                );
            }

            if (!empty($validated['tags'])) {
                $employee->tags()->delete();
                foreach ($validated['tags'] as $tag) {
                    $employee->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                $employee->customFields()->delete();
                foreach ($validated['custom_fields'] as $field) {
                    $employee->customFields()->create($field);
                }
            }
        });

        return redirect()->route('employees.edit', $employee->id)
            ->with('success', 'Karyawan berhasil diubah.');
    }

    public function destroy(Request $request, BusinessRelation $employee)
    {
        if ($employee->type !== 'employee') {
            abort(404);
        }

        $employee->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('employees.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Karyawan berhasil dihapus.');
        }

        return redirect()->route('employees.index')
            ->with('success', 'Karyawan berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        BusinessRelation::where('type', 'employee')
            ->whereIn('id', $request->ids)
            ->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('employees.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Karyawan yang dipilih berhasil dihapus.');
        }
    }

    private function getFilteredEmployees(Request $request)
    {
        $query = BusinessRelation::query()
            ->where('type', 'employee')
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
        $employees = $this->getFilteredEmployees($request);
        return Excel::download(new EmployeesExport($employees), 'employees.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $employees = $this->getFilteredEmployees($request);
        return Excel::download(new EmployeesExport($employees), 'employees.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $employees = $this->getFilteredEmployees($request);
        return Excel::download(new EmployeesExport($employees), 'employees.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
} 