<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Partner;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Exports\PartnersExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('partners.index_filters', []);
        Session::put('partners.index_filters', $filters);

        $query = Partner::with(['roles', 'contacts', 'companies']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(email)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(phone)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('companies', function ($query) use ($filters) {
                $query->whereIn('companies.id', $filters['company_id']);
            });
        }

        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($query) use ($filters) {
                $query->whereIn('role', $filters['role']);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $partners = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('Partners/Index', [
            'partners' => $partners,
            'companies' => $companies,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'availableRoles' => Partner::getRoles(),
        ]);
    }

    public function create()
    {
        $filters = Session::get('partners.index_filters', []);
        
        return Inertia::render('Partners/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'availableRoles' => Partner::getRoles(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'roles' => 'required|array|min:1',
            'roles.*.role' => 'required|string|in:supplier,customer,asset_supplier,asset_customer,creditor,others',
            'roles.*.credit_limit' => 'nullable|numeric|min:0',
            'roles.*.payment_term_days' => 'nullable|integer|min:0',
            'roles.*.notes' => 'nullable|string',
            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:255',
            'contacts.*.position' => 'nullable|string|max:255',
            'contacts.*.notes' => 'nullable|string',
        ]);

        $partner = DB::transaction(function () use ($validated, $request) {
            $partner = Partner::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'address' => $validated['address'],
                'city' => $validated['city'],
                'region' => $validated['region'],
                'country' => $validated['country'],
                'postal_code' => $validated['postal_code'],
                'tax_id' => $validated['tax_id'],
                'registration_number' => $validated['registration_number'],
                'industry' => $validated['industry'],
                'website' => $validated['website'],
                'notes' => $validated['notes'],
                'status' => $validated['status'],
            ]);

            // Sync companies
            $partner->companies()->sync($validated['company_ids']);

            // Create roles
            foreach ($validated['roles'] as $role) {
                $partner->roles()->create([
                    'role' => $role['role'],
                    'credit_limit' => $role['credit_limit'] ?? 0,
                    'payment_term_days' => $role['payment_term_days'] ?? 0,
                    'notes' => $role['notes'] ?? null,
                    'status' => 'active'
                ]);
            }

            // Create contacts
            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contact) {
                    $partner->contacts()->create([
                        'name' => $contact['name'],
                        'email' => $contact['email'] ?? null,
                        'phone' => $contact['phone'] ?? null,
                        'position' => $contact['position'] ?? null,
                        'notes' => $contact['notes'] ?? null,
                    ]);
                }
            }

            return $partner;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('partners.create')
                ->with('success', 'Partner berhasil dibuat. Silakan buat partner lainnya.');
        }

        return redirect()->route('partners.show', $partner->id)
            ->with('success', 'Partner berhasil dibuat.');
    }

    public function show(Partner $partner)
    {
        $filters = Session::get('partners.index_filters', []);
        $partner->load(['roles', 'contacts', 'companies', 'createdBy', 'updatedBy']);
        
        return Inertia::render('Partners/Show', [
            'partner' => $partner,
            'filters' => $filters,
            'availableRoles' => Partner::getRoles(),
        ]);
    }

    public function edit(Partner $partner)
    {
        $filters = Session::get('partners.index_filters', []);
        $partner->load(['roles', 'contacts', 'companies']);
        
        return Inertia::render('Partners/Edit', [
            'partner' => $partner,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'availableRoles' => Partner::getRoles(),
        ]);
    }

    public function update(Request $request, Partner $partner)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'roles' => 'required|array|min:1',
            'roles.*.role' => 'required|string|in:supplier,customer,asset_supplier,asset_customer,creditor,others',
            'roles.*.credit_limit' => 'nullable|numeric|min:0',
            'roles.*.payment_term_days' => 'nullable|integer|min:0',
            'roles.*.notes' => 'nullable|string',
            'contacts' => 'nullable|array',
            'contacts.*.id' => 'nullable|exists:partner_contacts,id',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:255',
            'contacts.*.position' => 'nullable|string|max:255',
            'contacts.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $partner) {
            $partner->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'address' => $validated['address'],
                'city' => $validated['city'],
                'region' => $validated['region'],
                'country' => $validated['country'],
                'postal_code' => $validated['postal_code'],
                'tax_id' => $validated['tax_id'],
                'registration_number' => $validated['registration_number'],
                'industry' => $validated['industry'],
                'website' => $validated['website'],
                'notes' => $validated['notes'],
                'status' => $validated['status'],
            ]);

            // Sync companies
            $partner->companies()->sync($validated['company_ids']);

            // Handle roles - delete all and recreate
            $partner->roles()->delete();
            foreach ($validated['roles'] as $role) {
                $partner->roles()->create([
                    'role' => $role['role'],
                    'credit_limit' => $role['credit_limit'] ?? 0,
                    'payment_term_days' => $role['payment_term_days'] ?? 0,
                    'notes' => $role['notes'] ?? null,
                    'status' => 'active'
                ]);
            }

            // Handle contacts - delete all and recreate
            $partner->contacts()->delete();
            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contact) {
                    $partner->contacts()->create([
                        'name' => $contact['name'],
                        'email' => $contact['email'] ?? null,
                        'phone' => $contact['phone'] ?? null,
                        'position' => $contact['position'] ?? null,
                        'notes' => $contact['notes'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('partners.show', $partner->id)
            ->with('success', 'Partner berhasil diubah.');
    }

    public function destroy(Request $request, Partner $partner)
    {
        DB::transaction(function () use ($partner) {
            // Delete related records
            $partner->roles()->delete();
            $partner->contacts()->delete();
            $partner->companies()->detach();
            $partner->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('partners.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Partner berhasil dihapus.');
        } else {
            return Redirect::route('partners.index')
                ->with('success', 'Partner berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $partner = Partner::find($id);
                if ($partner) {
                    $partner->roles()->delete();
                    $partner->contacts()->delete();
                    $partner->companies()->detach();
                    $partner->delete();
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('partners.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Partner berhasil dihapus.');
        }
    }

    private function getFilteredPartners(Request $request)
    {
        $filters = $request->all() ?: Session::get('partners.index_filters', []);

        $query = Partner::with(['roles', 'contacts', 'companies']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(email)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(phone)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('companies', function ($query) use ($filters) {
                $query->whereIn('companies.id', $filters['company_id']);
            });
        }

        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($query) use ($filters) {
                $query->whereIn('role', $filters['role']);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $partners = $this->getFilteredPartners($request);
        return Excel::download(new PartnersExport($partners), 'partners.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $partners = $this->getFilteredPartners($request);
        return Excel::download(new PartnersExport($partners), 'partners.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $partners = $this->getFilteredPartners($request);
        return Excel::download(new PartnersExport($partners), 'partners.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
} 