<?php

namespace App\Http\Controllers\Costing;

use App\Enums\CostEntrySource;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CostEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class CostEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('cost_entries.index_filters', []);
        Session::put('cost_entries.index_filters', $filters);

        $filterKeys = [
            'search',
            'company_id',
            'source_type',
            'from_date',
            'to_date',
            'per_page',
            'sort',
            'order',
        ];

        $filters = Arr::only($filters, $filterKeys);

        $query = CostEntry::query()
            ->with([
                'product:id,name',
                'company:id,name',
                'currency:id,code',
                'costPool:id,code,name',
            ]);

        if ($filters['search'] ?? null) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(description) like ?', ["%{$search}%"])
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->whereRaw('lower(name) like ?', ["%{$search}%"]);
                    });
            });
        }

        if ($companyIds = Arr::wrap($filters['company_id'] ?? [])) {
            $query->whereIn('company_id', array_filter($companyIds));
        }

        if ($sourceTypes = Arr::wrap($filters['source_type'] ?? [])) {
            $query->whereIn('source_type', array_filter($sourceTypes));
        }

        if ($filters['from_date'] ?? null) {
            $query->whereDate('cost_date', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->whereDate('cost_date', '<=', $filters['to_date']);
        }

        $sort = $filters['sort'] ?? 'cost_date';
        $order = $filters['order'] ?? 'desc';

        $allowedSorts = ['cost_date', 'amount_base', 'allocated_amount', 'source_type'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'cost_date';
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        $costEntries = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Costing/CostEntries/Index', [
            'costEntries' => $costEntries,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'companies' => $this->companyOptions(),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
        ]);
    }

    public function show(CostEntry $costEntry): Response
    {
        $costEntry->load([
            'product',
            'productVariant',
            'company',
            'currency',
            'costPool',
            'creator',
            'source',
            'costObject',
            'invoiceDetailCosts.salesInvoiceLine.salesInvoice',
        ]);

        return Inertia::render('Costing/CostEntries/Show', [
            'costEntry' => $costEntry,
            'filters' => Session::get('cost_entries.index_filters', []),
        ]);
    }

    private function companyOptions()
    {
        return Company::orderBy('name')->get(['id', 'name']);
    }

    private function sourceTypeOptions(): array
    {
        return collect(CostEntrySource::cases())
            ->mapWithKeys(fn (CostEntrySource $source) => [
                $source->value => $source->label(),
            ])
            ->toArray();
    }
}
