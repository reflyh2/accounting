<?php

namespace App\Http\Controllers;

use App\Models\ResourceInstance;
use App\Models\ResourcePool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class ResourceInstanceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('resource_instances.index_filters', []);
        Session::put('resource_instances.index_filters', $filters);

        $filterKeys = ['search', 'resource_pool_id', 'status', 'per_page', 'sort', 'order'];
        $filters = Arr::only($filters, $filterKeys);

        $query = ResourceInstance::query()
            ->with([
                'pool:id,name,product_id',
                'pool.product:id,name,code',
                'asset:id,code,name',
            ]);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->whereRaw('lower(code) like ?', ["%{$search}%"]);
        }

        if ($poolIds = Arr::wrap($filters['resource_pool_id'] ?? [])) {
            $query->whereIn('resource_pool_id', array_filter($poolIds));
        }

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        $sort = $filters['sort'] ?? 'code';
        $order = $filters['order'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $instances = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('ResourceInstances/Index', [
            'resourceInstances' => $instances,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'resourcePools' => $this->poolOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('ResourceInstances/Create', [
            'formOptions' => $this->formOptions(),
            'preselectedPoolId' => $request->query('pool_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'resource_pool_id' => ['required', 'exists:resource_pools,id'],
            'code' => ['required', 'string', 'max:80', 'unique:resource_instances,code'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'status' => ['required', 'in:active,maintenance,retired'],
            'attrs_json' => ['nullable', 'array'],
        ]);

        $instance = ResourceInstance::create($data);

        return Redirect::route('resource-pools.show', $instance->resource_pool_id)
            ->with('success', 'Resource Instance berhasil dibuat.');
    }

    public function edit(ResourceInstance $resourceInstance): Response
    {
        $resourceInstance->load('pool');

        return Inertia::render('ResourceInstances/Edit', [
            'resourceInstance' => $resourceInstance,
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function update(Request $request, ResourceInstance $resourceInstance): RedirectResponse
    {
        $data = $request->validate([
            'resource_pool_id' => ['required', 'exists:resource_pools,id'],
            'code' => ['required', 'string', 'max:80', 'unique:resource_instances,code,' . $resourceInstance->id],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'status' => ['required', 'in:active,maintenance,retired'],
            'attrs_json' => ['nullable', 'array'],
        ]);

        $resourceInstance->update($data);

        return Redirect::route('resource-pools.show', $resourceInstance->resource_pool_id)
            ->with('success', 'Resource Instance berhasil diperbarui.');
    }

    public function destroy(ResourceInstance $resourceInstance): RedirectResponse
    {
        $poolId = $resourceInstance->resource_pool_id;

        if ($resourceInstance->bookingLines()->exists()) {
            return Redirect::back()->with('error', 'Tidak dapat menghapus instance yang sudah memiliki booking.');
        }

        $resourceInstance->delete();

        return Redirect::route('resource-pools.show', $poolId)
            ->with('success', 'Resource Instance berhasil dihapus.');
    }

    private function poolOptions(): array
    {
        return ResourcePool::with('product:id,name,code')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ResourcePool $pool) => [
                'id' => $pool->id,
                'name' => $pool->name,
                'product_name' => $pool->product?->name,
            ])
            ->toArray();
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Aktif'],
            ['value' => 'maintenance', 'label' => 'Maintenance'],
            ['value' => 'retired', 'label' => 'Tidak Aktif'],
        ];
    }

    private function formOptions(): array
    {
        return [
            'resourcePools' => ResourcePool::with('product:id,name,code')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (ResourcePool $pool) => [
                    'value' => $pool->id,
                    'label' => "{$pool->name} ({$pool->product?->name})",
                ])
                ->toArray(),
            'statusOptions' => $this->statusOptions(),
            'assets' => \App\Models\Asset::orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn ($a) => [
                    'value' => $a->id,
                    'label' => "{$a->code} — {$a->name}",
                ])
                ->toArray(),
        ];
    }
}
