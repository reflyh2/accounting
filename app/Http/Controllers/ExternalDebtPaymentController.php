<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExternalDebt;
use App\Models\ExternalDebtPayment;
use App\Models\ExternalDebtPaymentDetail;
use App\Models\PartnerBankAccount;
use App\Events\Debt\ExternalDebtPaymentCreated;
use App\Events\Debt\ExternalDebtPaymentUpdated;
use App\Events\Debt\ExternalDebtPaymentDeleted;
use Illuminate\Support\Facades\DB;

class ExternalDebtPaymentController extends Controller
{
    protected string $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    protected function getUnpaidDebts($companyId = null, $branchId = null, $partnerId = null, $currencyId = null, $includePaymentId = null)
    {
        $debts = ExternalDebt::with(['partner', 'currency'])
            ->where('type', $this->type)
            ->whereHas('branch.branchGroup', fn($bq) => $bq->where('company_id', $companyId))
            ->where('branch_id', $branchId)
            ->where('partner_id', $partnerId)
            ->where('currency_id', $currencyId)
            ->orderBy('due_date', 'asc')
            ->orderBy('issue_date', 'asc')
            ->get();

        $paidSumsQuery = DB::table('external_debt_payment_details')
            ->join('external_debt_payments', 'external_debt_payments.id', '=', 'external_debt_payment_details.external_debt_payment_id')
            ->whereNull('external_debt_payment_details.deleted_at')
            ->whereNull('external_debt_payments.deleted_at')
            ->where('external_debt_payments.type', $this->type);
        if ($includePaymentId) {
            // exclude current payment from sum to allow editing amounts
            $paidSumsQuery->where('external_debt_payment_details.external_debt_payment_id', '!=', $includePaymentId);
        }
        $paidSums = $paidSumsQuery
            ->groupBy('external_debt_id')
            ->pluck(DB::raw('SUM(external_debt_payment_details.amount) as total'), 'external_debt_id');

        return $debts->map(function ($d) use ($paidSums) {
            $paid = (float)($paidSums[$d->id] ?? 0);
            $remaining = (float)$d->amount - $paid;
            $d->remaining_amount = max($remaining, 0);
            return $d;
        })->filter(fn($d) => $d->remaining_amount > 0)->values();
    }

    protected function detectOverpay(array $details, $excludePaymentId = null): bool
    {
        $grouped = collect($details)->groupBy('external_debt_id')->map->sum('amount');
        if ($grouped->isEmpty()) return false;
        $paidSumsQuery = DB::table('external_debt_payment_details')
            ->join('external_debt_payments', 'external_debt_payments.id', '=', 'external_debt_payment_details.external_debt_payment_id')
            ->whereNull('external_debt_payment_details.deleted_at')
            ->whereNull('external_debt_payments.deleted_at')
            ->where('external_debt_payments.type', $this->type);
        if ($excludePaymentId) {
            $paidSumsQuery->where('external_debt_payment_details.external_debt_payment_id', '!=', $excludePaymentId);
        }
        $paidSums = $paidSumsQuery
            ->whereIn('external_debt_payment_details.external_debt_id', $grouped->keys())
            ->groupBy('external_debt_payment_details.external_debt_id')
            ->pluck(DB::raw('SUM(external_debt_payment_details.amount) as total'), 'external_debt_payment_details.external_debt_id');

        $debts = ExternalDebt::whereIn('id', $grouped->keys())->pluck('amount', 'id');
        foreach ($grouped as $debtId => $newAmount) {
            $alreadyPaid = (float)($paidSums[$debtId] ?? 0);
            $totalDebt = (float)($debts[$debtId] ?? 0);
            if ($newAmount + $alreadyPaid - 0.00001 > $totalDebt) {
                return true;
            }
        }
        return false;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'payment_date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'payment_method' => 'required|in:tunai,transfer,cek,giro',
            'partner_bank_account_id' => 'nullable|required_if:payment_method,transfer|exists:partner_bank_accounts,id',
            'instrument_date' => 'nullable|required_if:payment_method,cek,\\,giro|date',
            'withdrawal_date' => 'nullable|required_if:payment_method,cek,\\,giro|date|after_or_equal:instrument_date',
            'details' => 'required|array|min:1',
            'details.*.external_debt_id' => 'required|exists:external_debts,id',
            'details.*.amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validated['payment_method'] === 'transfer' && !empty($validated['partner_bank_account_id'])) {
            $pba = PartnerBankAccount::findOrFail($validated['partner_bank_account_id']);
            if ($pba->partner_id != $validated['partner_id']) {
                return Redirect::back()->with('error', 'Rekening bank tidak sesuai dengan partner yang dipilih.');
            }
        }

        // Validate debts belong to same partner and type, and not overpay
        $debtIds = collect($validated['details'])->pluck('external_debt_id')->unique()->values();
        $debts = ExternalDebt::whereIn('id', $debtIds)->get();
        if ($debts->isEmpty() || $debts->contains(fn($d) => $d->type !== $this->type)) {
            return Redirect::back()->with('error', 'Jenis hutang/piutang tidak sesuai.');
        }
        if ($debts->contains(fn($d) => $d->partner_id != $validated['partner_id'])) {
            return Redirect::back()->with('error', 'Semua dokumen harus untuk partner yang sama.');
        }
        $overpaid = $this->detectOverpay($validated['details']);
        if ($overpaid) {
            return Redirect::back()->with('error', 'Jumlah pembayaran melebihi sisa hutang untuk salah satu dokumen.');
        }

        $sumAmount = collect($validated['details'])->sum('amount');

        $payment = DB::transaction(function () use ($validated, $sumAmount) {
            $primaryCurrencyAmount = $sumAmount * $validated['exchange_rate'];
            $payment = ExternalDebtPayment::create([
                'type' => $this->type,
                'partner_id' => $validated['partner_id'],
                'branch_id' => $validated['branch_id'],
                'account_id' => $validated['account_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'payment_date' => $validated['payment_date'],
                'amount' => $sumAmount,
                'primary_currency_amount' => $primaryCurrencyAmount,
                'payment_method' => $validated['payment_method'] ?? null,
                'partner_bank_account_id' => $validated['partner_bank_account_id'] ?? null,
                'instrument_date' => $validated['instrument_date'] ?? null,
                'withdrawal_date' => $validated['withdrawal_date'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['details'] as $detail) {
                ExternalDebtPaymentDetail::create([
                    'external_debt_payment_id' => $payment->id,
                    'external_debt_id' => $detail['external_debt_id'],
                    'amount' => $detail['amount'],
                    'primary_currency_amount' => $detail['amount'] * $validated['exchange_rate'],
                    'notes' => null,
                ]);
            }
            return $payment;
        });

        ExternalDebtPaymentCreated::dispatch($payment);
        return redirect()->route($this->type == 'payable' ? 'external-payable-payments.show' : 'external-receivable-payments.show', $payment->id)
            ->with('success', 'Pembayaran hutang berhasil dibuat.');
    }
}
