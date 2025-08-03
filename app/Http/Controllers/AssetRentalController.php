<?php

namespace App\Http\Controllers;

use App\Exports\AssetRentalsExport;
use App\Models\Asset;
use App\Models\AssetInvoice;
use App\Models\AssetInvoiceDetail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Partner;
use App\Models\Currency;
use App\Events\Asset\AssetRentalCreated;
use App\Events\Asset\AssetRentalUpdated;
use App\Events\Asset\AssetRentalDeleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AssetRentalController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_rentals.index_filters', []);
        Session::put('asset_rentals.index_filters', $filters);

        $query = AssetInvoice::with(['branch', 'partner', 'assetInvoiceDetails', 'currency'])
            ->where('type', 'rental');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
                  ->orWhereHas('partner', function ($q) use ($filters) {
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

        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', $filters['partner_id']);
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

        // Adjust sort column if it involves relationships
        if ($sortColumn === 'partner.name') {
            $query->join('partners', 'asset_invoices.partner_id', '=', 'partners.id')
                  ->orderBy('partners.name', $sortOrder)
                  ->select('asset_invoices.*'); // Avoid column ambiguity
        } elseif ($sortColumn === 'branch.name') {
            $query->join('branches', 'asset_invoices.branch_id', '=', 'branches.id')
                  ->orderBy('branches.name', $sortOrder)
                  ->select('asset_invoices.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $assetRentals = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        $partners = Partner::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->orderBy('name', 'asc')->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('AssetRentals/Index', [
            'assetRentals' => $assetRentals,
            'companies' => $companies,
            'branches' => $branches,
            'partners' => $partners,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('asset_rentals.index_filters', []);
        
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
            
            // Get partners with asset supplier role
            $partners = Partner::whereHas('roles', function ($query) {
                $query->where('role', 'asset_supplier');
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

        return Inertia::render('AssetRentals/Create', [
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
            'details.*.rental_start_date' => 'required|date',
            'details.*.rental_end_date' => 'required|date|after_or_equal:details.*.rental_start_date',
        ]);

        $totalAmount = 0;
        foreach ($validated['details'] as $detail) {
            $totalAmount += $detail['quantity'] * $detail['unit_price'];
        }

        $assetInvoice = DB::transaction(function () use ($validated, $request, $totalAmount) {
            $invoice = AssetInvoice::create([
                'branch_id' => $validated['branch_id'],
                'partner_id' => $validated['partner_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'total_amount' => $totalAmount,
                'status' => 'open',
                'notes' => $validated['notes'],
                'type' => 'rental',
            ]);

            foreach ($validated['details'] as $detail) {
                $lineAmount = $detail['quantity'] * $detail['unit_price'];
                $invoice->assetInvoiceDetails()->create([
                    'asset_id' => $detail['asset_id'],
                    'description' => $detail['description'] ?? Asset::find($detail['asset_id'])->name,
                    'quantity' => $detail['quantity'],
                    'unit_price' => $detail['unit_price'],
                    'line_amount' => $lineAmount,
                    'rental_start_date' => $detail['rental_start_date'],
                    'rental_end_date' => $detail['rental_end_date'],
                ]);
            }

            AssetRentalCreated::dispatch($invoice);

            // TODO: Create Journal Entry for Asset Rental
            // Example:
            // Debit: Rental Expense Account
            // Credit: Accounts Payable (from Partner or Default Accounts)

            return $invoice;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-rentals.create')
                ->with('success', 'Faktur Sewa Aset berhasil dibuat. Silakan buat faktur lainnya.');
        }

        return redirect()->route('asset-rentals.show', $assetInvoice->id)
            ->with('success', 'Faktur Sewa Aset berhasil dibuat.');
    }

    public function show(Request $request, AssetInvoice $assetRental)
    {
        $this->ensureIsRental($assetRental);
        $filters = Session::get('asset_rentals.index_filters', []);
        $assetRental->load(['branch.branchGroup.company', 'partner', 'assetInvoiceDetails.asset', 'currency', 'creator', 'updater']);

        return Inertia::render('AssetRentals/Show', [
            'assetRental' => $assetRental,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, AssetInvoice $assetRental)
    {
        $this->ensureIsRental($assetRental);
        $filters = Session::get('asset_rentals.index_filters', []);
        $assetRental->load(['branch.branchGroup', 'partner', 'assetInvoiceDetails.asset', 'currency']);

        $companyId = $assetRental->branch->branchGroup->company_id;
        $branchId = $assetRental->branch_id;
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

        return Inertia::render('AssetRentals/Edit', [
            'assetRental' => $assetRental,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'partners' => Partner::whereHas('roles', function ($query) {
                $query->where('role', 'asset_supplier');
            })->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'currencies' => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'assets' => $this->getAvailableAssets($assetRental->id, $companyId, $branchId),
            'assetCategories' => \App\Models\AssetCategory::orderBy('name', 'asc')->get(),
        ]);
    }

    public function update(Request $request, AssetInvoice $assetRental)
    {
        $this->ensureIsRental($assetRental);
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
            'details.*.rental_start_date' => 'required|date',
            'details.*.rental_end_date' => 'required|date|after_or_equal:details.*.rental_start_date',
        ]);

        if ($assetRental->branch_id != $validated['branch_id']) {
             return redirect()->back()->with('error', 'Cabang Faktur tidak dapat diubah.');
        }

        $totalAmount = 0;
        foreach ($validated['details'] as $detail) {
            $totalAmount += $detail['quantity'] * $detail['unit_price'];
        }

        DB::transaction(function () use ($validated, $assetRental, $totalAmount) {
            $assetRental->update([
                'partner_id' => $validated['partner_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'],
            ]);

            $existingDetailIds = $assetRental->assetInvoiceDetails->pluck('id')->toArray();
            $updatedDetailIds = [];

            foreach ($validated['details'] as $detail) {
                $lineAmount = $detail['quantity'] * $detail['unit_price'];
                $detailData = [
                    'asset_id' => $detail['asset_id'],
                    'description' => $detail['description'] ?? Asset::find($detail['asset_id'])->name,
                    'quantity' => $detail['quantity'],
                    'unit_price' => $detail['unit_price'],
                    'line_amount' => $lineAmount,
                    'rental_start_date' => $detail['rental_start_date'],
                    'rental_end_date' => $detail['rental_end_date'],
                ];

                if (isset($detail['id']) && in_array($detail['id'], $existingDetailIds)) {
                    // Update existing detail
                    $assetRental->assetInvoiceDetails()->find($detail['id'])->update($detailData);
                    $updatedDetailIds[] = $detail['id'];
                } else {
                    // Create new detail
                    $newDetail = $assetRental->assetInvoiceDetails()->create($detailData);
                    $updatedDetailIds[] = $newDetail->id;
                }
            }

            // Delete details that were removed
            $detailsToDelete = array_diff($existingDetailIds, $updatedDetailIds);
            if (!empty($detailsToDelete)) {
                $assetRental->assetInvoiceDetails()->whereIn('id', $detailsToDelete)->delete();
            }

            AssetRentalUpdated::dispatch($assetRental);

            // TODO: Update or Reverse/Recreate Journal Entry for Asset Rental
        });

        return redirect()->route('asset-rentals.show', $assetRental->id)
            ->with('success', 'Faktur Sewa Aset berhasil diubah.');
    }

    public function destroy(Request $request, AssetInvoice $assetRental)
    {
        $this->ensureIsRental($assetRental);
        DB::transaction(function () use ($assetRental) {
            AssetRentalDeleted::dispatch($assetRental);
            $assetRental->assetInvoiceDetails()->delete();
            $assetRental->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-rentals.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Faktur Sewa Aset berhasil dihapus.');
        } else {
            return Redirect::route('asset-rentals.index')
                ->with('success', 'Faktur Sewa Aset berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_invoices,id',
        ]);

        DB::transaction(function () use ($validated) {
            $invoices = AssetInvoice::whereIn('id', $validated['ids'])->where('type', 'rental')->get();
            foreach ($invoices as $invoice) {
                AssetRentalDeleted::dispatch($invoice);
                $invoice->assetInvoiceDetails()->delete();
                $invoice->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-rentals.index') . ($currentQuery ? '?' . $currentQuery : '');
            return Redirect::to($redirectUrl)
                ->with('success', 'Faktur Sewa Aset berhasil dihapus.');
        }
        
        return redirect()->route('asset-rentals.index')->with('success', 'Faktur Sewa Aset terpilih berhasil dihapus.');
    }

    private function getFilteredAssetRentals(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_rentals.index_filters', []);
        $query = AssetInvoice::with(['branch.branchGroup.company', 'partner', 'assetInvoiceDetails.asset', 'currency'])
            ->where('type', 'rental');

        if (!empty($filters['search'])) {
             $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
                  ->orWhereHas('partner', function ($q) use ($filters) {
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
        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', $filters['partner_id']);
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
        } elseif ($sortColumn === 'branch.name') {
            $query->join('branches', 'asset_invoices.branch_id', '=', 'branches.id')
                  ->orderBy('branches.name', $sortOrder)
                  ->select('asset_invoices.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $assetRentals = $this->getFilteredAssetRentals($request);
        return Excel::download(new AssetRentalsExport($assetRentals), 'asset-rentals.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $assetRentals = $this->getFilteredAssetRentals($request);
        return Excel::download(new AssetRentalsExport($assetRentals), 'asset-rentals.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $assetRentals = $this->getFilteredAssetRentals($request);
        return Excel::download(new AssetRentalsExport($assetRentals), 'asset-rentals.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public function print(AssetInvoice $assetRental)
    {
        $this->ensureIsRental($assetRental);
        $assetRental->load(['branch.branchGroup.company', 'partner', 'assetInvoiceDetails.asset', 'currency', 'creator', 'updater']);

        return Inertia::render('AssetRentals/Print', [
            'assetRental' => $assetRental,
        ]);
    }

    /**
     * Ensure the AssetInvoice is of type 'rental'.
     */
    private function ensureIsRental(AssetInvoice $assetInvoice)
    {
        if ($assetInvoice->type !== 'rental') {
            abort(404);
        }
    }

    /**
     * Get assets that are not yet used in any purchase or rental invoice.
     * For edit mode, exclude the current invoice from the check.
     */
    private function getAvailableAssets($excludeInvoiceId = null)
    {
        $usedAssetIds = AssetInvoiceDetail::query()
            ->when($excludeInvoiceId, function ($query, $excludeInvoiceId) {
                $query->whereHas('assetInvoice', function ($subQuery) use ($excludeInvoiceId) {
                    $subQuery->where('id', '!=', $excludeInvoiceId);
                });
            })
            ->pluck('asset_id')
            ->unique()
            ->toArray();

        return Asset::whereNotIn('id', $usedAssetIds)
            ->orderBy('name', 'asc')
            ->get();
    }
} 