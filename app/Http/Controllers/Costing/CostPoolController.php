<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Http\Requests\CostPoolRequest;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class CostPoolController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('cost_pools.index_filters', []);
        Session::put('cost_pools.index_filters', $filters);

        $filterKeys = [
            'search',
            'company_id',
            'pool_type',
            'is_active',
            'per_page',
            'sort',
            'order',
        ];

        $filters = Arr::only($filters, $filterKeys);

        $query = CostPool::query()
            ->with([
                'company:id,name',
            ])
            ->withCount(['costEntries', 'allocations']);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(code) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(name) like ?', ["%{$search}%"]);
            });
        }

        if ($companyIds = Arr::wrap($filters['company_id'] ?? [])) {
            $query->whereIn('company_id', array_filter($companyIds));
        }

        if ($poolTypes = Arr::wrap($filters['pool_type'] ?? [])) {
            $query->whereIn('pool_type', array_filter($poolTypes));
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active'] === 'true' || $filters['is_active'] === '1');
        }

        $sort = $filters['sort'] ?? 'created_at';
        $order = $filters['order'] ?? 'desc';

        $allowedSorts = ['code', 'name', 'accumulated_amount', 'allocated_amount', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        $costPools = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Costing/CostPools/Index', [
            'costPools' => $costPools,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'companies' => $this->companyOptions(),
            'poolTypeOptions' => $this->poolTypeOptions(),
            'activeOptions' => $this->activeOptions(),
        ]);
    }

    public function create(): Response
    {
        $filters = Session::get('cost_pools.index_filters', []);
        $formOptions = $this->formOptions();

        return Inertia::render('Costing/CostPools/Create', [
            'filters' => $filters,
            'companies' => $formOptions['companies'],
            'branches' => $formOptions['branches'],
            'assets' => $formOptions['assets'],
            'poolTypeOptions' => $this->poolTypeOptions(),
            'allocationRuleOptions' => $this->allocationRuleOptions(),
        ]);
    }

    public function store(CostPoolRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['accumulated_amount'] = 0;
        $data['allocated_amount'] = 0;

        $costPool = CostPool::create($data);

        if ($request->input('create_another', false)) {
            return Redirect::route('costing.cost-pools.create')
                ->with('success', 'Pool Biaya berhasil dibuat. Silahkan buat lagi.');
        }

        return Redirect::route('costing.cost-pools.show', $costPool->id)
            ->with('success', 'Pool Biaya berhasil dibuat.');
    }

    public function show(CostPool $costPool): Response
    {
        $costPool->load([
            'company',
            'asset',
            'branch',
            'creator',
            'updatedBy',
            'costEntries' => fn ($q) => $q->with('product:id,name', 'currency:id,code')->latest('cost_date')->limit(20),
            'allocations' => fn ($q) => $q->with('salesInvoiceLine.salesInvoice:id,invoice_number', 'creator:id,name')->latest()->limit(20),
        ]);

        return Inertia::render('Costing/CostPools/Show', [
            'costPool' => $costPool,
            'filters' => Session::get('cost_pools.index_filters', []),
        ]);
    }

    public function edit(CostPool $costPool): Response
    {
        $costPool->load(['company', 'asset', 'branch']);

        $filters = Session::get('cost_pools.index_filters', []);
        $formOptions = $this->formOptions();

        return Inertia::render('Costing/CostPools/Edit', [
            'costPool' => $costPool,
            'filters' => $filters,
            'companies' => $formOptions['companies'],
            'branches' => $formOptions['branches'],
            'assets' => $formOptions['assets'],
            'poolTypeOptions' => $this->poolTypeOptions(),
            'allocationRuleOptions' => $this->allocationRuleOptions(),
        ]);
    }

    public function update(CostPoolRequest $request, CostPool $costPool): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        $costPool->update($data);

        return Redirect::route('costing.cost-pools.show', $costPool->id)
            ->with('success', 'Pool Biaya berhasil diperbarui.');
    }

    public function destroy(CostPool $costPool): RedirectResponse
    {
        if ($costPool->costEntries()->exists() || $costPool->allocations()->exists()) {
            return Redirect::back()->with('error', 'Pool Biaya tidak dapat dihapus karena memiliki catatan biaya atau alokasi.');
        }

        $costPool->delete();

        return Redirect::route('costing.cost-pools.index')
            ->with('success', 'Pool Biaya berhasil dihapus.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'exists:cost_pools,id'],
        ]);

        try {
            DB::transaction(function () use ($request) {
                foreach ($request->ids as $id) {
                    $costPool = CostPool::find($id);
                    if ($costPool && !$costPool->costEntries()->exists() && !$costPool->allocations()->exists()) {
                        $costPool->delete();
                    }
                }
            });
        } catch (\Exception $e) {
            return Redirect::back()->with('error', 'Terjadi kesalahan saat menghapus data.');
        }

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('costing.cost-pools.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Pool Biaya berhasil dihapus.');
        }

        return Redirect::route('costing.cost-pools.index')
            ->with('success', 'Pool Biaya berhasil dihapus.');
    }

    private function companyOptions()
    {
        return Company::orderBy('name')->get(['id', 'name']);
    }

    private function formOptions(): array
    {
        return [
            'companies' => $this->companyOptions(),
            'branches' => Branch::with('branchGroup:id,company_id')
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'company_id' => $branch->branchGroup?->company_id,
                ]),
            'assets' => Asset::orderBy('name')
                ->get(['id', 'name', 'code', 'company_id'])
                ->map(fn ($asset) => [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'code' => $asset->code,
                    'company_id' => $asset->company_id,
                    'label' => "{$asset->code} - {$asset->name}",
                ]),
        ];
    }

    private function poolTypeOptions(): array
    {
        return [
            'asset' => 'Aset',
            'service' => 'Layanan',
            'branch' => 'Cabang',
        ];
    }

    private function allocationRuleOptions(): array
    {
        return [
            'revenue_based' => 'Berdasarkan Revenue',
            'quantity_based' => 'Berdasarkan Quantity',
            'time_based' => 'Berdasarkan Waktu',
            'manual' => 'Manual',
        ];
    }

    private function activeOptions(): array
    {
        return [
            ['value' => 'true', 'label' => 'Aktif'],
            ['value' => 'false', 'label' => 'Non-aktif'],
        ];
    }
}
