<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Models\BusinessRelation;
use App\Models\BusinessRelationCreditTerm;
use Illuminate\Support\Facades\DB;
use App\Exports\PartnersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('partners.index_filters', []);
        Session::put('partners.index_filters', $filters);

        $query = BusinessRelation::query()
            ->where('type', 'partner')
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
        
        $partners = $query->paginate($perPage)->onEachSide(0)->withQueryString();
        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('Partners/Index', [
            'partners' => $partners,
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
        $filters = Session::get('partners.index_filters', []);
        
        return Inertia::render('Partners/Create', [
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

        $partner = DB::transaction(function () use ($validated) {
            $partner = BusinessRelation::create([
                ...$validated,
                'type' => 'partner',
            ]);

            if (!empty($validated['credit_terms'])) {
                $partner->creditTerms()->create($validated['credit_terms']);
            }

            if (!empty($validated['tags'])) {
                foreach ($validated['tags'] as $tag) {
                    $partner->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                foreach ($validated['custom_fields'] as $field) {
                    $partner->customFields()->create($field);
                }
            }

            $partner->companies()->attach($validated['company_ids']);

            return $partner;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('partners.create')
                ->with('success', 'Partner berhasil dibuat. Silahkan buat partner lainnya.');
        }

        return redirect()->route('partners.show', $partner->id)
            ->with('success', 'Partner berhasil dibuat.');
    }

    public function show(BusinessRelation $partner)
    {
        if ($partner->type !== 'partner') {
            abort(404);
        }

        $filters = Session::get('partners.index_filters', []);
        
        return Inertia::render('Partners/Show', [
            'partner' => $partner->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'statuses' => BusinessRelation::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function edit(BusinessRelation $partner)
    {
        if ($partner->type !== 'partner') {
            abort(404);
        }

        $filters = Session::get('partners.index_filters', []);
        
        return Inertia::render('Partners/Edit', [
            'partner' => $partner->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'companies' => Company::orderBy('name', 'asc')->get(),
            'filters' => $filters,
            'statuses' => BusinessRelation::STATUSES,
            'paymentTermTypes' => BusinessRelationCreditTerm::PAYMENT_TERM_TYPES,
        ]);
    }

    public function update(Request $request, BusinessRelation $partner)
    {
        if ($partner->type !== 'partner') {
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

        DB::transaction(function () use ($partner, $validated) {
            $partner->update($validated);

            if (!empty($validated['credit_terms'])) {
                $partner->creditTerms()->updateOrCreate(
                    ['business_relation_id' => $partner->id],
                    $validated['credit_terms']
                );
            }

            if (!empty($validated['tags'])) {
                $partner->tags()->delete();
                foreach ($validated['tags'] as $tag) {
                    $partner->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                $partner->customFields()->delete();
                foreach ($validated['custom_fields'] as $field) {
                    $partner->customFields()->create($field);
                }
            }

            $partner->companies()->sync($validated['company_ids']);
        });

        return redirect()->route('partners.edit', $partner->id)
            ->with('success', 'Partner berhasil diubah.');
    }

    public function destroy(Request $request, BusinessRelation $partner)
    {
        if ($partner->type !== 'partner') {
            abort(404);
        }

        $partner->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('partners.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Partner berhasil dihapus.');
        }

        return redirect()->route('partners.index')
            ->with('success', 'Partner berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        BusinessRelation::where('type', 'partner')
            ->whereIn('id', $request->ids)
            ->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('partners.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Partner yang dipilih berhasil dihapus.');
        }
    }

    private function getFilteredPartners(Request $request)
    {
        $query = BusinessRelation::query()
            ->where('type', 'partner')
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