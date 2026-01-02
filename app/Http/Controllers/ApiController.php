<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\Account;
use App\Models\AssetFinancingSchedule;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use App\Enums\Documents\PurchasePlanStatus;
use App\Models\Product;
use App\Models\PurchasePlan;
use App\Models\Uom;

class ApiController extends Controller
{
    public function getBranchesByCompany($companyId)
    {
        $branches = Branch::whereHas('branchGroup', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->get();
        return response()->json(['availableBranches' => $branches]);
    }

    public function getAccountsByBranch($branchId)
    {
        $accounts = Account::where('branch_id', $branchId)->get();
        return response()->json($accounts);
    }

    public function getFinancingSchedule(Request $request)
    {
        $request->validate([
            'agreement_id' => 'required|exists:asset_financing_agreements,id',
            'payment_date' => 'required|date',
        ]);

        $paymentDate = Carbon::parse($request->payment_date);

        $schedule = AssetFinancingSchedule::where('asset_financing_agreement_id', $request->agreement_id)
            ->whereYear('payment_date', $paymentDate->year)
            ->whereMonth('payment_date', $paymentDate->month)
            ->first();

        if ($schedule) {
            $unpaidPrincipal = $schedule->principal_amount - $schedule->paid_principal_amount;
            $unpaidInterest = $schedule->interest_amount - $schedule->paid_interest_amount;

            return response()->json([
                'id' => $schedule->id,
                'principal_amount' => $unpaidPrincipal,
                'interest_amount' => $unpaidInterest,
            ]);
        }

        return response()->json(null);
    }

    public function getPartners(Request $request)
    {
        $query = Partner::query();

        $companyId = $request->company_id ?? 0;

        $query->whereHas('companies', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        });

        if ($request->search) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(name)'), 'like', "%{$search}%")
                    ->orWhere(DB::raw('lower(code)'), 'like', "%{$search}%");
            });
        }

        if ($request->roles) {
            $query->whereHas('roles', function ($query) use ($request) {
                $query->whereIn('role', $request->roles);
            });
        }

        $sort = $request->input('sort', 'name');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);
        
        return $query->paginate($request->input('per_page', 10))->withQueryString();
    }

    public function getPartner(Partner $partner)
    {
        return response()->json($partner);
    }

    public function getProducts(Request $request)
    {
        $query = Product::query()->with(['variants.uom:id,code,name', 'category:id,name', 'defaultUom:id,code,name']);

        $companyId = $request->company_id ?? 0;

        if ($companyId) {
            $query->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        if ($request->search) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(name)'), 'like', "%{$search}%")
                    ->orWhere(DB::raw('lower(sku)'), 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vq) use ($search) {
                        $vq->where(DB::raw('lower(sku)'), 'like', "%{$search}%")
                            ->orWhere(DB::raw('lower(barcode)'), 'like', "%{$search}%");
                    });
            });
        }

        $sort = $request->input('sort', 'name');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);

        $products = $query->paginate($request->input('per_page', 10))->withQueryString();

        // Transform to include sku from first variant for display
        $products->getCollection()->transform(function ($product) {
            $firstVariant = $product->variants->first();
            $product->sku = $firstVariant?->sku ?? $firstVariant?->barcode ?? '-';
            $product->uom_code = $product->defaultUom?->code ?? '-';
            return $product;
        });

        return $products;
    }

    public function getProduct(Product $product)
    {
        $product->load(['variants.uom:id,code,name']);
        return response()->json($product);
    }

    /**
     * Get UOMs that are convertible from a given base UOM.
     * Returns the base UOM itself plus all UOMs that have conversion rules.
     */
    public function getConvertibleUoms(Request $request)
    {
        $baseUomId = $request->input('base_uom_id');
        $productId = $request->input('product_id');
        $companyId = $request->input('company_id');

        if (!$baseUomId) {
            return response()->json([]);
        }

        // Start with the base UOM itself (always included)
        $convertibleUomIds = [(int) $baseUomId];

        // Get UOMs from global uom_conversions table (from_uom_id -> to_uom_id)
        $globalConversions = \App\Models\UomConversion::where('from_uom_id', $baseUomId)
            ->pluck('to_uom_id')
            ->toArray();
        
        $convertibleUomIds = array_merge($convertibleUomIds, $globalConversions);

        // Also get reverse conversions (to_uom_id -> from_uom_id)
        $reverseConversions = \App\Models\UomConversion::where('to_uom_id', $baseUomId)
            ->pluck('from_uom_id')
            ->toArray();
        
        $convertibleUomIds = array_merge($convertibleUomIds, $reverseConversions);

        // Get UOMs from uom_conversion_rules (product/variant/company specific)
        $rulesQuery = \App\Models\UomConversionRule::where('from_uom_id', $baseUomId)
            ->where(function ($query) use ($productId, $companyId) {
                $query->whereNull('product_id')
                    ->orWhere('product_id', $productId);
            })
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });

        $ruleConversions = $rulesQuery->pluck('to_uom_id')->toArray();
        $convertibleUomIds = array_merge($convertibleUomIds, $ruleConversions);

        // Also get reverse rule conversions
        $reverseRulesQuery = \App\Models\UomConversionRule::where('to_uom_id', $baseUomId)
            ->where(function ($query) use ($productId, $companyId) {
                $query->whereNull('product_id')
                    ->orWhere('product_id', $productId);
            })
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });

        $reverseRuleConversions = $reverseRulesQuery->pluck('from_uom_id')->toArray();
        $convertibleUomIds = array_merge($convertibleUomIds, $reverseRuleConversions);

        // Remove duplicates
        $convertibleUomIds = array_unique($convertibleUomIds);

        // Fetch the UOM records
        $uoms = Uom::whereIn('id', $convertibleUomIds)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'kind']);

        return response()->json($uoms);
    }

    /**
     * Get available purchase plans for a branch that have remaining items to order.
     */
    public function getPurchasePlans(Request $request)
    {
        $branchId = $request->input('branch_id');
        
        if (!$branchId) {
            return response()->json([]);
        }

        $plans = PurchasePlan::query()
            ->where('branch_id', $branchId)
            ->where('status', PurchasePlanStatus::CONFIRMED->value)
            ->with(['lines' => function ($query) {
                $query->whereRaw('planned_qty > ordered_qty')
                    ->with(['product:id,name', 'variant:id,sku,barcode', 'uom:id,code,name']);
            }])
            ->orderBy('plan_date', 'desc')
            ->get()
            ->filter(fn ($plan) => $plan->lines->isNotEmpty())
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'plan_number' => $plan->plan_number,
                    'plan_date' => $plan->plan_date->format('Y-m-d'),
                    'lines' => $plan->lines->map(fn ($line) => [
                        'id' => $line->id,
                        'product_id' => $line->product_id,
                        'product_name' => $line->product?->name,
                        'product_variant_id' => $line->product_variant_id,
                        'variant_sku' => $line->variant?->sku,
                        'uom_id' => $line->uom_id,
                        'uom_code' => $line->uom?->code,
                        'remaining_qty' => max(0, (float) $line->planned_qty - (float) $line->ordered_qty),
                        'description' => $line->description,
                    ])->values()->all(),
                ];
            })
            ->values();

        return response()->json($plans);
    }
}
