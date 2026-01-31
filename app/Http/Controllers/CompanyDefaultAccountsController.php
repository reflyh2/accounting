<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

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
                'defaultRetainedEarningsAccount',
                'defaultInterbranchReceivableAccount',
                'defaultInterbranchPayableAccount',
                'defaultIntercompanyReceivableAccount',
                'defaultIntercompanyPayableAccount',
                'defaultShippingChargeAccount',
            ]),
            'accounts' => Account::where(function ($query) use ($company) {
                $query->whereHas('companies', function ($q) use ($company) {
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
            'default_interbranch_receivable_account_id' => 'nullable|exists:accounts,id',
            'default_interbranch_payable_account_id' => 'nullable|exists:accounts,id',
            'default_intercompany_receivable_account_id' => 'nullable|exists:accounts,id',
            'default_intercompany_payable_account_id' => 'nullable|exists:accounts,id',
            'default_shipping_charge_account_id' => 'nullable|exists:accounts,id',
        ]);

        $company->update($validated);

        return redirect()->back()->with('success', 'Pengaturan akun default berhasil disimpan.');
    }
}
