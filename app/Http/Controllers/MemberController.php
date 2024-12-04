<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Models\BusinessRelation;
use App\Models\BusinessRelationCreditTerm;
use Illuminate\Support\Facades\DB;
use App\Exports\MembersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class MemberController extends Controller
{
    // The methods are similar to SupplierController and CustomerController
    // but with 'member' type and appropriate naming
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('members.index_filters', []);
        Session::put('members.index_filters', $filters);

        $query = BusinessRelation::query()
            ->where('type', 'member')
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
        
        $members = $query->paginate($perPage)->onEachSide(0)->withQueryString();
        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('Members/Index', [
            'members' => $members,
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
        $filters = Session::get('members.index_filters', []);
        
        return Inertia::render('Members/Create', [
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

        $member = DB::transaction(function () use ($validated) {
            $member = BusinessRelation::create([
                ...$validated,
                'type' => 'member',
            ]);

            if (!empty($validated['credit_terms'])) {
                $member->creditTerms()->create($validated['credit_terms']);
            }

            if (!empty($validated['tags'])) {
                foreach ($validated['tags'] as $tag) {
                    $member->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                foreach ($validated['custom_fields'] as $field) {
                    $member->customFields()->create($field);
                }
            }

            $member->companies()->attach($validated['company_ids']);

            return $member;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('members.create')
                ->with('success', 'Anggota berhasil dibuat. Silahkan buat anggota lainnya.');
        }

        return redirect()->route('members.show', $member->id)
            ->with('success', 'Anggota berhasil dibuat.');
    }

    public function show(BusinessRelation $member)
    {
        if ($member->type !== 'member') {
            abort(404);
        }

        $filters = Session::get('members.index_filters', []);
        
        return Inertia::render('Members/Show', [
            'member' => $member->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'statuses' => BusinessRelation::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function edit(BusinessRelation $member)
    {
        if ($member->type !== 'member') {
            abort(404);
        }

        $filters = Session::get('members.index_filters', []);
        
        return Inertia::render('Members/Edit', [
            'member' => $member->load(['companies', 'creditTerms', 'tags', 'customFields']),
            'companies' => Company::orderBy('name', 'asc')->get(),
            'filters' => $filters,
            'statuses' => BusinessRelation::STATUSES,
            'paymentTermTypes' => BusinessRelationCreditTerm::PAYMENT_TERM_TYPES,
        ]);
    }

    public function update(Request $request, BusinessRelation $member)
    {
        if ($member->type !== 'member') {
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

        DB::transaction(function () use ($member, $validated) {
            $member->update($validated);

            if (!empty($validated['credit_terms'])) {
                $member->creditTerms()->updateOrCreate(
                    ['business_relation_id' => $member->id],
                    $validated['credit_terms']
                );
            }

            if (!empty($validated['tags'])) {
                $member->tags()->delete();
                foreach ($validated['tags'] as $tag) {
                    $member->tags()->create(['tag_name' => $tag]);
                }
            }

            if (!empty($validated['custom_fields'])) {
                $member->customFields()->delete();
                foreach ($validated['custom_fields'] as $field) {
                    $member->customFields()->create($field);
                }
            }

            $member->companies()->sync($validated['company_ids']);
        });

        return redirect()->route('members.edit', $member->id)
            ->with('success', 'Anggota berhasil diubah.');
    }

    public function destroy(Request $request, BusinessRelation $member)
    {
        if ($member->type !== 'member') {
            abort(404);
        }

        $member->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('members.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Anggota berhasil dihapus.');
        }

        return redirect()->route('members.index')
            ->with('success', 'Anggota berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        BusinessRelation::where('type', 'member')
            ->whereIn('id', $request->ids)
            ->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('members.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Anggota yang dipilih berhasil dihapus.');
        }
    }

    private function getFilteredMembers(Request $request)
    {
        $query = BusinessRelation::query()
            ->where('type', 'member')
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
        $members = $this->getFilteredMembers($request);
        return Excel::download(new MembersExport($members), 'members.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $members = $this->getFilteredMembers($request);
        return Excel::download(new MembersExport($members), 'members.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $members = $this->getFilteredMembers($request);
        return Excel::download(new MembersExport($members), 'members.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
} 