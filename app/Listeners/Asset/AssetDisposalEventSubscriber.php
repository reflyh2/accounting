<?php

namespace App\Listeners\Asset;

use App\Events\Asset\AssetDisposalCreated;
use App\Events\Asset\AssetDisposalDeleted;
use App\Events\Asset\AssetDisposalUpdated;
use App\Models\Journal;
use App\Models\Currency;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class AssetDisposalEventSubscriber
{
    public function handleAssetDisposalCreated(AssetDisposalCreated $event): void
    {
        $this->createJournalForAssetDisposal($event->assetDisposal);
    }

    public function handleAssetDisposalUpdated(AssetDisposalUpdated $event): void
    {
        $this->deleteJournal($event->assetDisposal);
        $this->createJournalForAssetDisposal($event->assetDisposal);
    }

    public function handleAssetDisposalDeleted(AssetDisposalDeleted $event): void
    {
        $this->deleteJournal($event->assetDisposal);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AssetDisposalCreated::class => 'handleAssetDisposalCreated',
            AssetDisposalUpdated::class => 'handleAssetDisposalUpdated',
            AssetDisposalDeleted::class => 'handleAssetDisposalDeleted',
        ];
    }

    private function createJournalForAssetDisposal($assetDisposal)
    {
        DB::transaction(function () use ($assetDisposal) {
            $company = $assetDisposal->branch->branchGroup->company;
            $entries = [];

            foreach ($assetDisposal->assetDisposalDetails as $detail) {
                $asset = $detail->asset;
                $assetCategory = $asset->category;

                $assetAccount = $assetCategory->assetAccount($company);
                $accumulatedDepreciationAccount = $assetCategory->assetAccumulatedDepreciationAccount($company);

                if (!$assetAccount) {
                    throw new \Exception("Akun Aset tidak diatur untuk kategori: {$assetCategory->name}");
                }

                if (!$accumulatedDepreciationAccount) {
                    throw new \Exception("Akun Akumulasi Penyusutan Aset tidak diatur untuk kategori: {$assetCategory->name}");
                }

                $accumulatedDepreciation = $asset->accumulated_depreciation;
                $gainOrLoss = $detail->proceeds_amount - $detail->carrying_amount;

                // 1. Debit Accumulated Depreciation
                if ($accumulatedDepreciation > 0) {
                    $entries[] = [
                        'account_id' => $accumulatedDepreciationAccount->id, 
                        'debit' => $accumulatedDepreciation, 
                        'credit' => 0,
                    ];
                }
                // 2. Credit Asset Account
                if ($asset->cost_basis > 0) {
                    $entries[] = [
                        'account_id' => $assetAccount->id, 
                        'debit' => 0, 
                        'credit' => $asset->cost_basis,
                    ];
                }

                // 3. Debit Gain or Loss on Sale Account
                if ($gainOrLoss > 0) {
                    $gainOnSaleAccount = $assetCategory->assetSaleProfitAccount($company);

                    if (!$gainOnSaleAccount) {
                        throw new \Exception("Akun Laba Penjualan Aset tidak diatur untuk kategori: {$assetCategory->name}");
                    }

                    $entries[] = [
                        'account_id' => $gainOnSaleAccount->id,
                        'debit' => 0,
                        'credit' => $gainOrLoss,
                    ];
                } else {
                    $lossOnSaleAccount = $assetCategory->assetSaleLossAccount($company);

                    if (!$lossOnSaleAccount) {
                        throw new \Exception("Akun Rugi Penjualan Aset tidak diatur untuk kategori: {$assetCategory->name}");
                    }

                    $entries[] = [
                        'account_id' => $lossOnSaleAccount->id,
                        'debit' => abs($gainOrLoss),
                        'credit' => 0,
                    ];
                }
            }

            if (!$assetDisposal->proceed_account_id) {
                throw new \Exception("Akun Penerimaan Hasil Pelepasan Aset tidak diatur untuk kategori: {$assetCategory->name}");
            }

            // 4. Credit Proceed Account
            if ($assetDisposal->proceeds_amount > 0) {
                $entries[] = [
                    'account_id' => $assetDisposal->proceed_account_id,
                    'debit' => $assetDisposal->proceeds_amount,
                    'credit' => 0,
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

            $primaryCurrency = Currency::where('is_primary', true)->first();
            $companyCurrencyRate = $primaryCurrency->companyRates()->where('company_id', $assetDisposal->branch->branchGroup->company_id)->first();
            $exchangeRate = $companyCurrencyRate->exchange_rate;

            $journal = Journal::create([
                'branch_id' => $assetDisposal->branch_id,
                'user_global_id' => $assetDisposal->created_by,
                'journal_type' => 'asset_disposal',
                'date' => $assetDisposal->disposal_date,
                'description' => "Pelepasan Aset #{$assetDisposal->number}",
                'reference_number' => $assetDisposal->number,
            ]);

            foreach ($entries as $entry) {
                $journal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'currency_id' => $primaryCurrency->id,
                    'exchange_rate' => $exchangeRate,
                    'primary_currency_debit' => $entry['debit'] * $exchangeRate,
                    'primary_currency_credit' => $entry['credit'] * $exchangeRate,
                ]);
            }

            $assetDisposal->journal_id = $journal->id;
            $assetDisposal->saveQuietly();
        });
    }

    private function deleteJournal($assetDisposal)
    {
        if (!$assetDisposal->journal_id) {
            return;
        }

        DB::transaction(function () use ($assetDisposal) {
            $originalJournal = Journal::find($assetDisposal->journal_id);

            if (!$originalJournal) {
                return;
            }

            foreach ($originalJournal->journalEntries as $entry) {
                $entry->delete();
            }

            $assetDisposal->journal_id = null;
            $assetDisposal->saveQuietly();

            $originalJournal->delete();
        });
    }
} 