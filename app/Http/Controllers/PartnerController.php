<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Partner;
use App\Models\PartnerBankAccount;
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
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank_name' => 'required_with:bank_accounts|string|max:255',
            'bank_accounts.*.account_number' => 'required_with:bank_accounts|string|max:255',
            'bank_accounts.*.account_holder_name' => 'required_with:bank_accounts|string|max:255',
            'bank_accounts.*.branch_name' => 'nullable|string|max:255',
            'bank_accounts.*.swift_code' => 'nullable|string|max:255',
            'bank_accounts.*.iban' => 'nullable|string|max:255',
            'bank_accounts.*.currency' => 'nullable|string|max:3',
            'bank_accounts.*.is_primary' => 'boolean',
            'bank_accounts.*.is_active' => 'boolean',
            'bank_accounts.*.notes' => 'nullable|string',
            'addresses' => 'nullable|array',
            'addresses.*.name' => 'required|string|max:255',
            'addresses.*.address' => 'required|string',
            'addresses.*.city' => 'nullable|string|max:255',
            'addresses.*.region' => 'nullable|string|max:255',
            'addresses.*.country' => 'nullable|string|max:255',
            'addresses.*.postal_code' => 'nullable|string|max:255',
            'addresses.*.phone' => 'nullable|string|max:255',
            'addresses.*.email' => 'nullable|email|max:255',
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

            // Create bank accounts if provided
            if (!empty($validated['bank_accounts'])) {
                foreach ($validated['bank_accounts'] as $acc) {
                    $partner->bankAccounts()->create([
                        'bank_name' => $acc['bank_name'],
                        'account_number' => $acc['account_number'],
                        'account_holder_name' => $acc['account_holder_name'],
                        'branch_name' => $acc['branch_name'] ?? null,
                        'swift_code' => $acc['swift_code'] ?? null,
                        'iban' => $acc['iban'] ?? null,
                        'currency' => $acc['currency'] ?? null,
                        'is_primary' => (bool)($acc['is_primary'] ?? false),
                        'is_active' => array_key_exists('is_active', $acc) ? (bool)$acc['is_active'] : true,
                        'notes' => $acc['notes'] ?? null,
                    ]);
                }
            }

            // Create addresses
            if (!empty($validated['addresses'])) {
                foreach ($validated['addresses'] as $addr) {
                    $partner->addresses()->create([
                        'name' => $addr['name'],
                        'address' => $addr['address'],
                        'city' => $addr['city'] ?? null,
                        'region' => $addr['region'] ?? null,
                        'country' => $addr['country'] ?? null,
                        'postal_code' => $addr['postal_code'] ?? null,
                        'phone' => $addr['phone'] ?? null,
                        'email' => $addr['email'] ?? null,
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
        $partner->load(['roles', 'contacts', 'companies', 'bankAccounts', 'addresses', 'createdBy', 'updatedBy']);
        
        return Inertia::render('Partners/Show', [
            'partner' => $partner,
            'filters' => $filters,
            'availableRoles' => Partner::getRoles(),
        ]);
    }

    public function edit(Partner $partner)
    {
        $filters = Session::get('partners.index_filters', []);
        $partner->load(['roles', 'contacts', 'companies', 'bankAccounts', 'addresses']);

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
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.id' => 'nullable|exists:partner_bank_accounts,id',
            'bank_accounts.*.bank_name' => 'required_with:bank_accounts|string|max:255',
            'bank_accounts.*.account_number' => 'required_with:bank_accounts|string|max:255',
            'bank_accounts.*.account_holder_name' => 'required_with:bank_accounts|string|max:255',
            'bank_accounts.*.branch_name' => 'nullable|string|max:255',
            'bank_accounts.*.swift_code' => 'nullable|string|max:255',
            'bank_accounts.*.iban' => 'nullable|string|max:255',
            'bank_accounts.*.currency' => 'nullable|string|max:3',
            'bank_accounts.*.is_primary' => 'boolean',
            'bank_accounts.*.is_active' => 'boolean',
            'bank_accounts.*.notes' => 'nullable|string',
            'addresses' => 'nullable|array',
            'addresses.*.id' => 'nullable|exists:partner_addresses,id',
            'addresses.*.name' => 'required|string|max:255',
            'addresses.*.address' => 'required|string',
            'addresses.*.city' => 'nullable|string|max:255',
            'addresses.*.region' => 'nullable|string|max:255',
            'addresses.*.country' => 'nullable|string|max:255',
            'addresses.*.postal_code' => 'nullable|string|max:255',
            'addresses.*.phone' => 'nullable|string|max:255',
            'addresses.*.email' => 'nullable|email|max:255',
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

            // Handle contacts - upsert pattern to preserve created_by
            $existingContactIds = $partner->contacts()->withoutPartnerContactAccess()->pluck('id')->toArray();
            $submittedContactIds = [];

            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contact) {
                    $data = [
                        'name' => $contact['name'],
                        'email' => $contact['email'] ?? null,
                        'phone' => $contact['phone'] ?? null,
                        'position' => $contact['position'] ?? null,
                        'notes' => $contact['notes'] ?? null,
                    ];

                    if (!empty($contact['id'])) {
                        // Update existing - created_by preserved
                        $partner->contacts()->withoutPartnerContactAccess()->where('id', $contact['id'])->update($data);
                        $submittedContactIds[] = $contact['id'];
                    } else {
                        // Create new - created_by auto-set via model boot
                        $newContact = $partner->contacts()->create($data);
                        $submittedContactIds[] = $newContact->id;
                    }
                }
            }

            // Delete contacts that were removed from the form
            $contactsToDelete = array_diff($existingContactIds, $submittedContactIds);
            if (!empty($contactsToDelete)) {
                $partner->contacts()->withoutPartnerContactAccess()->whereIn('id', $contactsToDelete)->delete();
            }

            // Handle addresses
            $existingAddressIds = $partner->addresses()->pluck('id')->toArray();
            $submittedAddressIds = [];

            if (!empty($validated['addresses'])) {
                foreach ($validated['addresses'] as $addr) {
                    $data = [
                        'name' => $addr['name'],
                        'address' => $addr['address'],
                        'city' => $addr['city'] ?? null,
                        'region' => $addr['region'] ?? null,
                        'country' => $addr['country'] ?? null,
                        'postal_code' => $addr['postal_code'] ?? null,
                        'phone' => $addr['phone'] ?? null,
                        'email' => $addr['email'] ?? null,
                    ];

                    if (!empty($addr['id'])) {
                        $partner->addresses()->where('id', $addr['id'])->update($data);
                        $submittedAddressIds[] = $addr['id'];
                    } else {
                        $newAddress = $partner->addresses()->create($data);
                        $submittedAddressIds[] = $newAddress->id;
                    }
                }
            }

            // Delete addresses that were removed
            $addressesToDelete = array_diff($existingAddressIds, $submittedAddressIds);
            if (!empty($addressesToDelete)) {
                $partner->addresses()->whereIn('id', $addressesToDelete)->delete();
            }

            // Handle bank accounts - delete all and recreate from payload (simpler sync)
            if (array_key_exists('bank_accounts', $validated)) {
                $payloadAccounts = $validated['bank_accounts'] ?? [];
                $existingAccounts = $partner->bankAccounts()->get()->keyBy('id');

                $updatedOrCreatedIds = [];
                $requestedPrimaryId = null;

                foreach ($payloadAccounts as $acc) {
                    $attributes = [
                        'bank_name' => $acc['bank_name'],
                        'account_number' => $acc['account_number'],
                        'account_holder_name' => $acc['account_holder_name'],
                        'branch_name' => $acc['branch_name'] ?? null,
                        'swift_code' => $acc['swift_code'] ?? null,
                        'iban' => $acc['iban'] ?? null,
                        'currency' => $acc['currency'] ?? null,
                        'is_active' => array_key_exists('is_active', $acc) ? (bool)$acc['is_active'] : true,
                        'notes' => $acc['notes'] ?? null,
                    ];

                    $isPrimary = (bool)($acc['is_primary'] ?? false);

                    if (!empty($acc['id']) && $existingAccounts->has($acc['id'])) {
                        $model = $existingAccounts->get($acc['id']);
                        $model->fill($attributes);
                        // Temporarily set primary flag; we will enforce single-primary after loop
                        $model->is_primary = $isPrimary;
                        $model->save();
                        $updatedOrCreatedIds[] = $model->id;
                        if ($isPrimary) {
                            $requestedPrimaryId = $model->id;
                        }
                    } else {
                        $model = $partner->bankAccounts()->create(array_merge($attributes, [
                            // Defer primary resolution to a single pass below
                            'is_primary' => false,
                        ]));
                        $updatedOrCreatedIds[] = $model->id;
                        if ($isPrimary) {
                            $requestedPrimaryId = $model->id;
                        }
                    }
                }

                // Deactivate accounts that were omitted from payload instead of deleting (to avoid FK issues)
                $missingIds = $existingAccounts->keys()->diff(collect($updatedOrCreatedIds));
                if ($missingIds->isNotEmpty()) {
                    $partner->bankAccounts()
                        ->whereIn('id', $missingIds->all())
                        ->update(['is_active' => false, 'is_primary' => false]);
                }

                // Enforce exactly one primary among active accounts
                if ($requestedPrimaryId) {
                    // Clear primary on all, then set requested one
                    $partner->bankAccounts()->update(['is_primary' => false]);
                    $partner->bankAccounts()->where('id', $requestedPrimaryId)->update(['is_primary' => true]);
                } else {
                    // If none requested as primary, keep current primary if still active; otherwise pick first active
                    $currentPrimary = $partner->bankAccounts()->where('is_primary', true)->first();
                    $hasActive = $partner->bankAccounts()->where('is_active', true)->exists();
                    if (!$currentPrimary && $hasActive) {
                        $firstActive = $partner->bankAccounts()->where('is_active', true)->orderBy('id')->first();
                        if ($firstActive) {
                            $partner->bankAccounts()->update(['is_primary' => false]);
                            $firstActive->is_primary = true;
                            $firstActive->save();
                        }
                    } else {
                        // Ensure no multiple primaries remain
                        if ($currentPrimary) {
                            $partner->bankAccounts()->where('id', '<>', $currentPrimary->id)->update(['is_primary' => false]);
                        }
                    }
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
            $partner->addresses()->delete();
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
                    $partner->addresses()->delete();
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
        
        // Removed addresses from eager loading here to avoid N+1 if not needed, or add if we want to search addresses
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