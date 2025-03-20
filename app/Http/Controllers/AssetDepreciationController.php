<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Account;
use App\Models\AssetDepreciationEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class AssetDepreciationController extends Controller
{
    public function index(Request $request, Asset $asset)
    {
        $filters = $request->all() ?: Session::get('asset_depreciation.index_filters', []);
        Session::put('asset_depreciation.index_filters', $filters);

        $query = $asset->depreciationEntries();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('notes', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('entry_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('entry_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->whereIn('type', $filters['type']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'entry_date';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $entries = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('AssetDepreciation/Index', [
            'asset' => $asset->load(['branch.branchGroup.company', 'category']),
            'accounts' => Account::whereIn('type', ['akumulasi_penyusutan', 'beban_penyusutan'])
                ->where('is_parent', false)
                ->orderBy('code')
                ->get(),
            'entries' => $entries,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Asset $asset)
    {
        return Inertia::render('AssetDepreciation/Create', [
            'asset' => $asset->load(['branch.branchGroup.company', 'category']),
            'accounts' => Account::where('type', 'akumulasi_penyusutan')
                ->where('is_parent', false)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'entry_date' => 'required|date',
            'type' => 'required|in:depreciation,amortization',
            'amount' => 'required|numeric|min:0',
            'cumulative_amount' => 'required|numeric|min:0',
            'remaining_value' => 'required|numeric|min:0',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'notes' => 'nullable|string|max:1000',
        ]);

        $entry = $asset->depreciationEntries()->create([
            'entry_date' => $validated['entry_date'],
            'type' => $validated['type'],
            'status' => 'scheduled',
            'amount' => $validated['amount'],
            'cumulative_amount' => $validated['cumulative_amount'],
            'remaining_value' => $validated['remaining_value'],
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'notes' => $validated['notes'],
        ]);

        if ($request->get('create_another')) {
            return redirect()
                ->route('asset-depreciation.create', $asset->id)
                ->with('success', 'Entri penyusutan berhasil ditambahkan.');
        }

        return redirect()
            ->route('asset-depreciation.index', $asset->id)
            ->with('success', 'Entri penyusutan berhasil ditambahkan.');
    }

    public function show(AssetDepreciationEntry $assetDepreciation)
    {
        return Inertia::render('AssetDepreciation/Show', [
            'asset' => $assetDepreciation->asset->load(['branch.branchGroup.company']),
            'entry' => $assetDepreciation,
        ]);
    }

    public function edit(AssetDepreciationEntry $assetDepreciation)
    {
        return Inertia::render('AssetDepreciation/Edit', [
            'asset' => $assetDepreciation->asset->load(['branch.branchGroup.company']),
            'entry' => $assetDepreciation,
            'accounts' => Account::where('type', 'akumulasi_penyusutan')
                ->where('is_parent', false)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(Request $request, AssetDepreciationEntry $assetDepreciation)
    {
        $validated = $request->validate([
            'entry_date' => 'required|date',
            'type' => 'required|in:depreciation,amortization',
            'amount' => 'required|numeric|min:0',
            'cumulative_amount' => 'required|numeric|min:0',
            'remaining_value' => 'required|numeric|min:0',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'notes' => 'nullable|string|max:1000',
        ]);

        $assetDepreciation->update($validated);

        return redirect()
            ->route('asset-depreciation.index', $assetDepreciation->asset->id)
            ->with('success', 'Entri penyusutan berhasil diperbarui.');
    }

    public function destroy(AssetDepreciationEntry $assetDepreciation)
    {
        $assetId = $assetDepreciation->asset_id;
        $assetDepreciation->delete();

        return redirect()
            ->route('asset-depreciation.index', $assetId)
            ->with('success', 'Entri penyusutan berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_depreciation_entries,id'
        ]);

        $assetId = AssetDepreciationEntry::find($validated['ids'][0])->asset_id;

        DB::transaction(function () use ($validated) {
            foreach ($validated['ids'] as $id) {
                $entry = AssetDepreciationEntry::find($id);
                $entry->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-depreciation.index', $assetId) . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Entri penyusutan yang dipilih berhasil dihapus.');
        }

        return redirect()->back()
            ->with('success', 'Entri penyusutan yang dipilih berhasil dihapus.');
    }

    public function process(Request $request, AssetDepreciationEntry $assetDepreciation)
    {
        $validated = $request->validate([
            'journal_date' => 'required|date',
            'debit_account_id' => 'required|exists:accounts,id',
            'credit_account_id' => 'required|exists:accounts,id',
            'notes' => 'nullable|string',
        ]);

        $assetDepreciation->update([
            'status' => 'processed',
            'notes' => $validated['notes'] ?? $assetDepreciation->notes,
        ]);

        return redirect()->back()
            ->with('success', 'Penyusutan berhasil diproses.');
    }

    public function generateSchedule(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'depreciation_method' => 'required|in:straight-line,declining-balance',
            'total_periods' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $purchaseCost = $asset->purchase_cost;
        $salvageValue = $asset->salvage_value;
        $depreciableAmount = $purchaseCost - $salvageValue;
        $periodLength = ($validated['depreciation_method'] === 'straight-line') 
            ? $depreciableAmount / $validated['total_periods']
            : null;
        
        $startDate = new \DateTime($validated['start_date']);
        $endDate = new \DateTime($validated['start_date']);
        $interval = \DateInterval::createFromDateString('1 month');
        
        DB::transaction(function () use ($asset, $validated, $depreciableAmount, $periodLength, $startDate, $endDate, $interval, $purchaseCost, $salvageValue) {
            // Clean existing scheduled entries if requested
            if ($request->has('replace_existing') && $request->get('replace_existing')) {
                $asset->depreciationEntries()->where('status', 'scheduled')->delete();
            }
            
            $remainingValue = $purchaseCost;
            $cumulativeDepreciation = 0;
            
            for ($i = 0; $i < $validated['total_periods']; $i++) {
                // Calculate period dates
                $periodStart = clone $startDate;
                $periodStart->add(new \DateInterval('P' . $i . 'M'));
                
                $periodEnd = clone $periodStart;
                $periodEnd->add($interval);
                $periodEnd->modify('-1 day');
                
                // Calculate amount based on method
                if ($validated['depreciation_method'] === 'straight-line') {
                    $amount = $periodLength;
                } else {
                    // Declining balance method
                    $rate = (2 / $validated['total_periods']);
                    $amount = ($remainingValue - $salvageValue) * $rate;
                }
                
                // Ensure we don't depreciate below salvage value
                if ($remainingValue - $amount < $salvageValue) {
                    $amount = $remainingValue - $salvageValue;
                }
                
                // Skip if amount is zero or negative
                if ($amount <= 0) {
                    continue;
                }
                
                $cumulativeDepreciation += $amount;
                $remainingValue -= $amount;
                
                // Create entry
                $asset->depreciationEntries()->create([
                    'entry_date' => $periodEnd->format('Y-m-d'),
                    'type' => 'depreciation',
                    'status' => 'scheduled',
                    'amount' => $amount,
                    'cumulative_amount' => $cumulativeDepreciation,
                    'remaining_value' => $remainingValue,
                    'period_start' => $periodStart->format('Y-m-d'),
                    'period_end' => $periodEnd->format('Y-m-d'),
                    'notes' => $validated['notes'] ?? 'Auto-generated depreciation schedule',
                ]);
                
                // Stop if we've reached salvage value
                if (abs($remainingValue - $salvageValue) < 0.01) {
                    break;
                }
            }
        });
        
        return redirect()
            ->route('asset-depreciation.index', $asset->id)
            ->with('success', 'Jadwal penyusutan berhasil dibuat.');
    }
} 