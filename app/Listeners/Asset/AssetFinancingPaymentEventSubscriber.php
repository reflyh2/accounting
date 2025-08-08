<?php

namespace App\Listeners\Asset;

use App\Events\Asset\AssetFinancingPaymentCreated;
use App\Events\Asset\AssetFinancingPaymentDeleted;
use App\Events\Asset\AssetFinancingPaymentUpdated;
use App\Models\Journal;
use App\Models\AssetCategory;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class AssetFinancingPaymentEventSubscriber
{
    public function handleAssetFinancingPaymentCreated(AssetFinancingPaymentCreated $event): void
    {
        $this->createJournalForAssetFinancingPayment($event->assetFinancingPayment);
    }

    public function handleAssetFinancingPaymentUpdated(AssetFinancingPaymentUpdated $event): void
    {
        $this->deleteJournal($event->assetFinancingPayment);
        $this->createJournalForAssetFinancingPayment($event->assetFinancingPayment);
    }

    public function handleAssetFinancingPaymentDeleted(AssetFinancingPaymentDeleted $event): void
    {
        $this->deleteJournal($event->assetFinancingPayment);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AssetFinancingPaymentCreated::class => 'handleAssetFinancingPaymentCreated',
            AssetFinancingPaymentUpdated::class => 'handleAssetFinancingPaymentUpdated',
            AssetFinancingPaymentDeleted::class => 'handleAssetFinancingPaymentDeleted',
        ];
    }

    private function createJournalForAssetFinancingPayment($assetFinancingPayment)
    {
        DB::transaction(function () use ($assetFinancingPayment) {
            $company = $assetFinancingPayment->branch->branchGroup->company;
            $entries = [];

            foreach ($assetFinancingPayment->allocations as $allocation) {
                $agreement = $allocation->assetFinancingAgreement;
                $assetInvoiceDetailsByCategory = $agreement->assetInvoice->assetInvoiceDetails->groupBy('asset.asset_category_id');

                $categoryTotal = [];
                $totalAmount = 0;

                foreach ($assetInvoiceDetailsByCategory as $assetCategoryId => $assetInvoiceDetails) {
                    $categoryTotal[$assetCategoryId] = $assetInvoiceDetails->sum('line_amount');
                    $totalAmount += $categoryTotal[$assetCategoryId];
                }

                foreach ($categoryTotal as $assetCategoryId => $total) {
                    $assetCategory = AssetCategory::find($assetCategoryId);
                    $principalAmount = $allocation->principal_amount * ($total / $totalAmount);
                    $interestAmount = $allocation->interest_amount * ($total / $totalAmount);
                    
                    $financingPayableAccount = $assetCategory->assetFinancingPayableAccount($company);
                    $interestCostAccount = $assetCategory->leasingInterestCostAccount($company);

                    if (!$financingPayableAccount) {
                        throw new \Exception("Akun Hutang Pembiayaan Aset tidak diatur untuk kategori: {$assetCategory->name}");
                    }

                    if (!$interestCostAccount) {
                        throw new \Exception("Akun Biaya Leasing tidak diatur untuk kategori: {$assetCategory->name}");
                    }

                    $entries[] = [
                        'account_id' => $financingPayableAccount->id,
                        'debit' => $principalAmount,
                        'credit' => 0,
                    ];
                    $entries[] = [
                        'account_id' => $interestCostAccount->id,
                        'debit' => $interestAmount,
                        'credit' => 0,
                    ];
                }
            }
            
            $entries[] = [
                'account_id' => $assetFinancingPayment->source_account_id,
                'debit' => 0,
                'credit' => $assetFinancingPayment->total_paid_amount,
            ];
            
            $combinedEntries = [];
            foreach ($entries as $entry) {
                $accountId = $entry['account_id'];
                if (!isset($combinedEntries[$accountId])) {
                    $combinedEntries[$accountId] = ['account_id' => $accountId, 'debit' => 0, 'credit' => 0];
                }
                $combinedEntries[$accountId]['debit'] += $entry['debit'];
                $combinedEntries[$accountId]['credit'] += $entry['credit'];
            }
            $entries = array_values($combinedEntries);

            $journal = Journal::create([
                'branch_id' => $assetFinancingPayment->branch_id,
                'user_global_id' => $assetFinancingPayment->created_by,
                'journal_type' => 'asset_financing_payment',
                'date' => $assetFinancingPayment->payment_date,
                'description' => "Pembayaran Pembiayaan Aset #{$assetFinancingPayment->number}",
                'reference_number' => $assetFinancingPayment->number,
            ]);

            foreach ($entries as $entry) {
                $journal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'currency_id' => $assetFinancingPayment->currency_id,
                    'exchange_rate' => $assetFinancingPayment->exchange_rate,
                    'primary_currency_debit' => $entry['debit'] * $assetFinancingPayment->exchange_rate,
                    'primary_currency_credit' => $entry['credit'] * $assetFinancingPayment->exchange_rate,
                ]);
            }

            $assetFinancingPayment->journal_id = $journal->id;
            $assetFinancingPayment->saveQuietly();
        });
    }

    private function deleteJournal($assetFinancingPayment)
    {
        if (!$assetFinancingPayment->journal_id) {
            return;
        }

        DB::transaction(function () use ($assetFinancingPayment) {
            $originalJournal = Journal::find($assetFinancingPayment->journal_id);

            if (!$originalJournal) {
                return;
            }

            foreach ($originalJournal->journalEntries as $entry) {
                $entry->delete();
            }

            $assetFinancingPayment->journal_id = null;
            $assetFinancingPayment->saveQuietly();

            $originalJournal->delete();
        });
    }
} 