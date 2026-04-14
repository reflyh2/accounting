<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class CompanyAccountAssignmentController extends Controller
{
    public function edit(Request $request, Company $company): \Inertia\Response
    {
        $filters = Session::get('companies.index_filters', []);

        $assignedAccountIds = $company->accounts()->pluck('accounts.id')->toArray();

        $accounts = Account::query()
            ->with('parent')
            ->orderBy('code', 'asc')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'is_parent' => $account->is_parent,
                'parent_name' => $account->parent?->name,
            ]);

        return Inertia::render('Companies/AccountAssignment', [
            'company' => $company,
            'accounts' => $accounts,
            'assignedAccountIds' => $assignedAccountIds,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Company $company): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'account_ids' => 'present|array',
            'account_ids.*' => 'integer|exists:accounts,id',
        ]);

        $company->accounts()->sync($validated['account_ids']);

        return redirect()->back()->with('success', 'Akun perusahaan berhasil diperbarui.');
    }
}
