<?php

namespace App\Http\Controllers;

use App\Enums\AccountingEventCode;
use App\Exports\GlEventConfigurationsExport;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GlEventConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class GlEventConfigurationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('gl-event-configurations.index_filters', []);
        Session::put('gl-event-configurations.index_filters', $filters);

        $query = GlEventConfiguration::with(['company', 'branch', 'lines.account']);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(event_code)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhere(DB::raw('lower(description)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhereHas('company', function ($q) use ($filters) {
                        $q->where(DB::raw('lower(name)'), 'like', '%'.strtolower($filters['search']).'%');
                    })
                    ->orWhereHas('branch', function ($q) use ($filters) {
                        $q->where(DB::raw('lower(name)'), 'like', '%'.strtolower($filters['search']).'%');
                    });
            });
        }

        if (! empty($filters['company_id'])) {
            $query->whereIn('company_id', $filters['company_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (! empty($filters['event_code'])) {
            $query->whereIn('event_code', $filters['event_code']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $configurations = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (! empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        $eventCodes = collect(AccountingEventCode::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ]);

        return Inertia::render('GlEventConfigurations/Index', [
            'configurations' => $configurations,
            'companies' => $companies,
            'branches' => $branches,
            'eventCodes' => $eventCodes,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('gl-event-configurations.index_filters', []);

        $eventCodes = collect(AccountingEventCode::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ]);

        return Inertia::render('GlEventConfigurations/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => fn () => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'accounts' => fn () => Account::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->where('is_parent', false)->orderBy('code', 'asc')->get(),
            'eventCodes' => $eventCodes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'event_code' => 'required|string|in:'.implode(',', array_column(AccountingEventCode::cases(), 'value')),
            'is_active' => 'boolean',
            'description' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.role' => 'required|string|max:100',
            'lines.*.direction' => 'required|in:debit,credit',
            'lines.*.account_id' => 'required|exists:accounts,id',
        ]);

        // Validate that debit and credit entries balance
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($validated['lines'] as $line) {
            if ($line['direction'] === 'debit') {
                $totalDebit += 1; // Count entries, not amounts
            } else {
                $totalCredit += 1;
            }
        }

        // At minimum, we need at least one debit and one credit
        if ($totalDebit === 0 || $totalCredit === 0) {
            return redirect()->back()->withErrors(['lines' => 'Konfigurasi harus memiliki setidaknya satu entri debit dan satu entri kredit.']);
        }

        $configuration = DB::transaction(function () use ($validated) {
            dd($validated);
            $configuration = GlEventConfiguration::create([
                'company_id' => $validated['company_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'event_code' => $validated['event_code'],
                'is_active' => $validated['is_active'] ?? true,
                'description' => $validated['description'] ?? null,
            ]);

            foreach ($validated['lines'] as $line) {
                $configuration->lines()->create([
                    'role' => $line['role'],
                    'direction' => $line['direction'],
                    'account_id' => $line['account_id'],
                ]);
            }

            return $configuration;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('gl-event-configurations.create')
                ->with('success', 'Konfigurasi GL Event berhasil dibuat. Silakan buat konfigurasi lainnya.');
        }

        return redirect()->route('gl-event-configurations.show', $configuration->id)
            ->with('success', 'Konfigurasi GL Event berhasil dibuat.');
    }

    public function show(Request $request, GlEventConfiguration $glEventConfiguration)
    {
        $filters = Session::get('gl-event-configurations.index_filters', []);
        $glEventConfiguration->load(['company', 'branch', 'lines.account']);

        return Inertia::render('GlEventConfigurations/Show', [
            'configuration' => $glEventConfiguration,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, GlEventConfiguration $glEventConfiguration)
    {
        $filters = Session::get('gl-event-configurations.index_filters', []);
        $glEventConfiguration->load(['company', 'branch', 'lines.account']);

        $companyId = $glEventConfiguration->company_id;

        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        $eventCodes = collect(AccountingEventCode::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ]);

        return Inertia::render('GlEventConfigurations/Edit', [
            'configuration' => $glEventConfiguration,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'accounts' => Account::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->where('is_parent', false)->orderBy('code', 'asc')->get(),
            'eventCodes' => $eventCodes,
        ]);
    }

    public function update(Request $request, GlEventConfiguration $glEventConfiguration)
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'event_code' => 'required|string|in:'.implode(',', array_column(AccountingEventCode::cases(), 'value')),
            'is_active' => 'boolean',
            'description' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.id' => 'nullable|exists:gl_event_configuration_lines,id',
            'lines.*.role' => 'required|string|max:100',
            'lines.*.direction' => 'required|in:debit,credit',
            'lines.*.account_id' => 'required|exists:accounts,id',
        ]);

        // Validate that debit and credit entries balance
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($validated['lines'] as $line) {
            if ($line['direction'] === 'debit') {
                $totalDebit += 1;
            } else {
                $totalCredit += 1;
            }
        }

        if ($totalDebit === 0 || $totalCredit === 0) {
            return redirect()->back()->withErrors(['lines' => 'Konfigurasi harus memiliki setidaknya satu entri debit dan satu entri kredit.']);
        }

        DB::transaction(function () use ($validated, $glEventConfiguration) {
            $glEventConfiguration->update([
                'company_id' => $validated['company_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'event_code' => $validated['event_code'],
                'is_active' => $validated['is_active'] ?? true,
                'description' => $validated['description'] ?? null,
            ]);

            // Delete existing lines
            foreach ($glEventConfiguration->lines as $line) {
                $line->delete();
            }

            // Create new lines
            foreach ($validated['lines'] as $line) {
                $glEventConfiguration->lines()->create([
                    'role' => $line['role'],
                    'direction' => $line['direction'],
                    'account_id' => $line['account_id'],
                ]);
            }
        });

        return redirect()->route('gl-event-configurations.edit', $glEventConfiguration->id)
            ->with('success', 'Konfigurasi GL Event berhasil diubah.');
    }

    public function destroy(Request $request, GlEventConfiguration $glEventConfiguration)
    {
        DB::transaction(function () use ($glEventConfiguration) {
            foreach ($glEventConfiguration->lines as $line) {
                $line->delete();
            }
            $glEventConfiguration->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('gl-event-configurations.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Konfigurasi GL Event berhasil dihapus.');
        } else {
            return Redirect::route('gl-event-configurations.index')
                ->with('success', 'Konfigurasi GL Event berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $configuration = GlEventConfiguration::find($id);
                foreach ($configuration->lines as $line) {
                    $line->delete();
                }
                $configuration->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('gl-event-configurations.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Konfigurasi GL Event berhasil dihapus.');
        }
    }

    private function getFilteredConfigurations(Request $request)
    {
        $filters = $request->all() ?: Session::get('gl-event-configurations.index_filters', []);

        $query = GlEventConfiguration::with(['company', 'branch', 'lines.account']);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(event_code)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhere(DB::raw('lower(description)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhereHas('company', function ($q) use ($filters) {
                        $q->where(DB::raw('lower(name)'), 'like', '%'.strtolower($filters['search']).'%');
                    })
                    ->orWhereHas('branch', function ($q) use ($filters) {
                        $q->where(DB::raw('lower(name)'), 'like', '%'.strtolower($filters['search']).'%');
                    });
            });
        }

        if (! empty($filters['company_id'])) {
            $query->whereIn('company_id', $filters['company_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (! empty($filters['event_code'])) {
            $query->whereIn('event_code', $filters['event_code']);
        }

        $sortColumn = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $configurations = $this->getFilteredConfigurations($request);

        return Excel::download(new GlEventConfigurationsExport($configurations), 'gl-event-configurations.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $configurations = $this->getFilteredConfigurations($request);

        return Excel::download(new GlEventConfigurationsExport($configurations), 'gl-event-configurations.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $configurations = $this->getFilteredConfigurations($request);

        return Excel::download(new GlEventConfigurationsExport($configurations), 'gl-event-configurations.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
