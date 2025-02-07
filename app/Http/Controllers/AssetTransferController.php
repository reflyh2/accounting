<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetTransfer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssetTransferController extends Controller
{
    public function index(Asset $asset)
    {
        return Inertia::render('AssetTransfers/Index', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'transfers' => $asset->transfers()
                ->with(['asset'])
                ->orderBy('transfer_date', 'desc')
                ->paginate(10),
        ]);
    }

    public function create(Asset $asset)
    {
        if ($asset->status === 'disposed') {
            return redirect()->route('assets.show', $asset->id)
                ->with('error', 'Cannot transfer a disposed asset.');
        }

        return Inertia::render('AssetTransfers/Create', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'from_department' => 'required|string',
            'to_department' => 'required|string|different:from_department',
            'from_location' => 'required|string',
            'to_location' => 'required|string|different:from_location',
            'transfer_date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $validated['asset_id'] = $asset->id;
        $validated['requested_by'] = Auth::user()->name;
        $validated['status'] = 'pending';

        $transfer = AssetTransfer::create($validated);

        return redirect()->route('asset-transfers.show', [$asset->id, $transfer->id])
            ->with('success', 'Transfer request created successfully.');
    }

    public function show(Asset $asset, AssetTransfer $transfer)
    {
        return Inertia::render('AssetTransfers/Show', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'transfer' => $transfer,
        ]);
    }

    public function edit(Asset $asset, AssetTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return redirect()->route('asset-transfers.show', [$asset->id, $transfer->id])
                ->with('error', 'Cannot edit a processed transfer.');
        }

        return Inertia::render('AssetTransfers/Edit', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'transfer' => $transfer,
        ]);
    }

    public function update(Request $request, Asset $asset, AssetTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return redirect()->back()->with('error', 'Cannot update a processed transfer.');
        }

        $validated = $request->validate([
            'from_department' => 'required|string',
            'to_department' => 'required|string|different:from_department',
            'from_location' => 'required|string',
            'to_location' => 'required|string|different:from_location',
            'transfer_date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $transfer->update($validated);

        return redirect()->route('asset-transfers.show', [$asset->id, $transfer->id])
            ->with('success', 'Transfer request updated successfully.');
    }

    public function destroy(Asset $asset, AssetTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return redirect()->back()->with('error', 'Cannot delete a processed transfer.');
        }

        $transfer->delete();

        return redirect()->route('asset-transfers.index', $asset->id)
            ->with('success', 'Transfer request deleted successfully.');
    }

    public function approve(Asset $asset, AssetTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return redirect()->back()->with('error', 'Transfer already processed.');
        }

        DB::transaction(function () use ($asset, $transfer) {
            $transfer->update([
                'status' => 'completed',
                'approved_by' => Auth::user()->name,
            ]);

            $asset->update([
                'department' => $transfer->to_department,
                'location' => $transfer->to_location,
            ]);
        });

        return redirect()->route('asset-transfers.show', [$asset->id, $transfer->id])
            ->with('success', 'Transfer approved successfully.');
    }

    public function cancel(Asset $asset, AssetTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return redirect()->back()->with('error', 'Transfer already processed.');
        }

        $transfer->update([
            'status' => 'cancelled',
            'approved_by' => Auth::user()->name,
        ]);

        return redirect()->route('asset-transfers.show', [$asset->id, $transfer->id])
            ->with('success', 'Transfer cancelled successfully.');
    }
} 