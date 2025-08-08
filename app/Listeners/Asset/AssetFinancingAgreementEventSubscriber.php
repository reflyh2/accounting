<?php

namespace App\Listeners\Asset;

use App\Events\Asset\AssetFinancingAgreementCreated;
use App\Events\Asset\AssetFinancingAgreementDeleted;
use App\Events\Asset\AssetFinancingAgreementUpdated;
use App\Models\Journal;
use App\Models\AssetCategory;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class AssetFinancingAgreementEventSubscriber
{
    public function handleAssetFinancingAgreementCreated(AssetFinancingAgreementCreated $event): void
    {
        $this->createJournalForAssetFinancingAgreement($event->assetFinancingAgreement);
    }

    public function handleAssetFinancingAgreementUpdated(AssetFinancingAgreementUpdated $event): void
    {
        $this->deleteJournal($event->assetFinancingAgreement);
        $this->createJournalForAssetFinancingAgreement($event->assetFinancingAgreement);
    }

    public function handleAssetFinancingAgreementDeleted(AssetFinancingAgreementDeleted $event): void
    {
        $this->deleteJournal($event->assetFinancingAgreement);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AssetFinancingAgreementCreated::class => 'handleAssetFinancingAgreementCreated',
            AssetFinancingAgreementUpdated::class => 'handleAssetFinancingAgreementUpdated',
            AssetFinancingAgreementDeleted::class => 'handleAssetFinancingAgreementDeleted',
        ];
    }

    private function createJournalForAssetFinancingAgreement($assetFinancingAgreement)
    {
        DB::transaction(function () use ($assetFinancingAgreement) {
            $company = $assetFinancingAgreement->branch->branchGroup->company;
            $entries = [];

            $assetInvoice = $assetFinancingAgreement->assetInvoice;
            $assetInvoiceDetailsByCategory = $assetInvoice->assetInvoiceDetails->groupBy('asset.asset_category_id');
            $categoryTotal = [];
            $totalAmount = 0;

            foreach ($assetInvoiceDetailsByCategory as $assetCategoryId => $assetInvoiceDetails) {
                $categoryTotal[$assetCategoryId] = $assetInvoiceDetails->sum('line_amount');
                $totalAmount += $categoryTotal[$assetCategoryId];
            }

            foreach ($categoryTotal as $assetCategoryId => $total) {
                $assetCategory = AssetCategory::find($assetCategoryId);
                $categoryAllocation = $assetFinancingAgreement->total_amount * ($total / $totalAmount);

                $assetPayableAccount = $assetCategory->assetAcquisitionPayableAccount($company);
                $assetFinancingPayableAccount = $assetCategory->assetFinancingPayableAccount($company);

                if (!$assetPayableAccount) {
                    throw new \Exception("Akun Hutang Pembelian Aset tidak diatur untuk kategori: {$assetCategory->name}");
                }

                if (!$assetFinancingPayableAccount) {
                    throw new \Exception("Akun Hutang Pembiayaan Aset tidak diatur untuk kategori: {$assetCategory->name}");
                }

                $entries[] = [
                    'account_id' => $assetPayableAccount->id, 
                    'debit' => $categoryAllocation, 
                    'credit' => 0,
                ];

                $entries[] = [
                    'account_id' => $assetFinancingPayableAccount->id, 
                    'debit' => 0, 
                    'credit' => $categoryAllocation,
                ];
            }
            
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
                'branch_id' => $assetFinancingAgreement->branch_id,
                'user_global_id' => $assetFinancingAgreement->created_by,
                'journal_type' => 'asset_financing_agreement',
                'date' => $assetFinancingAgreement->agreement_date,
                'description' => "Pembiayaan Aset untuk Invoice #{$assetInvoice->number}",
                'reference_number' => $assetFinancingAgreement->number,
            ]);

            foreach ($entries as $entry) {
                $journal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'currency_id' => $assetFinancingAgreement->currency_id,
                    'exchange_rate' => $assetFinancingAgreement->exchange_rate,
                    'primary_currency_debit' => $entry['debit'] * $assetFinancingAgreement->exchange_rate,
                    'primary_currency_credit' => $entry['credit'] * $assetFinancingAgreement->exchange_rate,
                ]);
            }

            $assetFinancingAgreement->journal_id = $journal->id;
            $assetFinancingAgreement->saveQuietly();
        });
    }

    private function deleteJournal($assetFinancingAgreement)
    {
        if (!$assetFinancingAgreement->journal_id) {
            return;
        }

        DB::transaction(function () use ($assetFinancingAgreement) {
            $originalJournal = Journal::find($assetFinancingAgreement->journal_id);

            if (!$originalJournal) {
                return;
            }

            foreach ($originalJournal->journalEntries as $entry) {
                $entry->delete();
            }

            $assetFinancingAgreement->journal_id = null;
            $assetFinancingAgreement->saveQuietly();

            $originalJournal->delete();
        });
    }
} 