<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Models\CostAllocation;
use App\Models\CostPool;
use App\Models\SalesInvoiceLine;
use App\Services\Costing\CostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class CostAllocationController extends Controller
{
    public function __construct(
        private readonly CostingService $costingService
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('cost_allocations.index_filters', []);
        Session::put('cost_allocations.index_filters', $filters);

        $filterKeys = [
            'search',
            'cost_pool_id',
            'period',
            'allocation_rule',
            'per_page',
            'sort',
            'order',
        ];

        $filters = Arr::only($filters, $filterKeys);

        $query = CostAllocation::query()
            ->with([
                'costPool:id,code,name',
                'salesInvoiceLine:id,sales_invoice_id',
                'salesInvoiceLine.salesInvoice:id,invoice_number,partner_id',
                'salesInvoiceLine.salesInvoice.partner:id,name',
            ]);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('costPool', function ($pq) use ($search) {
                    $pq->whereRaw('lower(name) like ?', ["%{$search}%"])
                        ->orWhereRaw('lower(code) like ?', ["%{$search}%"]);
                });
            });
        }

        if ($costPoolIds = Arr::wrap($filters['cost_pool_id'] ?? [])) {
            $query->whereIn('cost_pool_id', array_filter($costPoolIds));
        }

        if ($filters['period'] ?? null) {
            $query->where('period', $filters['period']);
        }

        if ($allocationRules = Arr::wrap($filters['allocation_rule'] ?? [])) {
            $query->whereIn('allocation_rule', array_filter($allocationRules));
        }

        $sort = $filters['sort'] ?? 'created_at';
        $order = $filters['order'] ?? 'desc';

        $allowedSorts = ['amount', 'period', 'created_at', 'allocation_ratio'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        $allocations = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Costing/CostAllocations/Index', [
            'allocations' => $allocations,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'costPoolOptions' => $this->costPoolOptions(),
            'allocationRuleOptions' => $this->allocationRuleOptions(),
        ]);
    }

    public function show(CostAllocation $costAllocation): Response
    {
        $costAllocation->load([
            'costPool.company',
            'salesInvoiceLine.salesInvoice.partner',
            'salesInvoiceLine.product',
            'creator',
        ]);

        return Inertia::render('Costing/CostAllocations/Show', [
            'allocation' => $costAllocation,
            'filters' => Session::get('cost_allocations.index_filters', []),
        ]);
    }

    public function batch(): Response
    {
        $costPools = CostPool::where('is_active', true)
            ->with('company:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($pool) => [
                'id' => $pool->id,
                'code' => $pool->code,
                'name' => $pool->name,
                'company_name' => $pool->company?->name,
                'unallocated' => $pool->accumulated_amount - $pool->allocated_amount,
                'allocation_rule' => $pool->allocation_rule,
            ]);

        return Inertia::render('Costing/CostAllocations/Batch', [
            'costPools' => $costPools,
            'allocationRuleOptions' => $this->allocationRuleOptions(),
        ]);
    }

    public function runBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cost_pool_id' => ['required', 'exists:cost_pools,id'],
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'allocation_rule' => ['required', 'string', 'in:revenue_based,quantity_based,time_based,manual'],
        ]);

        $costPool = CostPool::findOrFail($validated['cost_pool_id']);

        $unallocatedAmount = $costPool->accumulated_amount - $costPool->allocated_amount;

        if ($unallocatedAmount <= 0) {
            return Redirect::back()->with('error', 'Pool ini tidak memiliki biaya yang belum dialokasikan.');
        }

        // Get sales invoice lines for the period that can receive allocations
        $periodStart = $validated['period'] . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        $invoiceLines = SalesInvoiceLine::whereHas('salesInvoice', function ($q) use ($periodStart, $periodEnd, $costPool) {
            $q->whereBetween('invoice_date', [$periodStart, $periodEnd])
                ->where('company_id', $costPool->company_id);
        })
            ->with('salesInvoice:id,total_base')
            ->get();

        if ($invoiceLines->isEmpty()) {
            return Redirect::back()->with('error', 'Tidak ada faktur penjualan pada periode ini untuk perusahaan terkait.');
        }

        // Calculate allocation based on rule
        $totalDenominator = match ($validated['allocation_rule']) {
            'revenue_based' => $invoiceLines->sum(fn ($line) => $line->salesInvoice?->total_base ?? 0),
            'quantity_based' => $invoiceLines->sum('quantity'),
            default => $invoiceLines->count(),
        };

        if ($totalDenominator <= 0) {
            return Redirect::back()->with('error', 'Tidak dapat menghitung alokasi: denominator adalah 0.');
        }

        $allocationsCreated = 0;

        foreach ($invoiceLines as $line) {
            $numerator = match ($validated['allocation_rule']) {
                'revenue_based' => $line->salesInvoice?->total_base ?? 0,
                'quantity_based' => $line->quantity,
                default => 1,
            };

            $ratio = $numerator / $totalDenominator;
            $amount = round($unallocatedAmount * $ratio, 2);

            if ($amount > 0) {
                $this->costingService->allocateFromPool(
                    $costPool,
                    $line,
                    $amount,
                    $validated['allocation_rule'],
                    $validated['period'],
                    $numerator,
                    $totalDenominator
                );
                $allocationsCreated++;
            }
        }

        return Redirect::route('costing.cost-allocations.index')
            ->with('success', "Berhasil membuat {$allocationsCreated} alokasi biaya untuk periode {$validated['period']}.");
    }

    private function costPoolOptions()
    {
        return CostPool::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($pool) => [
                'id' => $pool->id,
                'code' => $pool->code,
                'name' => $pool->name,
                'label' => "{$pool->code} - {$pool->name}",
            ]);
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
}
