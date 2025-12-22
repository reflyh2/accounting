<?php

namespace App\Http\Controllers\Catalog;

use Inertia\Inertia;
use App\Models\Company;
use App\Models\PartnerGroup;
use App\Models\PriceList;
use App\Models\PriceListTarget;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class PriceListTargetController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('price-list-targets.index_filters', []);
        Session::put('price-list-targets.index_filters', $filters);

        $query = PriceListTarget::with(['priceList.currency', 'company', 'partner', 'partnerGroup']);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('priceList', function ($q) use ($search) {
                    $q->where(DB::raw('lower(name)'), 'like', '%' . $search . '%')
                      ->orWhere(DB::raw('lower(code)'), 'like', '%' . $search . '%');
                });
            });
        }

        if (!empty($filters['price_list_id'])) {
            $query->whereIn('price_list_id', (array) $filters['price_list_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active'] === '1');
        }

        $perPage = $filters['per_page'] ?? 15;
        $sortColumn = $filters['sort'] ?? 'priority';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $targets = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('Catalog/PriceListTargets/Index', [
            'targets' => $targets,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'priceLists' => PriceList::orderBy('name')->get(['id', 'name', 'code']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('price-list-targets.index_filters', []);

        return Inertia::render('Catalog/PriceListTargets/Create', [
            'filters' => $filters,
            'priceLists' => $this->priceListOptions(),
            'companies' => $this->companyOptions(),
            'partnerGroups' => $this->partnerGroupOptions(),
            'channels' => PriceListController::getChannels(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $data['created_by'] = $request->user()->global_id;
        
        PriceListTarget::create($data);

        return redirect()->route('catalog.price-list-targets.index')
            ->with('success', 'Target harga berhasil dibuat.');
    }

    public function edit(Request $request, PriceListTarget $priceListTarget)
    {
        $filters = Session::get('price-list-targets.index_filters', []);
        $priceListTarget->load(['priceList', 'company', 'partner', 'partnerGroup']);

        return Inertia::render('Catalog/PriceListTargets/Edit', [
            'target' => $priceListTarget,
            'filters' => $filters,
            'priceLists' => $this->priceListOptions(),
            'companies' => $this->companyOptions(),
            'partnerGroups' => $this->partnerGroupOptions(),
            'partnerDisplay' => $priceListTarget->partner?->name,
            'channels' => PriceListController::getChannels(),
        ]);
    }

    public function update(Request $request, PriceListTarget $priceListTarget)
    {
        $data = $this->validatePayload($request);
        $data['updated_by'] = $request->user()->global_id;
        
        $priceListTarget->update($data);

        return redirect()->route('catalog.price-list-targets.edit', $priceListTarget->id)
            ->with('success', 'Target harga berhasil diperbarui.');
    }

    public function destroy(Request $request, PriceListTarget $priceListTarget)
    {
        $priceListTarget->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('catalog.price-list-targets.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Target harga berhasil dihapus.');
        }

        return Redirect::route('catalog.price-list-targets.index')
            ->with('success', 'Target harga berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $target = PriceListTarget::find($id);
                if ($target) {
                    $target->delete();
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('catalog.price-list-targets.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Target harga berhasil dihapus.');
        }

        return Redirect::route('catalog.price-list-targets.index')
            ->with('success', 'Target harga berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'price_list_id' => ['required', 'exists:price_lists,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'partner_group_id' => ['nullable', 'exists:partner_groups,id'],
            'channel' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        if (($data['partner_id'] ?? false) && ($data['partner_group_id'] ?? false)) {
            abort(422, 'Tidak dapat menargetkan partner dan grup partner sekaligus.');
        }

        $data['priority'] = $data['priority'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        if (!empty($data['company_id'])) {
            $priceListCompany = PriceList::where('id', $data['price_list_id'])->value('company_id');
            if ($priceListCompany && (int) $priceListCompany !== (int) $data['company_id']) {
                abort(422, 'Perusahaan daftar harga dan target harus sama.');
            }
        }

        return $data;
    }

    private function priceListOptions()
    {
        return PriceList::with('currency')
            ->orderBy('name')
            ->get()
            ->map(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'code' => $list->code,
                'company_id' => $list->company_id,
                'currency' => $list->currency?->code,
            ]);
    }

    private function companyOptions()
    {
        return Company::orderBy('name')
            ->get(['id', 'name']);
    }

    private function partnerGroupOptions()
    {
        return PartnerGroup::orderBy('name')
            ->get(['id', 'name']);
    }
}
