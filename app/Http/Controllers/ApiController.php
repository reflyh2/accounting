<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\Account;
use App\Models\AssetFinancingSchedule;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
}
