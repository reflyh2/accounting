<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDisposal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssetDisposalController extends Controller
{
    public function index(Asset $asset)
    {
        return Inertia::render('AssetDisposals/Index', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'disposals' => $asset->disposals()
                ->with(['asset'])
                ->orderBy('disposal_date', 'desc')
                ->paginate(10),
        ]);
    }

    public function create(Asset $asset)
    {
        if ($asset->status === 'disposed') {
            return redirect()->route('assets.show', $asset->id)
                ->with('error', 'Asset already disposed.');
        }

        return Inertia::render('AssetDisposals/Create', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'currentValue' => $asset->calculateDepreciation(),
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'disposal_date' => 'required|date',
            'disposal_method' => 'required|in:sale,scrap,donation',
            'disposal_amount' => 'required_if:disposal_method,sale|nullable|numeric|min:0',
            'book_value_at_disposal' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $validated['asset_id'] = $asset->id;
        $validated['requested_by'] = Auth::user()->name;
        $validated['status'] = 'pending';
        $validated['gain_loss_amount'] = ($validated['disposal_amount'] ?? 0) - $validated['book_value_at_disposal'];

        $disposal = AssetDisposal::create($validated);

        return redirect()->route('asset-disposals.show', [$asset->id, $disposal->id])
            ->with('success', 'Disposal request created successfully.');
    }

    public function show(Asset $asset, AssetDisposal $disposal)
    {
        return Inertia::render('AssetDisposals/Show', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'disposal' => $disposal,
        ]);
    }

    public function edit(Asset $asset, AssetDisposal $disposal)
    {
        if ($disposal->status !== 'pending') {
            return redirect()->route('asset-disposals.show', [$asset->id, $disposal->id])
                ->with('error', 'Cannot edit a processed disposal.');
        }

        return Inertia::render('AssetDisposals/Edit', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'disposal' => $disposal,
            'currentValue' => $asset->calculateDepreciation(),
        ]);
    }

    public function update(Request $request, Asset $asset, AssetDisposal $disposal)
    {
        if ($disposal->status !== 'pending') {
            return redirect()->back()->with('error', 'Cannot update a processed disposal.');
        }

        $validated = $request->validate([
            'disposal_date' => 'required|date',
            'disposal_method' => 'required|in:sale,scrap,donation',
            'disposal_amount' => 'required_if:disposal_method,sale|nullable|numeric|min:0',
            'book_value_at_disposal' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $validated['gain_loss_amount'] = ($validated['disposal_amount'] ?? 0) - $validated['book_value_at_disposal'];

        $disposal->update($validated);

        return redirect()->route('asset-disposals.show', [$asset->id, $disposal->id])
            ->with('success', 'Disposal request updated successfully.');
    }

    public function destroy(Asset $asset, AssetDisposal $disposal)
    {
        if ($disposal->status !== 'pending') {
            return redirect()->back()->with('error', 'Cannot delete a processed disposal.');
        }

        $disposal->delete();

        return redirect()->route('asset-disposals.index', $asset->id)
            ->with('success', 'Disposal request deleted successfully.');
    }

    public function approve(Asset $asset, AssetDisposal $disposal)
    {
        if ($disposal->status !== 'pending') {
            return redirect()->back()->with('error', 'Disposal already processed.');
        }

        DB::transaction(function () use ($asset, $disposal) {
            $disposal->update([
                'status' => 'completed',
                'approved_by' => Auth::user()->name,
            ]);

            $asset->update([
                'status' => 'disposed',
                'current_value' => 0,
            ]);
        });

        return redirect()->route('asset-disposals.show', [$asset->id, $disposal->id])
            ->with('success', 'Disposal approved successfully.');
    }

    public function cancel(Asset $asset, AssetDisposal $disposal)
    {
        if ($disposal->status !== 'pending') {
            return redirect()->back()->with('error', 'Disposal already processed.');
        }

        $disposal->update([
            'status' => 'cancelled',
            'approved_by' => Auth::user()->name,
        ]);

        return redirect()->route('asset-disposals.show', [$asset->id, $disposal->id])
            ->with('success', 'Disposal cancelled successfully.');
    }
} 