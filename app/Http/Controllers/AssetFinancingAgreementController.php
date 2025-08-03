<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Partner;
use App\Models\AssetInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Models\AssetFinancingAgreement;
use App\Exports\AssetFinancingAgreementsExport;
use App\Models\Currency;

class AssetFinancingAgreementController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_financing_agreements.index_filters', []);
        Session::put('asset_financing_agreements.index_filters', $filters);

        $query = AssetFinancingAgreement::with(['creditor', 'currency', 'assetInvoice', 'branch', 'createdBy']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('creditor', function ($q) use ($filters) {
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

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['payment_frequency'])) {
            $query->whereIn('payment_frequency', $filters['payment_frequency']);
        }

        if (!empty($filters['creditor_id'])) {
            $query->whereIn('creditor_id', $filters['creditor_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('agreement_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('agreement_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'agreement_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $agreements = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = \App\Models\Company::orderBy('name', 'asc')->get();
        $partners = Partner::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = \App\Models\Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->orderBy('name', 'asc')->get();
        } else {
            $branches = \App\Models\Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('AssetFinancingAgreements/Index', [
            'agreements' => $agreements,
            'companies' => $companies,
            'branches' => $branches,
            'partners' => $partners,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'statusOptions' => AssetFinancingAgreement::statusOptions(),
            'paymentFrequencyOptions' => AssetFinancingAgreement::paymentFrequencyOptions(),
            'interestCalculationMethodOptions' => AssetFinancingAgreement::interestCalculationMethodOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('asset_financing_agreements.index_filters', []);
        
        // Get companies
        $companies = \App\Models\Company::orderBy('name', 'asc')->get();
        
        // Get branches, partners, and invoices based on company selection
        $branches = collect();
        $partners = collect();
        $assetInvoices = collect();
        $currencies = collect();
        
        if ($request->input('company_id')) {
            $companyId = $request->input('company_id');
            
            // Get branches for selected company
            $branches = \App\Models\Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get();
            
            // Get partners with creditor role for the selected company
            $partners = Partner::whereHas('roles', function ($query) {
                $query->where('role', 'creditor');
            })->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get();

            // Get currencies available for the company
            $currencies = Currency::whereHas('companyRates', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->with(['companyRates' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            }])->orderBy('code', 'asc')->get();
            
            // Get asset invoices with type purchase for the selected company (if branch is also selected)
            if ($request->input('branch_id')) {
                $branchId = $request->input('branch_id');
                $assetInvoices = AssetInvoice::where('type', 'purchase')
                    ->where('branch_id', $branchId)
                    ->whereNotIn('id', function($query) {
                        $query->select('asset_invoice_id')
                              ->from('asset_financing_agreements')
                              ->whereNotNull('asset_invoice_id')
                              ->whereNull('deleted_at');
                    })
                    ->where('currency_id', $request->input('currency_id'))
                    ->whereNotIn('status', ['financed', 'paid'])
                    ->with(['assetInvoiceDetails.asset', 'partner'])
                    ->orderBy('invoice_date', 'desc')
                    ->get();

                // Calculate outstanding amount for each invoice
                $assetInvoices->each(function ($invoice) {
                    $totalPaid = \App\Models\AssetInvoicePaymentAllocation::where('asset_invoice_id', $invoice->id)
                        ->sum('allocated_amount');
                    $invoice->outstanding_amount = $invoice->total_amount - $totalPaid;
                    $invoice->total_paid = $totalPaid;
                });
            }
        }
        
        return Inertia::render('AssetFinancingAgreements/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'branches' => fn() => $branches,
            'partners' => fn() => $partners,
            'currencies' => fn() => $currencies,
            'assetInvoices' => fn() => $assetInvoices,
            'statusOptions' => AssetFinancingAgreement::statusOptions(),
            'paymentFrequencyOptions' => AssetFinancingAgreement::paymentFrequencyOptions(),
            'interestCalculationMethodOptions' => AssetFinancingAgreement::interestCalculationMethodOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'agreement_date' => 'required|date',
            'creditor_id' => 'required|exists:partners,id',
            'asset_invoice_id' => 'required|exists:asset_invoices,id',
            'total_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_calculation_method' => 'required|in:' . implode(',', array_keys(AssetFinancingAgreement::interestCalculationMethodOptions())),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'payment_frequency' => 'required|in:monthly,quarterly,annually',
            'status' => 'required|in:pending,active,closed,defaulted,cancelled',
            'notes' => 'nullable|string',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0',
        ]);

        // Check if asset invoice is already financed
        $existingAgreement = AssetFinancingAgreement::where('asset_invoice_id', $validated['asset_invoice_id'])->first();
        if ($existingAgreement) {
            return redirect()->back()->with('error', 'Invoice aset sudah memiliki perjanjian pembiayaan.');
        }

        // Validate that the asset invoice belongs to the selected branch
        $assetInvoice = AssetInvoice::find($validated['asset_invoice_id']);
        if ($assetInvoice->branch_id !== $validated['branch_id']) {
            return redirect()->back()->with('error', 'Invoice aset tidak sesuai dengan cabang yang dipilih.');
        }

        $agreement = AssetFinancingAgreement::create($validated);

        return redirect()->route('asset-financing-agreements.show', $agreement->id)
            ->with('success', 'Perjanjian pembiayaan aset berhasil dibuat.');
    }

    public function show(Request $request, AssetFinancingAgreement $assetFinancingAgreement)
    {
        $filters = Session::get('asset_financing_agreements.index_filters', []);
        $assetFinancingAgreement->load(['creditor', 'assetInvoice.assetInvoiceDetails.asset', 'assetInvoice.partner', 'branch.branchGroup.company', 'createdBy', 'updatedBy']);

        $schedules = $assetFinancingAgreement->schedules()
            ->select('asset_financing_schedules.*', DB::raw('asset_financing_schedules.paid_principal_amount + asset_financing_schedules.paid_interest_amount as total_paid_amount'))
            ->orderBy($request->input('sort', 'payment_date'), $request->input('order', 'asc'))
            ->paginate($request->input('per_page', 10))->withQueryString();
        
        return Inertia::render('AssetFinancingAgreements/Show', [
            'agreement' => $assetFinancingAgreement,
            'schedules' => $schedules,
            'filters' => $filters,
            'sort' => $request->input('sort', 'payment_date'),
            'order' => $request->input('order', 'asc'),
            'statusOptions' => AssetFinancingAgreement::statusOptions(),
            'paymentFrequencyOptions' => AssetFinancingAgreement::paymentFrequencyOptions(),
            'interestCalculationMethodOptions' => AssetFinancingAgreement::interestCalculationMethodOptions(),
        ]);
    }

    public function edit(Request $request, AssetFinancingAgreement $assetFinancingAgreement)
    {
        $filters = Session::get('asset_financing_agreements.index_filters', []);
        $assetFinancingAgreement->load(['creditor', 'assetInvoice', 'branch.branchGroup']);

        $companyId = $assetFinancingAgreement->branch->branchGroup->company_id;
        
        // Override company ID if provided in request (for dynamic loading)
        if ($request->input('company_id')) {
            $companyId = $request->input('company_id');
        }

        // Get currencies available for the company
        $currencies = Currency::whereHas('companyRates', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->with(['companyRates' => function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        }])->orderBy('code', 'asc')->get();

        return Inertia::render('AssetFinancingAgreements/Edit', [
            'agreement' => $assetFinancingAgreement,
            'filters' => $filters,
            'companies' => \App\Models\Company::orderBy('name', 'asc')->get(),
            'branches' => \App\Models\Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'partners' => Partner::whereHas('roles', function ($query) {
                $query->where('role', 'creditor');
            })->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'currencies' => $currencies,
            'assetInvoices' => function() use ($assetFinancingAgreement) {
                $invoices = AssetInvoice::where('type', 'purchase')
                    ->where('branch_id', $assetFinancingAgreement->branch_id)
                    ->where(function($q) use ($assetFinancingAgreement) {
                        $q->whereNotIn('id', function($subQuery) use ($assetFinancingAgreement) {
                            $subQuery->select('asset_invoice_id')
                                     ->from('asset_financing_agreements')
                                     ->where('id', '!=', $assetFinancingAgreement->id)
                                     ->whereNotNull('asset_invoice_id')
                                     ->whereNull('deleted_at');
                        })
                        ->orWhere('id', $assetFinancingAgreement->asset_invoice_id);
                    })
                    ->where('currency_id', $assetFinancingAgreement->currency_id)
                    ->where(function($q) use ($assetFinancingAgreement) {
                        $q->whereNotIn('status', ['financed', 'paid'])
                          ->orWhere('id', $assetFinancingAgreement->asset_invoice_id);
                    })
                    ->with(['assetInvoiceDetails.asset', 'partner'])->orderBy('invoice_date', 'desc')->get();

                // Calculate outstanding amount for each invoice
                $invoices->each(function ($invoice) {
                    $totalPaid = \App\Models\AssetInvoicePaymentAllocation::where('asset_invoice_id', $invoice->id)
                        ->sum('allocated_amount');
                    $invoice->outstanding_amount = $invoice->total_amount - $totalPaid;
                    $invoice->total_paid = $totalPaid;
                });

                return $invoices;
            },
            'statusOptions' => AssetFinancingAgreement::statusOptions(),
            'paymentFrequencyOptions' => AssetFinancingAgreement::paymentFrequencyOptions(),
            'interestCalculationMethodOptions' => AssetFinancingAgreement::interestCalculationMethodOptions(),
        ]);
    }

    public function update(Request $request, AssetFinancingAgreement $assetFinancingAgreement)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'agreement_date' => 'required|date',
            'creditor_id' => 'required|exists:partners,id',
            'asset_invoice_id' => 'required|exists:asset_invoices,id',
            'total_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_calculation_method' => 'required|in:' . implode(',', array_keys(AssetFinancingAgreement::interestCalculationMethodOptions())),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'payment_frequency' => 'required|in:monthly,quarterly,annually',
            'status' => 'required|in:pending,active,closed,defaulted,cancelled',
            'notes' => 'nullable|string',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0',
        ]);

        // Prevent changing branch once agreement is created
        if ($assetFinancingAgreement->branch_id != $validated['branch_id']) {
            return redirect()->back()->with('error', 'Cabang perjanjian tidak dapat diubah.');
        }

        // Check if asset invoice is already financed by another agreement
        if ($validated['asset_invoice_id'] !== $assetFinancingAgreement->asset_invoice_id) {
            $existingAgreement = AssetFinancingAgreement::where('asset_invoice_id', $validated['asset_invoice_id'])
                ->where('id', '!=', $assetFinancingAgreement->id)
                ->first();
            if ($existingAgreement) {
                return redirect()->back()->with('error', 'Invoice aset sudah memiliki perjanjian pembiayaan.');
            }
            
            // Validate that the new asset invoice belongs to the same branch
            $assetInvoice = AssetInvoice::find($validated['asset_invoice_id']);
            if ($assetInvoice->branch_id !== $assetFinancingAgreement->branch_id) {
                return redirect()->back()->with('error', 'Invoice aset tidak sesuai dengan cabang perjanjian.');
            }

        }

        $assetFinancingAgreement->update($validated);

        return redirect()->route('asset-financing-agreements.edit', $assetFinancingAgreement->id)
            ->with('success', 'Perjanjian pembiayaan aset berhasil diubah.');
    }

    public function destroy(Request $request, AssetFinancingAgreement $assetFinancingAgreement)
    {
        $assetFinancingAgreement->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-financing-agreements.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Perjanjian pembiayaan aset berhasil dihapus.');
        } else {
            return Redirect::route('asset-financing-agreements.index')
                ->with('success', 'Perjanjian pembiayaan aset berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        AssetFinancingAgreement::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-financing-agreements.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Perjanjian pembiayaan aset berhasil dihapus.');
        }
    }



    private function getFilteredAgreements(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_financing_agreements.index_filters', []);

        $query = AssetFinancingAgreement::with(['creditor', 'assetInvoice', 'createdBy']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('creditor', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['payment_frequency'])) {
            $query->whereIn('payment_frequency', $filters['payment_frequency']);
        }

        if (!empty($filters['creditor_id'])) {
            $query->whereIn('creditor_id', $filters['creditor_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('agreement_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('agreement_date', '<=', $filters['to_date']);
        }

        $sortColumn = $filters['sort'] ?? 'agreement_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $agreements = $this->getFilteredAgreements($request);
        return Excel::download(new AssetFinancingAgreementsExport($agreements), 'asset-financing-agreements.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $agreements = $this->getFilteredAgreements($request);
        return Excel::download(new AssetFinancingAgreementsExport($agreements), 'asset-financing-agreements.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $agreements = $this->getFilteredAgreements($request);
        return Excel::download(new AssetFinancingAgreementsExport($agreements), 'asset-financing-agreements.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public function print(AssetFinancingAgreement $assetFinancingAgreement)
    {
        $assetFinancingAgreement->load(['creditor', 'assetInvoice.assetInvoiceDetails.asset', 'assetInvoice.partner', 'branch.branchGroup.company', 'createdBy', 'updatedBy']);
        
        return Inertia::render('AssetFinancingAgreements/Print', [
            'agreement' => $assetFinancingAgreement,
        ]);
    }
} 