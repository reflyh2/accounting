<?php

namespace App\Http\Controllers;

use App\Exports\AssetPurchasesExport;
use App\Models\Asset;
use App\Models\AssetInvoice;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AssetPurchaseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_purchases.index_filters', []);
        Session::put('asset_purchases.index_filters', $filters);

        $query = AssetInvoice::with(['branch', 'partner', 'assetInvoiceDetails'])
            ->where('type', 'purchase');

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

        $assetPurchases = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        $partners = Partner::orderBy('name', 'asc')->get(); // Fetch all partners for filter dropdown

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->orderBy('name', 'asc')->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('AssetPurchases/Index', [
            'assetPurchases' => $assetPurchases,
            'companies' => $companies,
            'branches' => $branches,
            'partners' => $partners, // Pass partners for filter
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('asset_purchases.index_filters', []);
        return Inertia::render('AssetPurchases/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'partners' => Partner::whereHas('roles', function ($query) {
                $query->where('role', 'asset_supplier');
            })->orderBy('name', 'asc')->get(),
            'assets' => Asset::orderBy('name', 'asc')->get(), // Pass assets for selection
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'partner_id' => 'required|exists:partners,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string',
            'status' => 'required|string|in:open,paid,overdue,cancelled,voided,closed,partially_paid', // Ensure status is valid
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

        $assetInvoice = DB::transaction(function () use ($validated, $request, $totalAmount) {
            $invoice = AssetInvoice::create([
                'branch_id' => $validated['branch_id'],
                'partner_id' => $validated['partner_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'total_amount' => $totalAmount,
                'status' => $validated['status'],
                'notes' => $validated['notes'],
                'type' => 'purchase', // Explicitly set type
                // created_by is handled by model boot method
            ]);

            foreach ($validated['details'] as $detail) {
                $lineAmount = $detail['quantity'] * $detail['unit_price'];
                $invoice->assetInvoiceDetails()->create([
                    'asset_id' => $detail['asset_id'],
                    'description' => $detail['description'] ?? Asset::find($detail['asset_id'])->name, // Default description to asset name
                    'quantity' => $detail['quantity'],
                    'unit_price' => $detail['unit_price'],
                    'line_amount' => $lineAmount,
                ]);
            }

            // TODO: Create Journal Entry for Asset Purchase
            // Example:
            // Debit: Asset Account (from Asset or Asset Category)
            // Credit: Accounts Payable (from Partner or Default Accounts)
            // $this->createPurchaseJournal($invoice);

            return $invoice;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-purchases.create')
                ->with('success', 'Faktur Pembelian Aset berhasil dibuat. Silakan buat faktur lainnya.');
        }

        return redirect()->route('asset-purchases.show', $assetInvoice->id)
            ->with('success', 'Faktur Pembelian Aset berhasil dibuat.');
    }

    public function show(Request $request, AssetInvoice $assetPurchase)
    {
        $this->ensureIsPurchase($assetPurchase);
        $filters = Session::get('asset_purchases.index_filters', []);
        $assetPurchase->load(['branch.branchGroup.company', 'partner', 'assetInvoiceDetails.asset', 'creator', 'updater']);

        return Inertia::render('AssetPurchases/Show', [
            'assetPurchase' => $assetPurchase,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, AssetInvoice $assetPurchase)
    {
        $this->ensureIsPurchase($assetPurchase);
        $filters = Session::get('asset_purchases.index_filters', []);
        $assetPurchase->load(['branch.branchGroup', 'partner', 'assetInvoiceDetails.asset']);

        $companyId = $assetPurchase->branch->branchGroup->company_id;

        // This logic might be needed if changing company affects available branches/partners/assets on edit
        // if ($request->company_id) {
        //     $companyId = $request->company_id;
        // }

        return Inertia::render('AssetPurchases/Edit', [
            'assetPurchase' => $assetPurchase,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'partners' => Partner::whereHas('roles', function ($query) {
                $query->where('role', 'asset_supplier');
            })->orderBy('name', 'asc')->get(),
            'assets' => Asset::orderBy('name', 'asc')->get(),
        ]);
    }

    public function update(Request $request, AssetInvoice $assetPurchase)
    {
        $this->ensureIsPurchase($assetPurchase);
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'partner_id' => 'required|exists:partners,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string',
            'status' => 'required|string|in:open,paid,overdue,cancelled,voided,closed,partially_paid',
            'details' => 'required|array|min:1',
            'details.*.id' => 'nullable|exists:asset_invoice_details,id',
            'details.*.asset_id' => 'required|exists:assets,id',
            'details.*.description' => 'nullable|string',
            'details.*.quantity' => 'required|numeric|min:0.01',
            'details.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Basic checks: Prevent changing critical immutable fields like branch/company easily
        // More complex logic might be needed depending on accounting rules
        if ($assetPurchase->branch_id != $validated['branch_id']) {
             return redirect()->back()->with('error', 'Cabang Faktur tidak dapat diubah.');
        }

        $totalAmount = 0;
        foreach ($validated['details'] as $detail) {
            $totalAmount += $detail['quantity'] * $detail['unit_price'];
        }

        DB::transaction(function () use ($validated, $assetPurchase, $totalAmount) {
            $assetPurchase->update([
                'partner_id' => $validated['partner_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'total_amount' => $totalAmount,
                'status' => $validated['status'],
                'notes' => $validated['notes'],
                // updated_by is handled by model boot method
            ]);

            $existingDetailIds = $assetPurchase->assetInvoiceDetails->pluck('id')->toArray();
            $updatedDetailIds = [];

            foreach ($validated['details'] as $detail) {
                $lineAmount = $detail['quantity'] * $detail['unit_price'];
                $detailData = [
                    'asset_id' => $detail['asset_id'],
                    'description' => $detail['description'] ?? Asset::find($detail['asset_id'])->name,
                    'quantity' => $detail['quantity'],
                    'unit_price' => $detail['unit_price'],
                    'line_amount' => $lineAmount,
                ];

                if (isset($detail['id']) && in_array($detail['id'], $existingDetailIds)) {
                    // Update existing detail
                    $assetPurchase->assetInvoiceDetails()->find($detail['id'])->update($detailData);
                    $updatedDetailIds[] = $detail['id'];
                } else {
                    // Create new detail
                    $newDetail = $assetPurchase->assetInvoiceDetails()->create($detailData);
                    $updatedDetailIds[] = $newDetail->id;
                }
            }

            // Delete details that were removed
            $detailsToDelete = array_diff($existingDetailIds, $updatedDetailIds);
            if (!empty($detailsToDelete)) {
                $assetPurchase->assetInvoiceDetails()->whereIn('id', $detailsToDelete)->delete();
            }

            // TODO: Update or Reverse/Recreate Journal Entry for Asset Purchase
            // $this->updatePurchaseJournal($assetPurchase);

        });

        return redirect()->route('asset-purchases.edit', $assetPurchase->id)
            ->with('success', 'Faktur Pembelian Aset berhasil diubah.');
    }

    public function destroy(Request $request, AssetInvoice $assetPurchase)
    {
        $this->ensureIsPurchase($assetPurchase);
        DB::transaction(function () use ($assetPurchase) {
            // TODO: Reverse/Delete Journal Entry associated with this invoice
            // $this->deletePurchaseJournal($assetPurchase);

            $assetPurchase->assetInvoiceDetails()->delete(); // Delete details first
            $assetPurchase->delete(); // Soft delete the invoice
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-purchases.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Faktur Pembelian Aset berhasil dihapus.');
        } else {
            return Redirect::route('asset-purchases.index')
                ->with('success', 'Faktur Pembelian Aset berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_invoices,id',
        ]);

        DB::transaction(function () use ($validated) {
            $invoices = AssetInvoice::whereIn('id', $validated['ids'])->where('type', 'purchase')->get();
            foreach ($invoices as $invoice) {
                // TODO: Reverse/Delete Journal Entry
                // $this->deletePurchaseJournal($invoice);
                $invoice->assetInvoiceDetails()->delete();
                $invoice->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-purchases.index') . ($currentQuery ? '?' . $currentQuery : '');
            return Redirect::to($redirectUrl)
                ->with('success', 'Faktur Pembelian Aset berhasil dihapus.');
        }
        // If not preserving state, redirect normally or return success message
        return redirect()->route('asset-purchases.index')->with('success', 'Faktur Pembelian Aset terpilih berhasil dihapus.');
    }

    private function getFilteredAssetPurchases(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_purchases.index_filters', []);
        $query = AssetInvoice::with(['branch.branchGroup.company', 'partner', 'assetInvoiceDetails.asset'])
            ->where('type', 'purchase');

        // Apply filters similar to index method
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

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $assetPurchases = $this->getFilteredAssetPurchases($request);
        return Excel::download(new AssetPurchasesExport($assetPurchases), 'asset-purchases.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $assetPurchases = $this->getFilteredAssetPurchases($request);
        return Excel::download(new AssetPurchasesExport($assetPurchases), 'asset-purchases.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $assetPurchases = $this->getFilteredAssetPurchases($request);
        // Ensure you have a PDF renderer package installed (e.g., dompdf, mpdf)
        // return Excel::download(new AssetPurchasesExport($assetPurchases), 'asset-purchases.pdf', \Maatwebsite\Excel\Excel::MPDF);
        // Placeholder: PDF export might require a specific view or library setup
         return redirect()->back()->with('error', 'Ekspor PDF belum diimplementasikan.');
    }

    public function print(AssetInvoice $assetPurchase)
    {
        $this->ensureIsPurchase($assetPurchase);
        $assetPurchase->load(['branch.branchGroup.company', 'partner', 'assetInvoiceDetails.asset', 'creator', 'updater']);

        // Optional: Load primary currency if needed for display
        // $primaryCurrency = Currency::where('is_primary', true)->first();

        return Inertia::render('AssetPurchases/Print', [
            'assetPurchase' => $assetPurchase,
            // 'primaryCurrency' => $primaryCurrency,
        ]);
    }

    /**
     * Ensure the AssetInvoice is of type 'purchase'.
     */
    private function ensureIsPurchase(AssetInvoice $assetInvoice)
    {
        if ($assetInvoice->type !== 'purchase') {
            abort(404); // Or handle as appropriate, e.g., redirect with error
        }
    }

    // Placeholder for journaling logic
    // private function createPurchaseJournal(AssetInvoice $invoice) { /* ... */ }
    // private function updatePurchaseJournal(AssetInvoice $invoice) { /* ... */ }
    // private function deletePurchaseJournal(AssetInvoice $invoice) { /* ... */ }
} 