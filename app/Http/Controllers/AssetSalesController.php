<?php

namespace App\Http\Controllers;

use App\Exports\AssetSalesExport;
use App\Models\AssetInvoice;
use App\Models\AssetInvoiceDetail;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Partner;
use App\Models\Branch;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AssetSalesController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_sales.index_filters', []);
        Session::put('asset_sales.index_filters', $filters);

        $query = AssetInvoice::where('type', 'sales')
            ->with(['partner', 'branch.branchGroup.company', 'currency', 'creator']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('partner', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', $filters['partner_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'invoice_date';
        $sortOrder = $filters['order'] ?? 'desc';

        if ($sortColumn === 'partner.name') {
            $query->join('partners', 'asset_invoices.partner_id', '=', 'partners.id')
                  ->orderBy('partners.name', $sortOrder)
                  ->select('asset_invoices.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $sales = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $partners = Partner::with(['roles'])
            ->whereHas('roles', function ($query) {
                $query->where('role', 'asset_customer');
            })
            ->orderBy('name', 'asc')
            ->get();

        $statuses = [
            'open' => 'Belum Dibayar',
            'partially_paid' => 'Dibayar Sebagian',
            'paid' => 'Lunas',
        ];

        return Inertia::render('AssetSales/Index', [
            'sales' => $sales,
            'partners' => $partners,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'statuses' => $statuses,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('asset_sales.index_filters', []);
        
        // Get companies
        $companies = Company::orderBy('name', 'asc')->get();
        
        // Get primary currency
        $primaryCurrency = Currency::where('is_primary', true)->first();

        // Get branches based on company selection (if any)
        $branches = collect();
        $partners = collect();
        $currencies = collect();
        $assets = collect();
        
        if ($request->company_id) {
            $companyId = $request->company_id;
            
            // Get branches for selected company
            $branches = Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get();
            
            // Get partners with asset_customer role
            $partners = Partner::whereHas('roles', function ($query) {
                $query->where('role', 'asset_customer');
            })->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get();
            
            // Get currencies available for the company
            $currencies = Currency::whereHas('companyRates', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->with(['companyRates' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            }])->orderBy('code', 'asc')->get();

            if ($request->branch_id) {
                $assets = $this->getAvailableAssets(null, $companyId, $request->branch_id);
            }
        }

        return Inertia::render('AssetSales/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'branches' => fn() => $branches,
            'partners' => fn() => $partners,
            'currencies' => fn() => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'assets' => fn() => $assets,
            'assetCategories' => \App\Models\AssetCategory::orderBy('name', 'asc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'partner_id' => 'required|exists:partners,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.asset_id' => 'required|exists:assets,id',
            'details.*.description' => 'nullable|string',
            'details.*.quantity' => 'required|numeric|min:0.01',
            'details.*.unit_price' => 'required|numeric|min:0',
        ]);

        $totalAmount = 0;
        foreach ($validated['details'] as $detail) {
            $totalAmount += $detail['quantity'] * $detail['unit_price'];
        }

        $sale = DB::transaction(function () use ($validated, $totalAmount) {
            $sale = AssetInvoice::create([
                'type' => 'sales',
                'branch_id' => $validated['branch_id'],
                'partner_id' => $validated['partner_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'],
                'status' => 'open',
            ]);

            foreach ($validated['details'] as $detail) {
                $sale->assetInvoiceDetails()->create([
                    'asset_id' => $detail['asset_id'],
                    'description' => $detail['description'],
                    'quantity' => $detail['quantity'],
                    'unit_price' => $detail['unit_price'],
                    'line_amount' => $detail['quantity'] * $detail['unit_price'],
                ]);
            }

            // TODO: Create sale journal entry
            // Example:
            // Debit: Accounts Receivable
            // Credit: Asset Sale Revenue

            return $sale;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-sales.create')
                ->with('success', 'Faktur penjualan aset berhasil dibuat. Silakan buat faktur lainnya.');
        }

        return redirect()->route('asset-sales.show', $sale->id)
            ->with('success', 'Faktur penjualan aset berhasil dibuat.');
    }

    public function show(Request $request, AssetInvoice $assetSale)
    {
        $this->ensureIsSales($assetSale);
        $filters = Session::get('asset_sales.index_filters', []);
        $assetSale->load(['branch.branchGroup.company', 'partner', 'assetInvoiceDetails.asset', 'currency', 'creator', 'updater']);

        return Inertia::render('AssetSales/Show', [
            'assetSale' => $assetSale,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, AssetInvoice $assetSale)
    {
        $this->ensureIsSales($assetSale);
        $filters = Session::get('asset_sales.index_filters', []);
        $assetSale->load(['branch.branchGroup', 'partner', 'assetInvoiceDetails.asset', 'currency']);

        $companyId = $assetSale->branch->branchGroup->company_id;
        $branchId = $assetSale->branch_id;
        if ($request->company_id) {
            $companyId = $request->company_id;
        }
        if ($request->branch_id) {
            $branchId = $request->branch_id;
        }
        
        // Get primary currency
        $primaryCurrency = Currency::where('is_primary', true)->first();
        
        // Get currencies available for the company
        $currencies = Currency::whereHas('companyRates', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->with(['companyRates' => function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        }])->orderBy('code', 'asc')->get();

        return Inertia::render('AssetSales/Edit', [
            'assetSale' => $assetSale,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'partners' => Partner::whereHas('roles', function ($query) {
                $query->where('role', 'asset_customer');
            })->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'currencies' => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'assets' => $this->getAvailableAssets($assetSale->id, $companyId, $branchId), // Allow current invoice assets
            'assetCategories' => \App\Models\AssetCategory::orderBy('name', 'asc')->get(),
        ]);
    }

    public function update(Request $request, AssetInvoice $assetSale)
    {
        $this->ensureIsSales($assetSale);

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'partner_id' => 'required|exists:partners,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.id' => 'nullable|exists:asset_invoice_details,id',
            'details.*.asset_id' => 'required|exists:assets,id',
            'details.*.description' => 'nullable|string',
            'details.*.quantity' => 'required|numeric|min:0.01',
            'details.*.unit_price' => 'required|numeric|min:0',
        ]);

        $totalAmount = 0;
        foreach ($validated['details'] as $detail) {
            $totalAmount += $detail['quantity'] * $detail['unit_price'];
        }

        DB::transaction(function () use ($validated, $assetSale, $totalAmount) {
            // Update the main invoice
            $assetSale->update([
                'branch_id' => $validated['branch_id'],
                'partner_id' => $validated['partner_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'],
            ]);

            // Delete existing details
            $assetSale->assetInvoiceDetails()->delete();

            // Create new details
            foreach ($validated['details'] as $detail) {
                $assetSale->assetInvoiceDetails()->create([
                    'asset_id' => $detail['asset_id'],
                    'description' => $detail['description'],
                    'quantity' => $detail['quantity'],
                    'unit_price' => $detail['unit_price'],
                    'line_amount' => $detail['quantity'] * $detail['unit_price'],
                ]);
            }

            // TODO: Update sale journal entry
        });

        return redirect()->route('asset-sales.show', $assetSale->id)
            ->with('success', 'Faktur penjualan aset berhasil diubah.');
    }

    public function destroy(Request $request, AssetInvoice $assetSale)
    {
        $this->ensureIsSales($assetSale);

        DB::transaction(function () use ($assetSale) {
            // Delete all details first
            $assetSale->assetInvoiceDetails()->delete();
            
            // Delete the main invoice
            $assetSale->delete();

            // TODO: Delete/reverse sale journal entry
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-sales.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Faktur penjualan aset berhasil dihapus.');
        }

        return redirect()->route('asset-sales.index')
            ->with('success', 'Faktur penjualan aset berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $sale = AssetInvoice::where('type', 'sales')->find($id);
                if ($sale) {
                    $sale->assetInvoiceDetails()->delete();
                    $sale->delete();
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-sales.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Faktur penjualan aset berhasil dihapus.');
        }
    }

    private function getFilteredSales(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_sales.index_filters', []);

        $query = AssetInvoice::where('type', 'sales')
            ->with(['partner', 'branch.branchGroup.company', 'currency', 'assetInvoiceDetails.asset']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('partner', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', $filters['partner_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }

        $sortColumn = $filters['sort'] ?? 'invoice_date';
        $sortOrder = $filters['order'] ?? 'desc';

        if ($sortColumn === 'partner.name') {
            $query->join('partners', 'asset_invoices.partner_id', '=', 'partners.id')
                  ->orderBy('partners.name', $sortOrder)
                  ->select('asset_invoices.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $sales = $this->getFilteredSales($request);
        return Excel::download(new AssetSalesExport($sales), 'asset_sales.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $sales = $this->getFilteredSales($request);
        return Excel::download(new AssetSalesExport($sales), 'asset_sales.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $sales = $this->getFilteredSales($request);
        return Excel::download(new AssetSalesExport($sales), 'asset_sales.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
    }

    public function print(AssetInvoice $assetSale)
    {
        $this->ensureIsSales($assetSale);
        $assetSale->load(['branch.branchGroup.company', 'partner', 'assetInvoiceDetails.asset', 'currency', 'creator', 'updater']);

        return Inertia::render('AssetSales/Print', [
            'assetSale' => $assetSale,
        ]);
    }

    /**
     * Ensure the AssetInvoice is of type 'sales'.
     */
    private function ensureIsSales(AssetInvoice $assetInvoice)
    {
        if ($assetInvoice->type !== 'sales') {
            abort(404);
        }
    }

    /**
     * Get assets that are available for sale.
     * For edit mode, exclude the current invoice from the check.
     */
    private function getAvailableAssets($excludeInvoiceId = null)
    {
        return Asset::orderBy('name', 'asc')->get();
    }
} 