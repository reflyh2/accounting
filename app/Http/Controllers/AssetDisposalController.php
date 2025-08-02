<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetDisposalDetail;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use App\Exports\AssetDisposalsExport;
use Maatwebsite\Excel\Facades\Excel;

class AssetDisposalController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_disposals.index_filters', []);
        Session::put('asset_disposals.index_filters', $filters);

        $query = AssetDisposal::with(['branch.branchGroup.company', 'assetDisposalDetails.asset'])
            ->orderBy($filters['sort'] ?? 'disposal_date', $filters['order'] ?? 'desc');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }
        
        if (!empty($filters['company_id'])) {
            $query->whereHas('branch', function ($query) use ($filters) {
                $query->whereHas('branchGroup', function ($query) use ($filters) {
                    $query->whereIn('company_id', $filters['company_id']);
                });
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }
        
        if (!empty($filters['from_date'])) {
            $query->whereDate('disposal_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('disposal_date', '<=', $filters['to_date']);
        }
        
        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }
        
        if (!empty($filters['disposal_type'])) {
            $query->whereIn('disposal_type', $filters['disposal_type']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $assetDisposals = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        
        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->orderBy('name', 'asc')->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('AssetDisposals/Index', [
            'assetDisposals' => $assetDisposals,
            'companies' => $companies,
            'branches' => $branches,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $filters['sort'] ?? 'disposal_date',
            'order' => $filters['order'] ?? 'desc',
            'statusOptions' => AssetDisposal::statusOptions(),
            'disposalTypeOptions' => AssetDisposal::disposalTypeOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('asset_disposals.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();
        
        $branches = collect();
        $assets = collect();

        if ($request->company_id) {
            $companyId = $request->company_id;
            $branches = Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get();
            
            if ($request->branch_id) {
                $assets = $this->getAvailableAssets($companyId, $request->branch_id);
            }
        }
        
        return Inertia::render('AssetDisposals/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'branches' => fn() => $branches,
            'assets' => fn() => $assets,
            'statusOptions' => AssetDisposal::statusOptions(),
            'disposalTypeOptions' => AssetDisposal::disposalTypeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'disposal_date' => 'required|date',
            'disposal_type' => 'required|string|in:' . implode(',', array_keys(AssetDisposal::disposalTypeOptions())),
            'proceeds_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.asset_id' => 'required|exists:assets,id',
            'details.*.carrying_amount' => 'required|numeric|min:0',
            'details.*.proceeds_amount' => 'required|numeric|min:0',
            'details.*.notes' => 'nullable|string',
        ]);

        $assetDisposal = DB::transaction(function () use ($validated, $request) {
            $disposal = AssetDisposal::create([
                'branch_id' => $validated['branch_id'],
                'disposal_date' => $validated['disposal_date'],
                'disposal_type' => $validated['disposal_type'],
                'proceeds_amount' => $validated['proceeds_amount'] ?? 0,
                'notes' => $validated['notes'],
                'status' => 'draft',
            ]);

            foreach ($validated['details'] as $detail) {
                $disposal->assetDisposalDetails()->create($detail);
            }
            
            // On posting, set asset status = “disposed”
            if ($disposal->status === 'approved') {
                foreach ($disposal->assetDisposalDetails as $detail) {
                    $asset = Asset::find($detail->asset_id);
                    if ($asset) {
                        $asset->status = 'disposed';
                        $asset->save();
                    }
                }
            }

            return $disposal;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-disposals.create')
                ->with('success', 'Dokumen Pelepasan Aset berhasil dibuat. Silakan buat dokumen lainnya.');
        }

        return redirect()->route('asset-disposals.show', $assetDisposal->id)
            ->with('success', 'Dokumen Pelepasan Aset berhasil dibuat.');
    }

    public function show(AssetDisposal $assetDisposal)
    {
        $filters = Session::get('asset_disposals.index_filters', []);
        $assetDisposal->load(['branch.branchGroup.company', 'assetDisposalDetails.asset', 'creator', 'updater', 'approver']);

        return Inertia::render('AssetDisposals/Show', [
            'assetDisposal' => $assetDisposal,
            'filters' => $filters,
            'statusOptions' => AssetDisposal::statusOptions(),
            'disposalTypeOptions' => AssetDisposal::disposalTypeOptions(),
        ]);
    }

    public function edit(Request $request, AssetDisposal $assetDisposal)
    {
        $filters = Session::get('asset_disposals.index_filters', []);
        $assetDisposal->load(['branch.branchGroup', 'assetDisposalDetails.asset']);

        $companyId = $assetDisposal->branch->branchGroup->company_id;
        if ($request->company_id) {
            $companyId = $request->company_id;
        }
        
        $branchId = $assetDisposal->branch_id;
        if ($request->branch_id) {
            $branchId = $request->branch_id;
        }

        return Inertia::render('AssetDisposals/Edit', [
            'assetDisposal' => $assetDisposal,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'assets' => $this->getAvailableAssets($companyId, $branchId, $assetDisposal->id),
            'statusOptions' => AssetDisposal::statusOptions(),
            'disposalTypeOptions' => AssetDisposal::disposalTypeOptions(),
        ]);
    }

    public function update(Request $request, AssetDisposal $assetDisposal)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'disposal_date' => 'required|date',
            'disposal_type' => 'required|string|in:' . implode(',', array_keys(AssetDisposal::disposalTypeOptions())),
            'proceeds_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.id' => 'nullable|exists:asset_disposal_details,id',
            'details.*.asset_id' => 'required|exists:assets,id',
            'details.*.carrying_amount' => 'required|numeric|min:0',
            'details.*.proceeds_amount' => 'required|numeric|min:0',
            'details.*.notes' => 'nullable|string',
        ]);
        
        if ($assetDisposal->branch_id != $validated['branch_id']) {
             return redirect()->back()->with('error', 'Cabang tidak dapat diubah.');
        }

        DB::transaction(function () use ($validated, $assetDisposal) {
            $assetDisposal->update([
                'disposal_date' => $validated['disposal_date'],
                'disposal_type' => $validated['disposal_type'],
                'proceeds_amount' => $validated['proceeds_amount'] ?? 0,
                'notes' => $validated['notes'],
            ]);

            $existingDetailIds = $assetDisposal->assetDisposalDetails->pluck('id')->toArray();
            $updatedDetailIds = [];

            foreach ($validated['details'] as $detail) {
                $detailData = [
                    'asset_id' => $detail['asset_id'],
                    'carrying_amount' => $detail['carrying_amount'],
                    'proceeds_amount' => $detail['proceeds_amount'],
                    'notes' => $detail['notes'],
                ];

                if (isset($detail['id']) && in_array($detail['id'], $existingDetailIds)) {
                    $assetDisposal->assetDisposalDetails()->find($detail['id'])->update($detailData);
                    $updatedDetailIds[] = $detail['id'];
                } else {
                    $newDetail = $assetDisposal->assetDisposalDetails()->create($detailData);
                    $updatedDetailIds[] = $newDetail->id;
                }
            }

            $detailsToDelete = array_diff($existingDetailIds, $updatedDetailIds);
            if (!empty($detailsToDelete)) {
                $assetDisposal->assetDisposalDetails()->whereIn('id', $detailsToDelete)->delete();
            }
        });

        return redirect()->route('asset-disposals.show', $assetDisposal->id)
            ->with('success', 'Dokumen Pelepasan Aset berhasil diubah.');
    }

    public function destroy(Request $request, AssetDisposal $assetDisposal)
    {
        DB::transaction(function () use ($assetDisposal) {
            $assetDisposal->assetDisposalDetails()->delete();
            $assetDisposal->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-disposals.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Dokumen Pelepasan Aset berhasil dihapus.');
        }
        
        return Redirect::route('asset-disposals.index')
            ->with('success', 'Dokumen Pelepasan Aset berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_disposals,id',
        ]);

        DB::transaction(function () use ($validated) {
            $disposals = AssetDisposal::whereIn('id', $validated['ids'])->get();
            foreach ($disposals as $disposal) {
                $disposal->assetDisposalDetails()->delete();
                $disposal->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-disposals.index') . ($currentQuery ? '?' . $currentQuery : '');
            return Redirect::to($redirectUrl)
                ->with('success', 'Dokumen Pelepasan Aset berhasil dihapus.');
        }
        
        return redirect()->route('asset-disposals.index')->with('success', 'Dokumen Pelepasan Aset terpilih berhasil dihapus.');
    }

    private function getFilteredAssetDisposals(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_disposals.index_filters', []);
        $query = AssetDisposal::with(['branch.branchGroup.company', 'assetDisposalDetails.asset'])
            ->orderBy($filters['sort'] ?? 'disposal_date', $filters['order'] ?? 'desc');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }
        
        if (!empty($filters['company_id'])) {
            $query->whereHas('branch', function ($query) use ($filters) {
                $query->whereHas('branchGroup', function ($query) use ($filters) {
                    $query->whereIn('company_id', $filters['company_id']);
                });
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }
        
        if (!empty($filters['from_date'])) {
            $query->whereDate('disposal_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('disposal_date', '<=', $filters['to_date']);
        }
        
        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }
        
        if (!empty($filters['disposal_type'])) {
            $query->whereIn('disposal_type', $filters['disposal_type']);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $assetDisposals = $this->getFilteredAssetDisposals($request);
        return Excel::download(new AssetDisposalsExport($assetDisposals), 'asset-disposals.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $assetDisposals = $this->getFilteredAssetDisposals($request);
        return Excel::download(new AssetDisposalsExport($assetDisposals), 'asset-disposals.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
         return redirect()->back()->with('error', 'Ekspor PDF belum diimplementasikan.');
    }

    public function print(AssetDisposal $assetDisposal)
    {
        $assetDisposal->load(['branch.branchGroup.company', 'assetDisposalDetails.asset', 'creator', 'updater', 'approver']);

        return Inertia::render('AssetDisposals/Print', [
            'assetDisposal' => $assetDisposal,
        ]);
    }
    
    private function getAvailableAssets($companyId, $branchId, $excludeDisposalId = null)
    {
        $usedAssetIds = AssetDisposalDetail::query()
            ->when($excludeDisposalId, function ($query, $excludeDisposalId) {
                $query->whereHas('assetDisposal', function ($subQuery) use ($excludeDisposalId) {
                    $subQuery->where('id', '!=', $excludeDisposalId);
                });
            })
            ->pluck('asset_id')
            ->unique()
            ->toArray();

        return Asset::whereNotIn('id', $usedAssetIds)
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', '!=', 'disposed')
            ->orderBy('name', 'asc')
            ->get();
    }
} 