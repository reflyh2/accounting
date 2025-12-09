<?php

namespace App\Http\Controllers;

use App\Exports\ComponentScrapsExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ComponentScrap;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ComponentScrapController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('component_scraps.index_filters', []);
        Session::put('component_scraps.index_filters', $filters);

        $query = ComponentScrap::with([
            'workOrder',
            'workOrder.bom.finishedProduct',
            'workOrder.branch.branchGroup.company',
            'componentProduct',
            'componentProductVariant',
            'uom',
            'bomLine',
            'componentIssueLine',
            'finishedGoodsReceipt',
            'user',
        ]);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(scrap_reason)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhere(DB::raw('lower(notes)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhereHas('workOrder', function ($q) use ($filters) {
                        $q->where(DB::raw('lower(wo_number)'), 'like', '%'.strtolower($filters['search']).'%');
                    })
                    ->orWhereHas('componentProduct', function ($q) use ($filters) {
                        $q->where(DB::raw('lower(name)'), 'like', '%'.strtolower($filters['search']).'%');
                    });
            });
        }

        if (! empty($filters['company_id'])) {
            $query->whereHas('workOrder', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            });
        }

        if (! empty($filters['branch_id'])) {
            $query->whereHas('workOrder', function ($query) use ($filters) {
                $query->whereIn('branch_id', $filters['branch_id']);
            });
        }

        if (! empty($filters['work_order_id'])) {
            $query->whereIn('work_order_id', $filters['work_order_id']);
        }

        if (! empty($filters['is_backflush'])) {
            $query->where('is_backflush', $filters['is_backflush'] === 'true' || $filters['is_backflush'] === true);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('scrap_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('scrap_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'scrap_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $componentScraps = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (! empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        $workOrders = WorkOrder::with(['bom.finishedProduct'])
            ->orderBy('wo_number', 'asc')
            ->get();

        return Inertia::render('ComponentScraps/Index', [
            'componentScraps' => $componentScraps,
            'companies' => $companies,
            'branches' => $branches,
            'workOrders' => $workOrders,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function show(Request $request, ComponentScrap $componentScrap)
    {
        $filters = Session::get('component_scraps.index_filters', []);
        $componentScrap->load([
            'workOrder',
            'workOrder.bom.finishedProduct',
            'componentProduct',
            'componentProductVariant',
            'uom',
            'bomLine',
            'componentIssueLine',
            'finishedGoodsReceipt',
            'user',
        ]);

        return Inertia::render('ComponentScraps/Show', [
            'componentScrap' => $componentScrap,
            'filters' => $filters,
        ]);
    }

    private function getFilteredComponentScraps(Request $request)
    {
        $filters = $request->all() ?: Session::get('component_scraps.index_filters', []);

        $query = ComponentScrap::with([
            'workOrder',
            'componentProduct',
            'componentProductVariant',
            'uom',
        ]);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(scrap_reason)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhereHas('workOrder', function ($q) use ($filters) {
                        $q->where(DB::raw('lower(wo_number)'), 'like', '%'.strtolower($filters['search']).'%');
                    });
            });
        }

        if (! empty($filters['company_id'])) {
            $query->whereHas('workOrder', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            });
        }

        if (! empty($filters['branch_id'])) {
            $query->whereHas('workOrder', function ($query) use ($filters) {
                $query->whereIn('branch_id', $filters['branch_id']);
            });
        }

        if (! empty($filters['work_order_id'])) {
            $query->whereIn('work_order_id', $filters['work_order_id']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('scrap_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('scrap_date', '<=', $filters['to_date']);
        }

        $sortColumn = $filters['sort'] ?? 'scrap_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $componentScraps = $this->getFilteredComponentScraps($request);

        return Excel::download(new ComponentScrapsExport($componentScraps), 'component_scraps.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $componentScraps = $this->getFilteredComponentScraps($request);

        return Excel::download(new ComponentScrapsExport($componentScraps), 'component_scraps.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $componentScraps = $this->getFilteredComponentScraps($request);

        return Excel::download(new ComponentScrapsExport($componentScraps), 'component_scraps.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
