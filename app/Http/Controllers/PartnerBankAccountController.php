<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\PartnerBankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerBankAccountController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:3',
            'is_primary' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $bankAccount = PartnerBankAccount::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rekening bank berhasil ditambahkan.',
            'data' => $bankAccount->load('partner')
        ]);
    }

    public function update(Request $request, PartnerBankAccount $partnerBankAccount)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:3',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $partnerBankAccount->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rekening bank berhasil diubah.',
            'data' => $partnerBankAccount->load('partner')
        ]);
    }

    public function destroy(PartnerBankAccount $partnerBankAccount)
    {
        $partnerBankAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rekening bank berhasil dihapus.'
        ]);
    }

    public function getByPartner(Partner $partner)
    {
        $bankAccounts = $partner->activeBankAccounts()->get();

        return response()->json([
            'success' => true,
            'data' => $bankAccounts
        ]);
    }
} 