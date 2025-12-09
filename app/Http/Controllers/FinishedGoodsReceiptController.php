<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FinishedGoodsReceipt;
use App\Models\Location;
use App\Models\WorkOrder;
use App\Services\Manufacturing\ManufacturingService;
use Illuminate\Http\Request;
use App\Exports\FinishedGoodsReceiptsExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class FinishedGoodsReceiptController extends Controller
{
    public function __construct(
        private readonly ManufacturingService $manufacturingService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('finished_goods_receipts.index_filters', []);
        Session::put('finished_goods_receipts.index_filters', $filters);

        $query = FinishedGoodsReceipt::with(['branch.branchGroup.company', 'workOrder', 'finishedProductVariant']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(receipt_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('workOrder', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(wo_number)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
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

        if (!empty($filters['work_order_id'])) {
            $query->whereIn('work_order_id', $filters['work_order_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('receipt_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('receipt_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'receipt_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $receipts = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        $workOrders = WorkOrder::with(['bom.finishedProduct'])
            ->whereIn('status', ['released', 'in_progress', 'completed'])
            ->orderBy('wo_number', 'asc')
            ->get();

        return Inertia::render('FinishedGoodsReceipts/Index', [
            'finishedGoodsReceipts' => $receipts,
            'companies' => $companies,
            'branches' => $branches,
            'workOrders' => $workOrders,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('finished_goods_receipts.index_filters', []);
        
        return Inertia::render('FinishedGoodsReceipts/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'locations' => fn() => Location::where('branch_id', $request->input('branch_id'))
                ->where('is_active', true)
                ->orderBy('code', 'asc')
                ->get(),
            'workOrders' => fn() => WorkOrder::with(['bom.finishedProduct', 'bom.finishedProductVariant', 'bom.finishedUom', 'componentIssues'])
                ->whereIn('status', ['released', 'in_progress', 'completed'])
                ->whereHas('branch', function ($query) use ($request) {
                    if ($request->input('branch_id')) {
                        $query->where('branch_id', $request->input('branch_id'));
                    }
                })
                ->orderBy('wo_number', 'asc')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'branch_id' => 'required|exists:branches,id',
            'finished_product_variant_id' => 'required|exists:product_variants,id',
            'location_to_id' => 'required|exists:locations,id',
            'uom_id' => 'required|exists:uoms,id',
            'receipt_date' => 'required|date',
            'quantity_good' => 'required|numeric|min:0.000001',
            'quantity_scrap' => 'nullable|numeric|min:0',
            'labor_cost' => 'nullable|numeric|min:0',
            'overhead_cost' => 'nullable|numeric|min:0',
            'lot_id' => 'nullable|exists:lots,id',
            'serial_id' => 'nullable|exists:serials,id',
            'notes' => 'nullable|string',
        ]);

        $workOrder = WorkOrder::with('branch.branchGroup')->findOrFail($validated['work_order_id']);

        $receipt = DB::transaction(function () use ($validated, $request, $workOrder) {
            return FinishedGoodsReceipt::create([
                'work_order_id' => $validated['work_order_id'],
                'company_id' => $workOrder->company_id,
                'branch_id' => $validated['branch_id'],
                'user_global_id' => $request->user()->global_id,
                'finished_product_variant_id' => $validated['finished_product_variant_id'],
                'location_to_id' => $validated['location_to_id'],
                'uom_id' => $validated['uom_id'],
                'receipt_date' => $validated['receipt_date'],
                'quantity_good' => $validated['quantity_good'],
                'quantity_scrap' => $validated['quantity_scrap'] ?? 0,
                'labor_cost' => $validated['labor_cost'] ?? 0,
                'overhead_cost' => $validated['overhead_cost'] ?? 0,
                'lot_id' => $validated['lot_id'] ?? null,
                'serial_id' => $validated['serial_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('finished-goods-receipts.create')
                ->with('success', 'Finished Goods Receipt berhasil dibuat. Silakan buat finished goods receipt lainnya.');
        }

        return redirect()->route('finished-goods-receipts.show', $receipt->id)
            ->with('success', 'Finished Goods Receipt berhasil dibuat.');
    }

    public function show(Request $request, FinishedGoodsReceipt $finishedGoodsReceipt)
    {
        $filters = Session::get('finished_goods_receipts.index_filters', []);
        $finishedGoodsReceipt->load([
            'branch.branchGroup.company',
            'locationTo',
            'workOrder.bom.finishedProduct',
            'workOrder.bom.finishedProductVariant',
            'finishedProductVariant',
            'uom',
            'lot',
            'serial',
            'inventoryTransaction',
        ]);
        
        return Inertia::render('FinishedGoodsReceipts/Show', [
            'finishedGoodsReceipt' => $finishedGoodsReceipt,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, FinishedGoodsReceipt $finishedGoodsReceipt)
    {
        $filters = Session::get('finished_goods_receipts.index_filters', []);
        $finishedGoodsReceipt->load([
            'branch.branchGroup',
            'workOrder.bom.finishedProduct',
            'workOrder.bom.finishedProductVariant',
            'workOrder.bom.finishedUom',
            'finishedProductVariant',
            'locationTo',
            'uom',
            'lot',
            'serial',
        ]);
        
        $companyId = $finishedGoodsReceipt->branch->branchGroup->company_id;
        
        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        return Inertia::render('FinishedGoodsReceipts/Edit', [
            'finishedGoodsReceipt' => $finishedGoodsReceipt,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'locations' => Location::where('branch_id', $finishedGoodsReceipt->branch_id)
                ->where('is_active', true)
                ->orderBy('code', 'asc')
                ->get(),
            'workOrders' => WorkOrder::with(['bom.finishedProduct', 'bom.finishedProductVariant', 'bom.finishedUom', 'componentIssues'])
                ->whereIn('status', ['released', 'in_progress', 'completed'])
                ->where('company_id', $companyId)
                ->orderBy('wo_number', 'asc')
                ->get(),
        ]);
    }

    public function update(Request $request, FinishedGoodsReceipt $finishedGoodsReceipt)
    {
        $validated = $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'branch_id' => 'required|exists:branches,id',
            'finished_product_variant_id' => 'required|exists:product_variants,id',
            'location_to_id' => 'required|exists:locations,id',
            'uom_id' => 'required|exists:uoms,id',
            'receipt_date' => 'required|date',
            'quantity_good' => 'required|numeric|min:0.000001',
            'quantity_scrap' => 'nullable|numeric|min:0',
            'labor_cost' => 'nullable|numeric|min:0',
            'overhead_cost' => 'nullable|numeric|min:0',
            'lot_id' => 'nullable|exists:lots,id',
            'serial_id' => 'nullable|exists:serials,id',
            'notes' => 'nullable|string',
        ]);

        // Check if year has changed
        $oldYear = date('Y', strtotime($finishedGoodsReceipt->receipt_date));
        $newYear = date('Y', strtotime($validated['receipt_date']));
        if ($oldYear !== $newYear) {
            return redirect()->back()->with('error', 'Tahun finished goods receipt tidak dapat diubah.');
        }

        // Check if branch_id has changed
        if ($finishedGoodsReceipt->branch_id !== $validated['branch_id']) {
            return redirect()->back()->with('error', 'Cabang finished goods receipt tidak dapat diubah.');
        }

        // Check if company has changed
        $newBranch = Branch::with('branchGroup.company')->find($validated['branch_id']);
        $oldBranch = $finishedGoodsReceipt->branch()->with('branchGroup.company')->first();
        
        if ($newBranch->branchGroup->company_id !== $oldBranch->branchGroup->company_id) {
            return redirect()->back()->with('error', 'Perusahaan finished goods receipt tidak dapat diubah.');
        }

        // Check if status is posted, prevent editing
        if ($finishedGoodsReceipt->status === 'posted') {
            return redirect()->back()->with('error', 'Finished goods receipt yang sudah diposting tidak dapat diubah.');
        }

        $workOrder = WorkOrder::findOrFail($validated['work_order_id']);

        DB::transaction(function () use ($validated, $finishedGoodsReceipt, $workOrder) {
            $finishedGoodsReceipt->update([
                'work_order_id' => $validated['work_order_id'],
                'company_id' => $workOrder->company_id,
                'finished_product_variant_id' => $validated['finished_product_variant_id'],
                'location_to_id' => $validated['location_to_id'],
                'uom_id' => $validated['uom_id'],
                'receipt_date' => $validated['receipt_date'],
                'quantity_good' => $validated['quantity_good'],
                'quantity_scrap' => $validated['quantity_scrap'] ?? 0,
                'labor_cost' => $validated['labor_cost'] ?? 0,
                'overhead_cost' => $validated['overhead_cost'] ?? 0,
                'lot_id' => $validated['lot_id'] ?? null,
                'serial_id' => $validated['serial_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->route('finished-goods-receipts.edit', $finishedGoodsReceipt->id)
            ->with('success', 'Finished Goods Receipt berhasil diubah.');
    }

    public function destroy(Request $request, FinishedGoodsReceipt $finishedGoodsReceipt)
    {
        if ($finishedGoodsReceipt->status === 'posted') {
            return redirect()->back()->with('error', 'Finished goods receipt yang sudah diposting tidak dapat dihapus.');
        }

        $finishedGoodsReceipt->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('finished-goods-receipts.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Finished Goods Receipt berhasil dihapus.');
        } else {
            return Redirect::route('finished-goods-receipts.index')
                ->with('success', 'Finished Goods Receipt berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $receipt = FinishedGoodsReceipt::find($id);
                if ($receipt && $receipt->status !== 'posted') {
                    $receipt->delete();
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('finished-goods-receipts.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Finished Goods Receipt berhasil dihapus.');
        }
    }

    private function getFilteredFinishedGoodsReceipts(Request $request)
    {
        $filters = $request->all() ?: Session::get('finished_goods_receipts.index_filters', []);

        $query = FinishedGoodsReceipt::with(['branch', 'workOrder', 'finishedProductVariant']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(receipt_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('workOrder', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(wo_number)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
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

        if (!empty($filters['work_order_id'])) {
            $query->whereIn('work_order_id', $filters['work_order_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('receipt_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('receipt_date', '<=', $filters['to_date']);
        }

        $sortColumn = $filters['sort'] ?? 'receipt_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function post(Request $request, FinishedGoodsReceipt $finishedGoodsReceipt)
    {
        try {
            $finishedGoodsReceipt = $this->manufacturingService->receiveFinishedGoods($finishedGoodsReceipt);
        } catch (\Exception $exception) {
            return Redirect::back()
                ->with('error', $exception->getMessage());
        }

        return Redirect::route('finished-goods-receipts.show', $finishedGoodsReceipt->id)
            ->with('success', 'Finished Goods Receipt berhasil diposting.');
    }

    public function exportXLSX(Request $request)
    {
        $receipts = $this->getFilteredFinishedGoodsReceipts($request);
        return Excel::download(new FinishedGoodsReceiptsExport($receipts), 'finished-goods-receipts.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $receipts = $this->getFilteredFinishedGoodsReceipts($request);
        return Excel::download(new FinishedGoodsReceiptsExport($receipts), 'finished-goods-receipts.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $receipts = $this->getFilteredFinishedGoodsReceipts($request);
        return Excel::download(new FinishedGoodsReceiptsExport($receipts), 'finished-goods-receipts.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}