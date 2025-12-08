<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ComponentIssue;
use App\Models\Location;
use App\Models\Lot;
use App\Models\Serial;
use App\Models\WorkOrder;
use App\Services\Manufacturing\ManufacturingService;
use Illuminate\Http\Request;
use App\Exports\ComponentIssuesExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class ComponentIssueController extends Controller
{
    public function __construct(
        private readonly ManufacturingService $manufacturingService
    ) {
    }
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('component_issues.index_filters', []);
        Session::put('component_issues.index_filters', $filters);

        $query = ComponentIssue::with(['branch.branchGroup.company', 'workOrder', 'componentIssueLines.componentProduct'])
            ->withSum('componentIssueLines', 'total_cost');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(issue_number)'), 'like', '%' . strtolower($filters['search']) . '%')
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
            $query->whereDate('issue_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('issue_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'issue_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $componentIssues = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        $workOrders = WorkOrder::with(['bom.finishedProduct'])
            ->whereIn('status', ['released', 'in_progress'])
            ->orderBy('wo_number', 'asc')
            ->get();

        return Inertia::render('ComponentIssues/Index', [
            'componentIssues' => $componentIssues,
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
        $filters = Session::get('component_issues.index_filters', []);
        
        return Inertia::render('ComponentIssues/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'locations' => fn() => Location::where('branch_id', $request->input('branch_id'))
                ->where('is_active', true)
                ->orderBy('code', 'asc')
                ->get(),
            'workOrders' => fn() => WorkOrder::with(['bom.finishedProduct', 'bom.bomLines.componentProduct', 'bom.bomLines.componentProductVariant', 'bom.bomLines.uom'])
                ->whereIn('status', ['released', 'in_progress'])
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
            'location_from_id' => 'nullable|exists:locations,id',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.bom_line_id' => 'nullable|exists:bill_of_material_lines,id',
            'lines.*.component_product_id' => 'required|exists:products,id',
            'lines.*.component_product_variant_id' => 'nullable|exists:product_variants,id',
            'lines.*.quantity_issued' => 'required|numeric|min:0.000001',
            'lines.*.uom_id' => 'required|exists:uoms,id',
            'lines.*.lot_id' => 'nullable|exists:lots,id',
            'lines.*.serial_id' => 'nullable|exists:serials,id',
            'lines.*.backflush' => 'boolean',
            'lines.*.notes' => 'nullable|string',
        ]);

        $workOrder = WorkOrder::with('branch.branchGroup')->findOrFail($validated['work_order_id']);

        $componentIssue = DB::transaction(function () use ($validated, $request, $workOrder) {
            $componentIssue = ComponentIssue::create([
                'work_order_id' => $validated['work_order_id'],
                'company_id' => $workOrder->company_id,
                'branch_id' => $validated['branch_id'],
                'location_from_id' => $validated['location_from_id'] ?? null,
                'user_global_id' => $request->user()->global_id,
                'issue_date' => $validated['issue_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['lines'] as $index => $line) {
                $componentIssue->componentIssueLines()->create([
                    'line_number' => $index + 1,
                    'bom_line_id' => $line['bom_line_id'] ?? null,
                    'component_product_id' => $line['component_product_id'],
                    'component_product_variant_id' => $line['component_product_variant_id'] ?? null,
                    'quantity_issued' => $line['quantity_issued'],
                    'uom_id' => $line['uom_id'],
                    'lot_id' => $line['lot_id'] ?? null,
                    'serial_id' => $line['serial_id'] ?? null,
                    'backflush' => $line['backflush'] ?? false,
                    'unit_cost' => 0,
                    'total_cost' => 0,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $componentIssue;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('component-issues.create')
                ->with('success', 'Component Issue berhasil dibuat. Silakan buat component issue lainnya.');
        }

        return redirect()->route('component-issues.show', $componentIssue->id)
            ->with('success', 'Component Issue berhasil dibuat.');
    }

    public function show(Request $request, ComponentIssue $componentIssue)
    {
        $filters = Session::get('component_issues.index_filters', []);
        $componentIssue->load([
            'branch.branchGroup.company',
            'locationFrom',
            'workOrder.bom.finishedProduct',
            'componentIssueLines.componentProduct',
            'componentIssueLines.componentProductVariant',
            'componentIssueLines.uom',
            'componentIssueLines.lot',
            'componentIssueLines.serial',
            'componentIssueLines.bomLine',
        ]);
        
        return Inertia::render('ComponentIssues/Show', [
            'componentIssue' => $componentIssue,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, ComponentIssue $componentIssue)
    {
        $filters = Session::get('component_issues.index_filters', []);
        $componentIssue->load([
            'branch.branchGroup',
            'workOrder.bom.finishedProduct',
            'workOrder.bom.bomLines.componentProduct',
            'workOrder.bom.bomLines.componentProductVariant',
            'workOrder.bom.bomLines.uom',
            'componentIssueLines.componentProduct',
            'componentIssueLines.componentProductVariant',
            'componentIssueLines.uom',
            'componentIssueLines.bomLine',
        ]);
        
        $companyId = $componentIssue->branch->branchGroup->company_id;
        
        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        return Inertia::render('ComponentIssues/Edit', [
            'componentIssue' => $componentIssue,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'locations' => Location::where('branch_id', $componentIssue->branch_id)
                ->where('is_active', true)
                ->orderBy('code', 'asc')
                ->get(),
            'workOrders' => WorkOrder::with(['bom.finishedProduct', 'bom.bomLines.componentProduct', 'bom.bomLines.componentProductVariant', 'bom.bomLines.uom'])
                ->whereIn('status', ['released', 'in_progress'])
                ->where('company_id', $companyId)
                ->orderBy('wo_number', 'asc')
                ->get(),
        ]);
    }

    public function update(Request $request, ComponentIssue $componentIssue)
    {
        $validated = $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'branch_id' => 'required|exists:branches,id',
            'location_from_id' => 'nullable|exists:locations,id',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.id' => 'nullable|exists:component_issue_lines,id',
            'lines.*.bom_line_id' => 'nullable|exists:bill_of_material_lines,id',
            'lines.*.component_product_id' => 'required|exists:products,id',
            'lines.*.component_product_variant_id' => 'nullable|exists:product_variants,id',
            'lines.*.quantity_issued' => 'required|numeric|min:0.000001',
            'lines.*.uom_id' => 'required|exists:uoms,id',
            'lines.*.lot_id' => 'nullable|exists:lots,id',
            'lines.*.serial_id' => 'nullable|exists:serials,id',
            'lines.*.backflush' => 'boolean',
            'lines.*.notes' => 'nullable|string',
        ]);

        // Check if year has changed
        $oldYear = date('Y', strtotime($componentIssue->issue_date));
        $newYear = date('Y', strtotime($validated['issue_date']));
        if ($oldYear !== $newYear) {
            return redirect()->back()->with('error', 'Tahun component issue tidak dapat diubah.');
        }

        // Check if branch_id has changed
        if ($componentIssue->branch_id !== $validated['branch_id']) {
            return redirect()->back()->with('error', 'Cabang component issue tidak dapat diubah.');
        }

        // Check if company has changed
        $newBranch = Branch::with('branchGroup.company')->find($validated['branch_id']);
        $oldBranch = $componentIssue->branch()->with('branchGroup.company')->first();
        
        if ($newBranch->branchGroup->company_id !== $oldBranch->branchGroup->company_id) {
            return redirect()->back()->with('error', 'Perusahaan component issue tidak dapat diubah.');
        }

        // Check if status is posted, prevent editing
        if ($componentIssue->status === 'posted') {
            return redirect()->back()->with('error', 'Component issue yang sudah diposting tidak dapat diubah.');
        }

        $workOrder = WorkOrder::findOrFail($validated['work_order_id']);

        DB::transaction(function () use ($validated, $componentIssue, $workOrder) {
            $componentIssue->update([
                'work_order_id' => $validated['work_order_id'],
                'company_id' => $workOrder->company_id,
                'location_from_id' => $validated['location_from_id'] ?? null,
                'issue_date' => $validated['issue_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($componentIssue->componentIssueLines as $line) {
                $line->delete();
            }

            foreach ($validated['lines'] as $index => $line) {
                $componentIssue->componentIssueLines()->create([
                    'line_number' => $index + 1,
                    'bom_line_id' => $line['bom_line_id'] ?? null,
                    'component_product_id' => $line['component_product_id'],
                    'component_product_variant_id' => $line['component_product_variant_id'] ?? null,
                    'quantity_issued' => $line['quantity_issued'],
                    'uom_id' => $line['uom_id'],
                    'lot_id' => $line['lot_id'] ?? null,
                    'serial_id' => $line['serial_id'] ?? null,
                    'backflush' => $line['backflush'] ?? false,
                    'unit_cost' => 0,
                    'total_cost' => 0,
                    'notes' => $line['notes'] ?? null,
                ]);
            }
        });

        return redirect()->route('component-issues.edit', $componentIssue->id)
            ->with('success', 'Component Issue berhasil diubah.');
    }

    public function destroy(Request $request, ComponentIssue $componentIssue)
    {
        if ($componentIssue->status === 'posted') {
            return redirect()->back()->with('error', 'Component issue yang sudah diposting tidak dapat dihapus.');
        }

        DB::transaction(function () use ($componentIssue) {
            foreach ($componentIssue->componentIssueLines as $line) {
                $line->delete();
            }
            $componentIssue->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('component-issues.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Component Issue berhasil dihapus.');
        } else {
            return Redirect::route('component-issues.index')
                ->with('success', 'Component Issue berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $componentIssue = ComponentIssue::find($id);
                if ($componentIssue && $componentIssue->status !== 'posted') {
                    foreach ($componentIssue->componentIssueLines as $line) {
                        $line->delete();
                    }
                    $componentIssue->delete();
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('component-issues.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Component Issue berhasil dihapus.');
        }
    }

    private function getFilteredComponentIssues(Request $request)
    {
        $filters = $request->all() ?: Session::get('component_issues.index_filters', []);

        $query = ComponentIssue::with(['branch', 'workOrder', 'componentIssueLines.componentProduct'])
            ->withSum('componentIssueLines', 'total_cost');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(issue_number)'), 'like', '%' . strtolower($filters['search']) . '%')
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
            $query->whereDate('issue_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('issue_date', '<=', $filters['to_date']);
        }

        $sortColumn = $filters['sort'] ?? 'issue_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function post(Request $request, ComponentIssue $componentIssue)
    {
        try {
            $componentIssue = $this->manufacturingService->issueComponents($componentIssue);
        } catch (\Exception $exception) {
            return Redirect::back()
                ->with('error', $exception->getMessage());
        }

        return Redirect::route('component-issues.show', $componentIssue->id)
            ->with('success', 'Component Issue berhasil diposting.');
    }

    public function exportXLSX(Request $request)
    {
        $componentIssues = $this->getFilteredComponentIssues($request);
        return Excel::download(new ComponentIssuesExport($componentIssues), 'component-issues.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $componentIssues = $this->getFilteredComponentIssues($request);
        return Excel::download(new ComponentIssuesExport($componentIssues), 'component-issues.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $componentIssues = $this->getFilteredComponentIssues($request);
        return Excel::download(new ComponentIssuesExport($componentIssues), 'component-issues.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
