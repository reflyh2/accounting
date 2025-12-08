<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Company;
use App\Models\WorkOrder;
use App\Models\BillOfMaterial;
use App\Models\Location;
use Illuminate\Http\Request;
use App\Exports\WorkOrdersExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('work_orders.index_filters', []);

        Session::put('work_orders.index_filters', $filters);

        $query = WorkOrder::with(['branch.branchGroup.company', 'bom.finishedProduct', 'bom.finishedProductVariant', 'bom.finishedUom', 'wipLocation', 'user']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(wo_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('bom.finishedProduct', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', $filters['company_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['bom_id'])) {
            $query->where('bom_id', $filters['bom_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('scheduled_start_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('scheduled_start_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'scheduled_start_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $workOrders = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->orderBy('name', 'asc')->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        $boms = BillOfMaterial::where('status', 'active')
            ->with('finishedProduct')
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('WorkOrders/Index', [
            'workOrders' => $workOrders,
            'companies' => $companies,
            'branches' => $branches,
            'boms' => $boms,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('work_orders.index_filters', []);

        return Inertia::render('WorkOrders/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'boms' => fn() => BillOfMaterial::where('status', 'active')
                ->where('company_id', $request->input('company_id'))
                ->with([
                    'finishedProduct.variants',
                    'finishedProductVariant',
                    'finishedUom',
                    'bomLines.componentProduct',
                    'bomLines.componentProductVariant',
                    'bomLines.uom'
                ])
                ->orderBy('name', 'asc')
                ->get(),
            'locations' => fn() => Location::whereHas('branch', function ($query) use ($request) {
                $query->where('id', $request->input('branch_id'));
            })->orderBy('name', 'asc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'bom_id' => 'required|exists:bill_of_materials,id',
            'finished_product_variant_id' => 'nullable|exists:product_variants,id',
            'wip_location_id' => 'nullable|exists:locations,id',
            'quantity_planned' => 'required|numeric|min:0.001',
            'scheduled_start_date' => 'nullable|date',
            'scheduled_end_date' => 'nullable|date|after_or_equal:scheduled_start_date',
            'notes' => 'nullable|string',
        ]);

        $workOrder = WorkOrder::create([
            'company_id' => $validated['company_id'],
            'branch_id' => $validated['branch_id'],
            'user_global_id' => $request->user()->global_id,
            'bom_id' => $validated['bom_id'],
            'finished_product_variant_id' => $validated['finished_product_variant_id'],
            'wip_location_id' => $validated['wip_location_id'],
            'quantity_planned' => $validated['quantity_planned'],
            'scheduled_start_date' => $validated['scheduled_start_date'],
            'scheduled_end_date' => $validated['scheduled_end_date'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('work-orders.show', $workOrder->id)
            ->with('success', 'Work Order berhasil dibuat.');
    }

    public function show(Request $request, WorkOrder $workOrder)
    {
        $filters = Session::get('work_orders.index_filters', []);
        $workOrder->load([
            'branch.branchGroup.company',
            'bom.finishedProduct',
            'bom.finishedProductVariant',
            'bom.finishedUom',
            'bom.bomLines.componentProduct',
            'bom.bomLines.componentProductVariant',
            'bom.bomLines.uom',
            'finishedProductVariant',
            'wipLocation',
            'user',
            'workOrderIssues.productVariant',
            'workOrderReceipts.productVariant'
        ]);

        return Inertia::render('WorkOrders/Show', [
            'workOrder' => $workOrder,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, WorkOrder $workOrder)
    {
        $filters = Session::get('work_orders.index_filters', []);
        $workOrder->load(['branch.branchGroup', 'bom', 'finishedProductVariant']);

        return Inertia::render('WorkOrders/Edit', [
            'workOrder' => $workOrder,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($workOrder) {
                $query->where('company_id', $workOrder->company_id);
            })->orderBy('name', 'asc')->get(),
            'boms' => BillOfMaterial::where('status', 'active')
                ->whereHas('branch.branchGroup', function ($query) use ($workOrder) {
                    $query->where('company_id', $workOrder->company_id);
                })
                ->with([
                    'finishedProduct.variants',
                    'finishedProductVariant',
                    'finishedUom',
                    'bomLines.componentProduct',
                    'bomLines.componentProductVariant',
                    'bomLines.uom'
                ])
                ->orderBy('name', 'asc')
                ->get(),
            'locations' => Location::whereHas('branch', function ($query) use ($workOrder) {
                $query->where('id', $workOrder->branch_id);
            })->orderBy('name', 'asc')->get(),
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'bom_id' => 'required|exists:bill_of_materials,id',
            'finished_product_variant_id' => 'nullable|exists:product_variants,id',
            'wip_location_id' => 'nullable|exists:locations,id',
            'quantity_planned' => 'required|numeric|min:0.001',
            'scheduled_start_date' => 'nullable|date',
            'scheduled_end_date' => 'nullable|date|after_or_equal:scheduled_start_date',
            'notes' => 'nullable|string',
        ]);

        // Check if company has changed
        if ($workOrder->company_id !== $validated['company_id']) {
            return redirect()->back()->with('error', 'Perusahaan Work Order tidak dapat diubah.');
        }

        // Check if work order can be edited (only draft status)
        if (!in_array($workOrder->status, ['draft'])) {
            return redirect()->back()->with('error', 'Work Order hanya dapat diubah jika status masih draft.');
        }

        $workOrder->update([
            'branch_id' => $validated['branch_id'],
            'bom_id' => $validated['bom_id'],
            'finished_product_variant_id' => $validated['finished_product_variant_id'],
            'wip_location_id' => $validated['wip_location_id'],
            'quantity_planned' => $validated['quantity_planned'],
            'scheduled_start_date' => $validated['scheduled_start_date'],
            'scheduled_end_date' => $validated['scheduled_end_date'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('work-orders.edit', $workOrder->id)
            ->with('success', 'Work Order berhasil diubah.');
    }

    public function transition(Request $request, WorkOrder $workOrder)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,released,in_progress,completed,cancelled',
        ]);

        try {
            $workOrder->transitionTo($validated['status']);
            return redirect()->back()->with('success', 'Status Work Order berhasil diubah.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, WorkOrder $workOrder)
    {
        // Only allow deletion of draft work orders
        if ($workOrder->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya Work Order dengan status draft yang dapat dihapus.');
        }

        DB::transaction(function () use ($workOrder) {
            $workOrder->workOrderIssues()->delete();
            $workOrder->workOrderReceipts()->delete();
            $workOrder->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('work-orders.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Work Order berhasil dihapus.');
        } else {
            return Redirect::route('work-orders.index')
                ->with('success', 'Work Order berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $workOrderIds = $request->input('ids', []);

        // Check if all work orders are in draft status
        $nonDraftWorkOrders = WorkOrder::whereIn('id', $workOrderIds)
            ->where('status', '!=', 'draft')
            ->count();

        if ($nonDraftWorkOrders > 0) {
            return redirect()->back()->with('error', 'Hanya Work Order dengan status draft yang dapat dihapus.');
        }

        DB::transaction(function () use ($workOrderIds) {
            foreach ($workOrderIds as $id) {
                $workOrder = WorkOrder::find($id);
                $workOrder->workOrderIssues()->delete();
                $workOrder->workOrderReceipts()->delete();
                $workOrder->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('work-orders.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Work Order berhasil dihapus.');
        }
    }

    private function getFilteredWorkOrders(Request $request)
    {
        $filters = $request->all() ?: Session::get('work_orders.index_filters', []);

        $query = WorkOrder::with(['branch', 'bom.finishedProduct'])
            ->withSum('workOrderIssues', 'quantity_issued')
            ->withSum('workOrderReceipts', 'quantity_received');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(wo_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('bom.finishedProduct', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', $filters['company_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['bom_id'])) {
            $query->where('bom_id', $filters['bom_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('scheduled_start_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('scheduled_start_date', '<=', $filters['to_date']);
        }

        $sortColumn = $filters['sort'] ?? 'scheduled_start_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $workOrders = $this->getFilteredWorkOrders($request);
        return Excel::download(new WorkOrdersExport($workOrders), 'work_orders.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $workOrders = $this->getFilteredWorkOrders($request);
        return Excel::download(new WorkOrdersExport($workOrders), 'work_orders.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $workOrders = $this->getFilteredWorkOrders($request);
        return Excel::download(new WorkOrdersExport($workOrders), 'work_orders.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
