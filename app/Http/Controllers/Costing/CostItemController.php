<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Company;
use App\Models\CostItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class CostItemController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->all() ?: Session::get('cost_items.index_filters', []);
        Session::put('cost_items.index_filters', $filters);

        $query = CostItem::with([
            'company:id,name',
            'debitAccount:id,code,name',
            'creditAccount:id,code,name',
        ]);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('lower(code) like ?', ["%{$search}%"])
                    ->orWhereRaw('lower(name) like ?', ["%{$search}%"]);
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active'] === 'true' || $filters['is_active'] === '1');
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $sort = $filters['sort'] ?? 'name';
        $order = $filters['order'] ?? 'asc';

        $costItems = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Costing/CostItems/Index', [
            'costItems' => $costItems,
            'filters' => $filters,
            'perPage' => $perPage,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Costing/CostItems/Create', [
            'filters' => Session::get('cost_items.index_filters', []),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'required|string|max:20|unique:cost_items,code,NULL,id,company_id,' . $request->company_id,
            'name' => 'required|string|max:100',
            'debit_account_id' => 'required|exists:accounts,id',
            'credit_account_id' => 'required|exists:accounts,id',
            'is_active' => 'boolean',
        ]);

        $costItem = CostItem::create($data);

        if ($request->boolean('create_another')) {
            return Redirect::route('costing.cost-items.create')
                ->with('success', 'Cost Item berhasil dibuat.');
        }

        return Redirect::route('costing.cost-items.show', $costItem)
            ->with('success', 'Cost Item berhasil dibuat.');
    }

    public function show(CostItem $costItem): Response
    {
        $costItem->load(['company', 'debitAccount', 'creditAccount']);

        return Inertia::render('Costing/CostItems/Show', [
            'costItem' => $costItem,
            'filters' => Session::get('cost_items.index_filters', []),
        ]);
    }

    public function edit(CostItem $costItem): Response
    {
        $costItem->load(['company', 'debitAccount', 'creditAccount']);

        return Inertia::render('Costing/CostItems/Edit', [
            'costItem' => $costItem,
            'filters' => Session::get('cost_items.index_filters', []),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, CostItem $costItem): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'required|string|max:20|unique:cost_items,code,' . $costItem->id . ',id,company_id,' . $request->company_id,
            'name' => 'required|string|max:100',
            'debit_account_id' => 'required|exists:accounts,id',
            'credit_account_id' => 'required|exists:accounts,id',
            'is_active' => 'boolean',
        ]);

        $costItem->update($data);

        return Redirect::route('costing.cost-items.show', $costItem)
            ->with('success', 'Cost Item berhasil diperbarui.');
    }

    public function destroy(CostItem $costItem): RedirectResponse
    {
        // Check if used in any SO/SI costs
        if (\App\Models\SalesOrderCost::where('cost_item_id', $costItem->id)->exists() ||
            \App\Models\SalesInvoiceCost::where('cost_item_id', $costItem->id)->exists()) {
            return Redirect::back()
                ->with('error', 'Cost Item tidak dapat dihapus karena sudah digunakan.');
        }

        $costItem->delete();

        return Redirect::route('costing.cost-items.index')
            ->with('success', 'Cost Item berhasil dihapus.');
    }

    private function formOptions(): array
    {
        return [
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'accounts' => Account::with('companies:id')
                ->where('is_parent', false)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'code' => $a->code,
                    'name' => $a->name,
                    'company_ids' => $a->companies->pluck('id')->toArray(),
                    'label' => "{$a->code} - {$a->name}",
                ]),
        ];
    }
}
