<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\Account;

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
        $branch = Branch::findOrFail($branchId);
        $accounts = Account::whereHas('companies', function ($query) use ($branch) {
            $query->where('company_id', $branch->company_id);
        })->get();
        return response()->json(['availableAccounts' => $accounts]);
    }
}
