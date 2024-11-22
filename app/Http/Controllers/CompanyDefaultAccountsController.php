<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Session;

class CompanyDefaultAccountsController extends Controller
{
    public function edit(Request $request, Company $company)
    {
        $filters = Session::get('companies.index_filters', []);
        
        return Inertia::render('Companies/DefaultAccounts', [
            'company' => $company->load([
                'defaultReceivableAccount',
                'defaultPayableAccount',
                'defaultRevenueAccount',
                'defaultCogsAccount',
                'defaultRetainedEarningsAccount'
            ]),
            'accounts' => Account::where(function($query) use ($company) {
                $query->whereHas('companies', function($q) use ($company) {
                    $q->where('companies.id', $company->id);
                });
            })->get(),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'default_receivable_account_id' => 'nullable|exists:accounts,id',
            'default_payable_account_id' => 'nullable|exists:accounts,id',
            'default_revenue_account_id' => 'nullable|exists:accounts,id',
            'default_cogs_account_id' => 'nullable|exists:accounts,id',
            'default_retained_earnings_account_id' => 'nullable|exists:accounts,id',
        ]);

        $company->update($validated);

        return redirect()->back()->with('success', 'Pengaturan akun default berhasil disimpan.');
    }
}