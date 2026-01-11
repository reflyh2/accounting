<?php

namespace App\Http\Controllers;

use App\Models\AssetDepreciationSchedule;
use App\Models\Asset;
use App\Models\CostPool;
use App\Models\Currency;
use App\Enums\CostEntrySource;
use App\Services\Costing\CostingService;
use App\Services\Costing\DTO\CostEntryDTO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AssetDepreciationController extends Controller
{
    public function __construct(
        private readonly CostingService $costingService
    ) {
    }

    public function index(Request $request)
    {
        $schedules = AssetDepreciationSchedule::with(['asset.branch.branchGroup.company'])
            ->where('is_processed', false)
            ->whereDate('schedule_date', '<=', now()->toDateString())
            ->orderBy('schedule_date')
            ->paginate($request->input('per_page', 25))
            ->onEachSide(0)
            ->withQueryString();

        return Inertia::render('AssetDepreciations/Index', [
            'schedules' => $schedules,
            'depreciationMethods' => Asset::depreciationMethods(),
        ]);
    }

    public function processAll(Request $request)
    {
        $ids = AssetDepreciationSchedule::where('is_processed', false)
            ->whereDate('schedule_date', '<=', now()->toDateString())
            ->pluck('id');

        $this->processMany($ids->all(), $request->user());

        return redirect()->back()->with('success', 'Penyusutan/Amortisasi berhasil diproses.');
    }

    public function processSelected(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:asset_depreciation_schedules,id']);
        $this->processMany($validated['ids'], $request->user());
        return redirect()->back()->with('success', 'Penyusutan/Amortisasi terpilih berhasil diproses.');
    }

    private function processMany(array $ids, $actor): void
    {
        $schedules = AssetDepreciationSchedule::with(['asset.category', 'asset.branch.branchGroup.company'])->whereIn('id', $ids)->get();
        $costEntryData = [];

        DB::transaction(function () use ($schedules, &$costEntryData) {
            foreach ($schedules as $schedule) {
                if ($schedule->is_processed) {
                    continue;
                }

                $asset = $schedule->asset;
                $company = $asset->branch->branchGroup->company;
                $category = $asset->category;

                if ($asset->is_amortizable) {
                    $drAccount = $category->assetAmortizationAccount($company);
                    $crAccount = $category->assetPrepaidAmortizationAccount($company);

                    if (!$drAccount || !$crAccount) {
                        throw new \RuntimeException('Akun Amortisasi tidak diatur untuk kategori: ' . $category->name);
                    }
                } else if ($asset->is_depreciable) {
                    $drAccount = $category->assetDepreciationAccount($company);
                    $crAccount = $category->assetAccumulatedDepreciationAccount($company);

                    if (!$drAccount || !$crAccount) {
                        throw new \RuntimeException('Akun Penyusutan tidak diatur untuk kategori: ' . $category->name);
                    }
                }

                $primaryCurrency = Currency::where('is_primary', true)->first();
                $exchangeRate = $primaryCurrency->companyRates()->where('company_id', $company->id)->first()?->exchange_rate ?? 1;
                $type = $asset->is_amortizable ? 'asset_amortization' : 'asset_depreciation';

                // Create journal
                $journal = \App\Models\Journal::create([
                    'branch_id' => $asset->branch_id,
                    'user_global_id' => auth()->user?->global_id,
                    'journal_type' => $type,
                    'date' => $schedule->schedule_date,
                    'description' => ($type === 'asset_amortization' ? 'Amortisasi' : 'Penyusutan') . ' aset ' . $asset->code . ' - ' . $asset->name . ' periode ' . $schedule->schedule_date,
                ]);

                $journal->journalEntries()->create([
                    'account_id' => $drAccount->id,
                    'debit' => $schedule->amount,
                    'credit' => 0,
                    'currency_id' => $primaryCurrency->id,
                    'exchange_rate' => $exchangeRate,
                    'primary_currency_debit' => $schedule->amount,
                    'primary_currency_credit' => 0,
                ]);

                $journal->journalEntries()->create([
                    'account_id' => $crAccount->id,
                    'debit' => 0,
                    'credit' => $schedule->amount,
                    'currency_id' => $primaryCurrency->id,
                    'exchange_rate' => $exchangeRate,
                    'primary_currency_debit' => 0,
                    'primary_currency_credit' => $schedule->amount,
                ]);

                $schedule->update([
                    'is_processed' => true,
                    'processed_at' => now(),
                    'journal_id' => $journal->id,
                ]);

                // Update asset summary fields
                $asset->accumulated_depreciation = ($asset->accumulated_depreciation ?? 0) + $schedule->amount;
                $asset->net_book_value = max(0, ($asset->cost_basis ?? 0) - $asset->accumulated_depreciation);
                $asset->saveQuietly();

                // Collect cost entry data - look up cost pool by asset_id
                $costPool = CostPool::where('asset_id', $asset->id)->where('is_active', true)->first();
                if ($costPool) {
                    $costEntryData[] = [
                        'company_id' => $company->id,
                        'schedule_id' => $schedule->id,
                        'cost_pool_id' => $costPool->id,
                        'amount' => $schedule->amount,
                        'currency_id' => $primaryCurrency->id,
                        'exchange_rate' => $exchangeRate,
                        'cost_date' => $schedule->schedule_date,
                        'asset_code' => $asset->code,
                        'asset_name' => $asset->name,
                    ];
                }
            }
        });

        // Create cost entries outside transaction
        foreach ($costEntryData as $data) {
            $dto = new CostEntryDTO(
                companyId: $data['company_id'],
                sourceType: CostEntrySource::ASSET_DEPRECIATION,
                sourceId: $data['schedule_id'],
                productId: null,
                productVariantId: null,
                costPoolId: $data['cost_pool_id'],
                costObjectType: null,
                costObjectId: null,
                description: "Depreciation: {$data['asset_code']} - {$data['asset_name']}",
                amount: $data['amount'],
                currencyId: $data['currency_id'],
                exchangeRate: $data['exchange_rate'],
                costDate: $data['cost_date'],
                notes: "Asset depreciation for {$data['asset_code']}",
            );

            $this->costingService->recordCostEntry($dto, $actor);
        }
    }
}
