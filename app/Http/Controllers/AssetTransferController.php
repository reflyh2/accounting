<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AssetTransfersExport;
use Illuminate\Support\Facades\Auth;

class AssetTransferController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_transfers.index_filters', []);
        Session::put('asset_transfers.index_filters', $filters);

        $query = AssetTransfer::with(['fromCompany', 'fromBranch', 'toCompany', 'toBranch', 'assetTransferDetails.asset']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('fromBranch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
                  ->orWhereHas('toBranch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['from_company_id'])) {
            $query->whereIn('from_company_id', $filters['from_company_id']);
        }
        
        if (!empty($filters['from_branch_id'])) {
            $query->whereIn('from_branch_id', $filters['from_branch_id']);
        }

        if (!empty($filters['to_company_id'])) {
            $query->whereIn('to_company_id', $filters['to_company_id']);
        }

        if (!empty($filters['to_branch_id'])) {
            $query->whereIn('to_branch_id', $filters['to_branch_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('transfer_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('transfer_date', '<=', $filters['to_date']);
        }
        
        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'transfer_date';
        $sortOrder = $filters['order'] ?? 'desc';

        if ($sortColumn === 'from_branch.name') {
            $query->join('branches as from_branches', 'asset_transfers.from_branch_id', '=', 'from_branches.id')
                  ->orderBy('from_branches.name', $sortOrder)
                  ->select('asset_transfers.*');
        } elseif ($sortColumn === 'to_branch.name') {
            $query->join('branches as to_branches', 'asset_transfers.to_branch_id', '=', 'to_branches.id')
                  ->orderBy('to_branches.name', $sortOrder)
                  ->select('asset_transfers.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $assetTransfers = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        $branches = Branch::orderBy('name', 'asc')->get();

        return Inertia::render('AssetTransfers/Index', [
            'assetTransfers' => $assetTransfers,
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'statusOptions' => AssetTransfer::statusOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('asset_transfers.index_filters', []);
        
        $companies = Company::orderBy('name', 'asc')->get();
        
        $fromBranches = collect();
        $toBranches = collect();
        $assets = collect();

        if ($request->from_company_id) {
            $fromCompanyId = $request->from_company_id;
            $fromBranches = Branch::whereHas('branchGroup', function ($query) use ($fromCompanyId) {
                $query->where('company_id', $fromCompanyId);
            })->orderBy('name', 'asc')->get();
        }

        if ($request->to_company_id) {
            $toCompanyId = $request->to_company_id;
            $toBranches = Branch::whereHas('branchGroup', function ($query) use ($toCompanyId) {
                $query->where('company_id', $toCompanyId);
            })->orderBy('name', 'asc')->get();
        }

        if ($request->from_branch_id) {
            $assets = Asset::where('branch_id', $request->from_branch_id)->orderBy('name', 'asc')->get();
        }

        return Inertia::render('AssetTransfers/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'fromBranches' => fn() => $fromBranches,
            'toBranches' => fn() => $toBranches,
            'assets' => fn() => $assets,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_company_id' => 'required|exists:companies,id',
            'from_branch_id' => 'required|exists:branches,id',
            'to_company_id' => 'required|exists:companies,id',
            'to_branch_id' => 'required|exists:branches,id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.asset_id' => 'required|exists:assets,id',
            'details.*.notes' => 'nullable|string',
        ]);

        $assetTransfer = DB::transaction(function () use ($validated, $request) {
            $transfer = AssetTransfer::create([
                'from_company_id' => $validated['from_company_id'],
                'from_branch_id' => $validated['from_branch_id'],
                'to_company_id' => $validated['to_company_id'],
                'to_branch_id' => $validated['to_branch_id'],
                'transfer_date' => $validated['transfer_date'],
                'notes' => $validated['notes'],
                'status' => 'draft',
            ]);

            foreach ($validated['details'] as $detail) {
                $transfer->assetTransferDetails()->create([
                    'asset_id' => $detail['asset_id'],
                    'notes' => $detail['notes'],
                ]);
            }
            return $transfer;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-transfers.create')
                ->with('success', 'Transfer Aset berhasil dibuat. Silakan buat transfer lainnya.');
        }

        return redirect()->route('asset-transfers.show', $assetTransfer->id)
            ->with('success', 'Transfer Aset berhasil dibuat.');
    }

    public function show(Request $request, AssetTransfer $assetTransfer)
    {
        $filters = Session::get('asset_transfers.index_filters', []);
        $assetTransfer->load(['fromCompany', 'fromBranch', 'toCompany', 'toBranch', 'assetTransferDetails.asset', 'creator', 'approver', 'rejector', 'canceller']);

        return Inertia::render('AssetTransfers/Show', [
            'assetTransfer' => $assetTransfer,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, AssetTransfer $assetTransfer)
    {
        if ($assetTransfer->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya transfer dengan status draft yang bisa diubah.');
        }
        
        $filters = Session::get('asset_transfers.index_filters', []);
        $assetTransfer->load(['fromCompany', 'fromBranch', 'toCompany', 'toBranch', 'assetTransferDetails.asset']);

        $fromCompanyId = $assetTransfer->from_company_id;
        if ($request->from_company_id) {
            $fromCompanyId = $request->from_company_id;
        }

        $toCompanyId = $assetTransfer->to_company_id;
        if ($request->to_company_id) {
            $toCompanyId = $request->to_company_id;
        }
        
        $fromBranchId = $assetTransfer->from_branch_id;
        if ($request->from_branch_id) {
            $fromBranchId = $request->from_branch_id;
        }

        return Inertia::render('AssetTransfers/Edit', [
            'assetTransfer' => $assetTransfer,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'fromBranches' => Branch::whereHas('branchGroup', function ($query) use ($fromCompanyId) {
                $query->where('company_id', $fromCompanyId);
            })->orderBy('name', 'asc')->get(),
            'toBranches' => Branch::whereHas('branchGroup', function ($query) use ($toCompanyId) {
                $query->where('company_id', $toCompanyId);
            })->orderBy('name', 'asc')->get(),
            'assets' => Asset::where('branch_id', $fromBranchId)->orderBy('name', 'asc')->get(),
        ]);
    }

    public function update(Request $request, AssetTransfer $assetTransfer)
    {
        if ($assetTransfer->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya transfer dengan status draft yang bisa diubah.');
        }

        $validated = $request->validate([
            'from_company_id' => 'required|exists:companies,id',
            'from_branch_id' => 'required|exists:branches,id',
            'to_company_id' => 'required|exists:companies,id',
            'to_branch_id' => 'required|exists:branches,id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.id' => 'nullable|exists:asset_transfer_details,id',
            'details.*.asset_id' => 'required|exists:assets,id',
            'details.*.notes' => 'nullable|string',
        ]);

        if ($assetTransfer->from_branch_id != $validated['from_branch_id'] || $assetTransfer->from_company_id != $validated['from_company_id']) {
             return redirect()->back()->with('error', 'Perusahaan/Cabang asal tidak dapat diubah.');
        }

        DB::transaction(function () use ($validated, $assetTransfer) {
            $assetTransfer->update([
                'to_company_id' => $validated['to_company_id'],
                'to_branch_id' => $validated['to_branch_id'],
                'transfer_date' => $validated['transfer_date'],
                'notes' => $validated['notes'],
            ]);

            $existingDetailIds = $assetTransfer->assetTransferDetails->pluck('id')->toArray();
            $updatedDetailIds = [];

            foreach ($validated['details'] as $detail) {
                $detailData = [
                    'asset_id' => $detail['asset_id'],
                    'notes' => $detail['notes'],
                ];

                if (isset($detail['id']) && in_array($detail['id'], $existingDetailIds)) {
                    $assetTransfer->assetTransferDetails()->find($detail['id'])->update($detailData);
                    $updatedDetailIds[] = $detail['id'];
                } else {
                    $newDetail = $assetTransfer->assetTransferDetails()->create($detailData);
                    $updatedDetailIds[] = $newDetail->id;
                }
            }

            $detailsToDelete = array_diff($existingDetailIds, $updatedDetailIds);
            if (!empty($detailsToDelete)) {
                $assetTransfer->assetTransferDetails()->whereIn('id', $detailsToDelete)->delete();
            }
        });

        return redirect()->route('asset-transfers.show', $assetTransfer->id)
            ->with('success', 'Transfer Aset berhasil diubah.');
    }

    public function destroy(Request $request, AssetTransfer $assetTransfer)
    {
        if ($assetTransfer->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya transfer dengan status draft yang bisa dihapus.');
        }

        DB::transaction(function () use ($assetTransfer) {
            $assetTransfer->assetTransferDetails()->delete();
            $assetTransfer->delete();
        });

        return Redirect::route('asset-transfers.index')
            ->with('success', 'Transfer Aset berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_transfers,id',
        ]);

        DB::transaction(function () use ($validated) {
            $transfers = AssetTransfer::whereIn('id', $validated['ids'])->where('status', 'draft')->get();
            foreach ($transfers as $transfer) {
                $transfer->assetTransferDetails()->delete();
                $transfer->delete();
            }
        });

        return redirect()->route('asset-transfers.index')->with('success', 'Transfer Aset terpilih berhasil dihapus.');
    }

    public function approve(AssetTransfer $assetTransfer)
    {
        if ($assetTransfer->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya transfer dengan status draft yang bisa disetujui.');
        }

        DB::transaction(function () use ($assetTransfer) {
            $assetTransfer->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Move assets
            foreach ($assetTransfer->assetTransferDetails as $detail) {
                $asset = $detail->asset;
                $asset->update([
                    'company_id' => $assetTransfer->to_company_id,
                    'branch_id' => $assetTransfer->to_branch_id,
                ]);
            }
            
            // TODO: Generate Journal Entry if inter-company
        });

        return redirect()->route('asset-transfers.show', $assetTransfer->id)->with('success', 'Transfer Aset berhasil disetujui.');
    }

    public function reject(AssetTransfer $assetTransfer)
    {
        if ($assetTransfer->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya transfer dengan status draft yang bisa ditolak.');
        }

        $assetTransfer->update([
            'status' => 'rejected',
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
        ]);

        return redirect()->route('asset-transfers.show', $assetTransfer->id)->with('success', 'Transfer Aset berhasil ditolak.');
    }

    public function cancel(AssetTransfer $assetTransfer)
    {
        if ($assetTransfer->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya transfer dengan status draft yang bisa dibatalkan.');
        }

        $assetTransfer->update([
            'status' => 'cancelled',
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
        ]);

        return redirect()->route('asset-transfers.show', $assetTransfer->id)->with('success', 'Transfer Aset berhasil dibatalkan.');
    }
} 