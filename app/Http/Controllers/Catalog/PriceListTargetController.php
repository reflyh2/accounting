<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PartnerGroup;
use App\Models\PriceList;
use App\Models\PriceListTarget;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PriceListTargetController extends Controller
{
    public function index(Request $request)
    {
        $targets = PriceListTarget::with(['priceList.currency', 'company', 'partner', 'partnerGroup'])
            ->orderBy('priority')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Catalog/PriceListTargets/Index', [
            'targets' => $targets,
            'filters' => $request->all(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Catalog/PriceListTargets/Form', [
            'mode' => 'create',
            'target' => null,
            'priceLists' => $this->priceListOptions(),
            'companies' => $this->companyOptions(),
            'partnerGroups' => $this->partnerGroupOptions(),
            'partnerDisplay' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        PriceListTarget::create($data);

        return redirect()->route('catalog.price-list-targets.index')
            ->with('success', 'Price list target created.');
    }

    public function edit(PriceListTarget $priceListTarget)
    {
        $priceListTarget->load(['priceList', 'company', 'partner', 'partnerGroup']);

        return Inertia::render('Catalog/PriceListTargets/Form', [
            'mode' => 'edit',
            'target' => $priceListTarget,
            'priceLists' => $this->priceListOptions(),
            'companies' => $this->companyOptions(),
            'partnerGroups' => $this->partnerGroupOptions(),
            'partnerDisplay' => $priceListTarget->partner?->name,
        ]);
    }

    public function update(Request $request, PriceListTarget $priceListTarget)
    {
        $data = $this->validatePayload($request);
        $priceListTarget->update($data);

        return redirect()->route('catalog.price-list-targets.index')
            ->with('success', 'Price list target updated.');
    }

    public function destroy(PriceListTarget $priceListTarget)
    {
        $priceListTarget->delete();

        return redirect()->route('catalog.price-list-targets.index')
            ->with('success', 'Price list target deleted.');
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

        if ($data['partner_id'] ?? false && $data['partner_group_id'] ?? false) {
            abort(422, 'Cannot target both a partner and partner group simultaneously.');
        }

        $data['priority'] = $data['priority'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        if (!empty($data['company_id'])) {
            $priceListCompany = PriceList::where('id', $data['price_list_id'])->value('company_id');
            if ($priceListCompany && (int) $priceListCompany !== (int) $data['company_id']) {
                abort(422, 'Price list and target company must match.');
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

