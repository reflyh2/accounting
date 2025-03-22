<?php

namespace App\Observers;

use App\Models\Journal;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\AssetRentalPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetRentalPaymentObserver
{
    /**
     * Handle the AssetRentalPayment "created" event.
     */
    public function created(AssetRentalPayment $payment): void
    {
        // Only create journal if payment is paid
        if ($payment->status !== 'paid') {
            return;
        }

        $this->createJournal($payment);
    }

    /**
     * Handle the AssetRentalPayment "updated" event.
     */
    public function updated(AssetRentalPayment $payment): void
    {
        // If payment status changed
        if ($payment->isDirty('status')) {
            // If changed to paid, create journal
            if ($payment->status === 'paid') {
                $this->createJournal($payment);
                return;
            }
            
            // If changed from paid to another status, delete journal
            if ($payment->getOriginal('status') === 'paid' && $payment->journal_id) {
                if ($payment->journal_id) {
                    $journalEntries = $payment->journal->journalEntries;

                    foreach ($journalEntries as $journalEntry) {
                        $journalEntry->delete();
                    }

                    $journalId = $payment->journal_id;

                    $payment->journal_id = null;
                    $payment->saveQuietly();

                    Journal::where('id', $journalId)->delete();
                }
                return;
            }
        }

        // If payment amount changed and status is paid, update journal
        if ($payment->isDirty(['amount']) && $payment->status === 'paid') {
            if ($payment->journal_id) {
                $this->updateJournal($payment);
            } else {
                $this->createJournal($payment);
            }
        }
    }

    /**
     * Handle the AssetRentalPayment "deleted" event.
     */
    public function deleted(AssetRentalPayment $payment): void
    {
        // Delete associated journal if exists
        if ($payment->journal_id) {
            $journalEntries = $payment->journal->journalEntries;

            foreach ($journalEntries as $journalEntry) {
                $journalEntry->delete();
            }

            $journalId = $payment->journal_id;

            $payment->journal_id = null;
            $payment->saveQuietly();

            Journal::where('id', $journalId)->delete();
        }
    }

    /**
     * Create journal for the payment
     */
    private function createJournal(AssetRentalPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            // Create journal
            $journal = Journal::create([
                'date' => $payment->payment_date,
                'description' => "Pembayaran sewa aset: {$payment->asset->name}",
                'journal_type' => 'asset_rental_payment',
                'branch_id' => $payment->asset->branch_id,
                'user_global_id' => Auth::user()->global_id ?? null,
            ]);

            $mainCurrency = Currency::where('is_primary', true)->first();
            $exchangeRate = $mainCurrency->companyRates()
                ->where('company_id', $payment->asset->branch->branchGroup->company_id)
                ->first()->exchange_rate;

            // Determine which account to debit based on rental type
            $debitAccountId = $payment->asset->acquisition_type === 'fixed_rental'
                ? $payment->asset->category->prepaid_rent_account_id
                : $payment->asset->category->rent_expense_account_id;

            // Create journal entries
            // Debit prepaid rent or rent expense account
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $debitAccountId,
                'debit' => $payment->amount,
                'credit' => 0,
                'currency_id' => $mainCurrency->id,
                'exchange_rate' => $exchangeRate,
                'primary_currency_debit' => $payment->amount * $exchangeRate,
                'primary_currency_credit' => 0,
            ]);

            // Credit selected account
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $payment->credited_account_id,
                'debit' => 0,
                'credit' => $payment->amount,
                'currency_id' => $mainCurrency->id,
                'exchange_rate' => $exchangeRate,
                'primary_currency_debit' => 0,
                'primary_currency_credit' => $payment->amount * $exchangeRate,
            ]);

            // Link journal to payment
            $payment->journal_id = $journal->id;
            $payment->saveQuietly();
        });
    }

    /**
     * Update journal entries for the payment
     */
    private function updateJournal(AssetRentalPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $journal = $payment->journal;
            $mainCurrency = Currency::where('is_primary', true)->first();
            $exchangeRate = $mainCurrency->companyRates()
                ->where('company_id', $payment->asset->branch->branchGroup->company_id)
                ->first()->exchange_rate;

            // Determine which account to debit based on rental type
            $debitAccountId = $payment->asset->acquisition_type === 'fixed_rental'
                ? $payment->asset->category->prepaid_rent_account_id
                : $payment->asset->category->rent_expense_account_id;

            // Update debit entry
            $journal->journalEntries()
                ->where('account_id', $debitAccountId)
                ->update([
                    'debit' => $payment->amount,
                    'credit' => 0,
                    'primary_currency_debit' => $payment->amount * $exchangeRate,
                    'primary_currency_credit' => 0,
                ]);

            // Update credit entry
            $journal->journalEntries()
                ->where('account_id', $payment->credited_account_id)
                ->update([
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'primary_currency_debit' => 0,
                    'primary_currency_credit' => $payment->amount * $exchangeRate,
                ]);
        });
    }
} 