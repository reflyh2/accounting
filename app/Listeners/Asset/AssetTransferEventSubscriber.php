<?php

namespace App\Listeners\Asset;

use App\Events\Asset\AssetTransferCreated;
use App\Events\Asset\AssetTransferUpdated;
use App\Events\Asset\AssetTransferDeleted;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class AssetTransferEventSubscriber
{
    public function handleAssetTransferCreated(AssetTransferCreated $event): void
    {
        $this->createJournalsForAssetTransfer($event->assetTransfer);
    }

    public function handleAssetTransferUpdated(AssetTransferUpdated $event): void
    {
        $this->deleteJournals($event->assetTransfer);
        $this->createJournalsForAssetTransfer($event->assetTransfer);
    }

    public function handleAssetTransferDeleted(AssetTransferDeleted $event): void
    {
        $this->deleteJournals($event->assetTransfer);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AssetTransferCreated::class => 'handleAssetTransferCreated',
            AssetTransferUpdated::class => 'handleAssetTransferUpdated',
            AssetTransferDeleted::class => 'handleAssetTransferDeleted',
        ];
    }

    private function createJournalsForAssetTransfer($assetTransfer): void
    {
        // Load necessary relationships
        $assetTransfer->loadMissing([
            'assetTransferDetails.asset.category',
            'fromBranch.branchGroup.company',
            'toBranch.branchGroup.company'
        ]);

        DB::transaction(function () use ($assetTransfer) {
            // Group details by asset category for consolidated journal entries
            $detailsByCategory = $assetTransfer->assetTransferDetails->groupBy('asset.category.id');

            // Create "sale" journal for source branch
            $fromJournal = Journal::create([
                'company_id' => $assetTransfer->from_company_id,
                'branch_id' => $assetTransfer->from_branch_id,
                'date' => $assetTransfer->transfer_date,
                'description' => "Asset transfer (out) - {$assetTransfer->number}",
                'status' => 'posted',
            ]);

            // Create "purchase" journal for destination branch
            $toJournal = Journal::create([
                'company_id' => $assetTransfer->to_company_id,
                'branch_id' => $assetTransfer->to_branch_id,
                'date' => $assetTransfer->transfer_date,
                'description' => "Asset transfer (in) - {$assetTransfer->number}",
                'status' => 'posted',
            ]);

            foreach ($detailsByCategory as $categoryId => $details) {
                $category = $details->first()->asset->category;
                $totalAmount = $details->sum(function ($detail) {
                    return $detail->asset->cost_basis;
                });
                $totalAccumulatedDepreciation = $details->sum(function ($detail) {
                    return $detail->asset->accumulated_depreciation ?? 0;
                });
                $bookValue = $totalAmount - $totalAccumulatedDepreciation;

                // Source branch journal entries (selling side)
                // Debit: Accumulated Depreciation
                if ($totalAccumulatedDepreciation > 0) {
                    JournalEntry::create([
                        'journal_id' => $fromJournal->id,
                        'account_id' => $category->assetAccumulatedDepreciationAccount->id,
                        'debit' => $totalAccumulatedDepreciation,
                        'credit' => 0,
                    ]);
                }

                // Credit: Asset Account
                JournalEntry::create([
                    'journal_id' => $fromJournal->id,
                    'account_id' => $category->assetAccount->id,
                    'debit' => 0,
                    'credit' => $totalAmount,
                ]);

                // Debit: Due from Other Branch/Company
                JournalEntry::create([
                    'journal_id' => $fromJournal->id,
                    'account_id' => $assetTransfer->from_company_id === $assetTransfer->to_company_id
                        ? $assetTransfer->fromCompany->defaultInterbranchReceivableAccount->id
                        : $assetTransfer->fromCompany->defaultIntercompanyReceivableAccount->id,
                    'debit' => $bookValue,
                    'credit' => 0,
                ]);

                // Destination branch journal entries (purchasing side)
                // Debit: Asset Account
                JournalEntry::create([
                    'journal_id' => $toJournal->id,
                    'account_id' => $category->assetAccount->id,
                    'debit' => $totalAmount,
                    'credit' => 0,
                ]);

                // Credit: Due to Other Branch/Company
                JournalEntry::create([
                    'journal_id' => $toJournal->id,
                    'account_id' => $assetTransfer->from_company_id === $assetTransfer->to_company_id
                        ? $assetTransfer->toCompany->defaultInterbranchPayableAccount->id
                        : $assetTransfer->toCompany->defaultIntercompanyPayableAccount->id,
                    'debit' => 0,
                    'credit' => $bookValue,
                ]);

                // If there's accumulated depreciation, create it in the new branch
                if ($totalAccumulatedDepreciation > 0) {
                    JournalEntry::create([
                        'journal_id' => $toJournal->id,
                        'account_id' => $category->assetAccumulatedDepreciationAccount->id,
                        'debit' => 0,
                        'credit' => $totalAccumulatedDepreciation,
                    ]);
                }
            }

            // Update the transfer record with journal IDs
            $assetTransfer->update([
                'from_journal_id' => $fromJournal->id,
                'to_journal_id' => $toJournal->id,
            ]);
        });
    }

    private function deleteJournals($assetTransfer): void
    {
        DB::transaction(function () use ($assetTransfer) {
            // Delete both journals if they exist
            if ($assetTransfer->from_journal_id) {
                Journal::where('id', $assetTransfer->from_journal_id)->delete();
                $assetTransfer->from_journal_id = null;
            }
            if ($assetTransfer->to_journal_id) {
                Journal::where('id', $assetTransfer->to_journal_id)->delete();
                $assetTransfer->to_journal_id = null;
            }
            $assetTransfer->save();
        });
    }
}
