<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\ResourcePool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class ResourcePoolController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('resource_pools.index_filters', []);
        Session::put('resource_pools.index_filters', $filters);

        $filterKeys = ['search', 'branch_id', 'is_active', 'per_page', 'sort', 'order'];
        $filters = Arr::only($filters, $filterKeys);

        $query = ResourcePool::query()
            ->with([
                'product:id,name,code',
                'branch:id,name,branch_group_id',
                'branch.branchGroup:id,company_id',
            ])
            ->withCount('instances');

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(name) like ?', ["%{$search}%"])
                    ->orWhereHas('product', fn ($pq) => $pq->whereRaw('lower(name) like ?', ["%{$search}%"]));
            });
        }

        if ($branchIds = Arr::wrap($filters['branch_id'] ?? [])) {
            $query->whereIn('branch_id', array_filter($branchIds));
        }

        if (($filters['is_active'] ?? null) !== null && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $sort = $filters['sort'] ?? 'name';
        $order = $filters['order'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $pools = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('ResourcePools/Index', [
            'resourcePools' => $pools,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'branches' => $this->branchOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ResourcePools/Create', [
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'default_capacity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $pool = ResourcePool::create($data);

        return Redirect::route('resource-pools.show', $pool->id)
            ->with('success', 'Resource Pool berhasil dibuat.');
    }

    public function show(ResourcePool $resourcePool): Response
    {
        $resourcePool->load([
            'product',
            'branch.branchGroup.company',
            'instances',
        ]);

        return Inertia::render('ResourcePools/Show', [
            'resourcePool' => $resourcePool,
            'filters' => Session::get('resource_pools.index_filters', []),
        ]);
    }

    public function edit(ResourcePool $resourcePool): Response
    {
        return Inertia::render('ResourcePools/Edit', [
            'resourcePool' => $resourcePool,
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function update(Request $request, ResourcePool $resourcePool): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'default_capacity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $resourcePool->update($data);

        return Redirect::route('resource-pools.show', $resourcePool->id)
            ->with('success', 'Resource Pool berhasil diperbarui.');
    }

    public function destroy(ResourcePool $resourcePool): RedirectResponse
    {
        if ($resourcePool->instances()->exists()) {
            return Redirect::back()->with('error', 'Tidak dapat menghapus pool yang masih memiliki instance.');
        }

        $resourcePool->delete();

        return Redirect::route('resource-pools.index')
            ->with('success', 'Resource Pool berhasil dihapus.');
    }

    private function branchOptions(): array
    {
        return Branch::with('branchGroup:id,company_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'company_id' => $branch->branchGroup?->company_id,
            ])
            ->toArray();
    }

    private function formOptions(): array
    {
        return [
            'branches' => $this->branchOptions(),
            'products' => Product::query()
                ->whereHas('capabilities', fn ($q) => $q->where('capability', 'bookable'))
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Product $p) => [
                    'value' => $p->id,
                    'label' => "{$p->code} — {$p->name}",
                ])
                ->toArray(),
        ];
    }
}
