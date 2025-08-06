<?php

namespace App\Http\Controllers;

use App\Exports\AssetInvoicePaymentsExport;
use App\Models\AssetInvoice;
use App\Models\AssetInvoicePayment;
use App\Models\AssetInvoicePaymentAllocation;
use App\Models\Company;
use App\Models\Partner;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Events\Asset\AssetInvoicePaymentCreated;
use App\Events\Asset\AssetInvoicePaymentUpdated;
use App\Events\Asset\AssetInvoicePaymentDeleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AssetInvoicePaymentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_invoice_payments.index_filters', []);
        Session::put('asset_invoice_payments.index_filters', $filters);

        $query = AssetInvoicePayment::with(['partner', 'currency', 'allocations.assetInvoice', 'creator', 'branch.branchGroup.company']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(reference)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('partner', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['type'])) {
            $query->whereIn('type', $filters['type']);
        }

        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', $filters['partner_id']);
        }

        if (!empty($filters['payment_method'])) {
            $query->whereIn('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('payment_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('payment_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'payment_date';
        $sortOrder = $filters['order'] ?? 'desc';

        if ($sortColumn === 'partner.name') {
            $query->join('partners', 'asset_invoice_payments.partner_id', '=', 'partners.id')
                  ->orderBy('partners.name', $sortOrder)
                  ->select('asset_invoice_payments.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $payments = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $partners = Partner::with(['roles'])
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('AssetInvoicePayments/Index', [
            'payments' => $payments,
            'partners' => $partners,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'paymentMethods' => AssetInvoicePayment::getPaymentMethods(),
            'paymentTypes' => AssetInvoicePayment::getTypes(),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('asset_invoice_payments.index_filters', []);
        
        // Get companies
        $companies = Company::orderBy('name', 'asc')->get();
        
        // Get primary currency
        $primaryCurrency = Currency::where('is_primary', true)->first();

        // Get branches based on company selection (if any)
        $branches = collect();
        $partners = collect();
        $sourceAccounts = collect();
        $currencies = collect();
        
        if ($request->input('company_id')) {
            $companyId = $request->input('company_id');
            
            // Get branches for selected company
            $branches = Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get();
            
            // Get partners associated with selected company
            $partners = Partner::with(['roles', 'activeBankAccounts'])
                ->whereHas('roles', function ($query) {
                    $query->whereIn('role', ['asset_supplier', 'asset_customer']);
                })
                ->whereHas('companies', function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                })
                ->orderBy('name', 'asc')
                ->get();
            
            // Get source accounts associated with selected company
            $sourceAccounts = Account::whereIn('type', ['kas_bank'])
                ->where('is_parent', false)
                ->whereHas('companies', function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                })
                ->orderBy('name', 'asc')
                ->get();
                
            // Get currencies available for the company
            $currencies = Currency::whereHas('companyRates', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->with(['companyRates' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            }])->orderBy('code', 'asc')->get();
        }

        return Inertia::render('AssetInvoicePayments/Create', [
            'filters' => $filters,
            'partners' => fn() => $partners,
            'companies' => $companies,
            'branches' => fn() => $branches,
            'sourceAccounts' => fn() => $sourceAccounts,
            'currencies' => fn() => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'paymentMethods' => AssetInvoicePayment::getPaymentMethods(),
            'paymentTypes' => AssetInvoicePayment::getTypes(),
            'assetInvoices' => fn() => $this->getOutstandingInvoices($request->input('partner_id'), null, $request->input('company_id'), $request->input('currency_id'), $request->input('type')),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'type' => 'required|in:purchase,rental,sales',
            'partner_id' => 'required|exists:partners,id',
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'source_account_id' => 'required|exists:accounts,id',
            'destination_bank_account_id' => 'nullable|exists:partner_bank_accounts,id',
            'reference' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,check,credit_card,bank_transfer,other',
            'notes' => 'nullable|string',
            'allocations' => 'required|array|min:1',
            'allocations.*.asset_invoice_id' => 'required|exists:asset_invoices,id',
            'allocations.*.allocated_amount' => 'required|numeric|min:0.01',
        ]);

        // Validate bank transfer requires destination bank account
        if ($validated['payment_method'] === 'bank_transfer' && empty($validated['destination_bank_account_id'])) {
            return back()->withErrors(['destination_bank_account_id' => 'Rekening tujuan diperlukan untuk transfer bank.']);
        }

        // Validate total allocation matches payment amount
        $totalAllocated = array_sum(array_column($validated['allocations'], 'allocated_amount'));
        if (abs($totalAllocated - $validated['amount']) > 0.01) {
            return back()->withErrors(['allocations' => 'Total alokasi harus sama dengan jumlah pembayaran.']);
        }

        $payment = DB::transaction(function () use ($validated) {
            $payment = AssetInvoicePayment::create([
                'payment_date' => $validated['payment_date'],
                'type' => $validated['type'],
                'partner_id' => $validated['partner_id'],
                'branch_id' => $validated['branch_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'source_account_id' => $validated['source_account_id'],
                'destination_bank_account_id' => $validated['destination_bank_account_id'],
                'reference' => $validated['reference'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'],
            ]);

            foreach ($validated['allocations'] as $allocation) {
                $payment->allocations()->create([
                    'asset_invoice_id' => $allocation['asset_invoice_id'],
                    'allocated_amount' => $allocation['allocated_amount'],
                ]);

                // Update asset invoice status if fully paid
                $this->updateInvoiceStatus($allocation['asset_invoice_id']);
            }

            AssetInvoicePaymentCreated::dispatch($payment);

            // TODO: Create journal entry for payment
            // Example:
            // Debit: Accounts Payable
            // Credit: Cash/Bank Account

            return $payment;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-invoice-payments.create')
                ->with('success', 'Pembayaran berhasil dibuat. Silakan buat pembayaran lainnya.');
        }

        return redirect()->route('asset-invoice-payments.show', $payment->id)
            ->with('success', 'Pembayaran berhasil dibuat.');
    }

    public function show(AssetInvoicePayment $assetInvoicePayment)
    {
        $filters = Session::get('asset_invoice_payments.index_filters', []);
        $assetInvoicePayment->load(['partner', 'allocations.assetInvoice.branch', 'creator', 'updater', 'branch.branchGroup.company', 'sourceAccount', 'destinationBankAccount']);

        return Inertia::render('AssetInvoicePayments/Show', [
            'payment' => $assetInvoicePayment,
            'filters' => $filters,
            'paymentMethods' => AssetInvoicePayment::getPaymentMethods(),
            'paymentTypes' => AssetInvoicePayment::getTypes(),
        ]);
    }

    public function edit(AssetInvoicePayment $assetInvoicePayment)
    {
        $filters = Session::get('asset_invoice_payments.index_filters', []);
        $assetInvoicePayment->load(['partner', 'allocations.assetInvoice', 'sourceAccount', 'destinationBankAccount', 'branch.branchGroup', 'currency']);

        // Get companies
        $companies = Company::orderBy('name', 'asc')->get();
        
        // Get primary currency
        $primaryCurrency = Currency::where('is_primary', true)->first();

        // Get branches and other data for the current company
        $companyId = $assetInvoicePayment->branch->branchGroup->company_id;
        
        $branches = Branch::whereHas('branchGroup', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->orderBy('name', 'asc')->get();

        $partners = Partner::with(['roles', 'activeBankAccounts'])
            ->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->orderBy('name', 'asc')
            ->get();

        // Get cash and bank accounts for payment sources
        $sourceAccounts = Account::whereIn('type', ['kas_bank'])
            ->where('is_parent', false)
            ->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->orderBy('name', 'asc')
            ->get();
            
        // Get currencies available for the company
        $currencies = Currency::whereHas('companyRates', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->with(['companyRates' => function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        }])->orderBy('code', 'asc')->get();

        return Inertia::render('AssetInvoicePayments/Edit', [
            'payment' => $assetInvoicePayment,
            'filters' => $filters,
            'partners' => $partners,
            'companies' => $companies,
            'branches' => $branches,
            'sourceAccounts' => $sourceAccounts,
            'currencies' => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'paymentMethods' => AssetInvoicePayment::getPaymentMethods(),
            'paymentTypes' => AssetInvoicePayment::getTypes(),
            'assetInvoices' => $this->getOutstandingInvoices($assetInvoicePayment->partner_id, $assetInvoicePayment->id, $companyId, $assetInvoicePayment->currency_id, $assetInvoicePayment->type),
        ]);
    }

    public function update(Request $request, AssetInvoicePayment $assetInvoicePayment)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'type' => 'required|in:purchase,rental,sales',
            'partner_id' => 'required|exists:partners,id',
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'source_account_id' => 'required|exists:accounts,id',
            'destination_bank_account_id' => 'nullable|exists:partner_bank_accounts,id',
            'reference' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,check,credit_card,bank_transfer,other',
            'notes' => 'nullable|string',
            'allocations' => 'required|array|min:1',
            'allocations.*.id' => 'nullable|exists:asset_invoice_payment_allocations,id',
            'allocations.*.asset_invoice_id' => 'required|exists:asset_invoices,id',
            'allocations.*.allocated_amount' => 'required|numeric|min:0.01',
        ]);

        // Validate bank transfer requires destination bank account
        if ($validated['payment_method'] === 'bank_transfer' && empty($validated['destination_bank_account_id'])) {
            return back()->withErrors(['destination_bank_account_id' => 'Rekening tujuan diperlukan untuk transfer bank.']);
        }

        // Validate total allocation matches payment amount
        $totalAllocated = array_sum(array_column($validated['allocations'], 'allocated_amount'));
        if (abs($totalAllocated - $validated['amount']) > 0.01) {
            return back()->withErrors(['allocations' => 'Total alokasi harus sama dengan jumlah pembayaran.']);
        }

        DB::transaction(function () use ($validated, $assetInvoicePayment) {
            // Store old allocations for status update
            $oldInvoiceIds = $assetInvoicePayment->allocations->pluck('asset_invoice_id')->toArray();

            $assetInvoicePayment->update([
                'payment_date' => $validated['payment_date'],
                'type' => $validated['type'],
                'partner_id' => $validated['partner_id'],
                'branch_id' => $validated['branch_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'source_account_id' => $validated['source_account_id'],
                'destination_bank_account_id' => $validated['destination_bank_account_id'],
                'reference' => $validated['reference'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'],
            ]);

            // Delete old allocations
            $assetInvoicePayment->allocations()->delete();

            // Create new allocations
            $newInvoiceIds = [];
            foreach ($validated['allocations'] as $allocation) {
                $assetInvoicePayment->allocations()->create([
                    'asset_invoice_id' => $allocation['asset_invoice_id'],
                    'allocated_amount' => $allocation['allocated_amount'],
                ]);
                $newInvoiceIds[] = $allocation['asset_invoice_id'];
            }

            // Update status for all affected invoices
            $allAffectedInvoices = array_unique(array_merge($oldInvoiceIds, $newInvoiceIds));
            foreach ($allAffectedInvoices as $invoiceId) {
                $this->updateInvoiceStatus($invoiceId);
            }

            AssetInvoicePaymentUpdated::dispatch($assetInvoicePayment);

            // TODO: Update journal entry for payment
        });

        return redirect()->route('asset-invoice-payments.show', $assetInvoicePayment->id)
            ->with('success', 'Pembayaran berhasil diubah.');
    }

    public function destroy(Request $request, AssetInvoicePayment $assetInvoicePayment)
    {
        DB::transaction(function () use ($assetInvoicePayment) {
            $invoiceIds = $assetInvoicePayment->allocations->pluck('asset_invoice_id')->toArray();
            
            AssetInvoicePaymentDeleted::dispatch($assetInvoicePayment);
            // Delete allocations
            $assetInvoicePayment->allocations()->delete();
            
            // Delete payment
            $assetInvoicePayment->delete();

            // Update invoice statuses
            foreach ($invoiceIds as $invoiceId) {
                $this->updateInvoiceStatus($invoiceId);
            }

            // TODO: Reverse journal entry
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-invoice-payments.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pembayaran berhasil dihapus.');
        }

        return redirect()->route('asset-invoice-payments.index')
            ->with('success', 'Pembayaran berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            $allInvoiceIds = [];
            
            foreach ($request->ids as $id) {
                $payment = AssetInvoicePayment::find($id);
                if ($payment) {
                    AssetInvoicePaymentDeleted::dispatch($payment);
                    $invoiceIds = $payment->allocations->pluck('asset_invoice_id')->toArray();
                    $allInvoiceIds = array_merge($allInvoiceIds, $invoiceIds);
                    
                    $payment->allocations()->delete();
                    $payment->delete();
                }
            }

            // Update all affected invoice statuses
            foreach (array_unique($allInvoiceIds) as $invoiceId) {
                $this->updateInvoiceStatus($invoiceId);
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-invoice-payments.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pembayaran berhasil dihapus.');
        }
    }

    private function getOutstandingInvoices($partnerId, $excludePaymentId = null, $companyId = null, $currencyId = null, $type = null)
    {
        if (!$partnerId) {
            return collect();
        }

        $query = AssetInvoice::where('partner_id', $partnerId)
            ->whereIn('status', ['open', 'partially_paid'])
            ->with(['branch', 'assetInvoiceDetails', 'currency']);

        // Filter by type if provided
        if ($type) {
            $query->where('type', $type);
        } else {
            // Default filter to exclude lease
            $query->whereIn('type', ['purchase', 'rental']);
        }

        if ($companyId) {
            $query->whereHas('branch.branchGroup', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        // Filter by currency if provided
        if ($currencyId) {
            $query->where('currency_id', $currencyId);
        }

        $invoices = $query->get();

        // Add current allocations if editing
        if ($excludePaymentId) {
            $currentAllocations = AssetInvoicePaymentAllocation::where('asset_invoice_payment_id', $excludePaymentId)
                ->with('assetInvoice.branch', 'assetInvoice.currency')
                ->get()
                ->pluck('assetInvoice')
                ->filter();
            
            $invoices = $invoices->merge($currentAllocations)->unique('id');
        }

        return $invoices->map(function ($invoice) use ($excludePaymentId) {
            $totalPaid = AssetInvoicePaymentAllocation::whereHas('assetInvoicePayment', function ($q) use ($excludePaymentId) {
                if ($excludePaymentId) {
                    $q->where('id', '!=', $excludePaymentId);
                }
            })
            ->where('asset_invoice_id', $invoice->id)
            ->sum('allocated_amount');

            $invoice->outstanding_amount = $invoice->total_amount - $totalPaid;
            $invoice->total_paid = $totalPaid;
            return $invoice;
        })->filter(function ($invoice) {
            return $invoice->outstanding_amount > 0;
        });
    }

    private function updateInvoiceStatus($invoiceId)
    {
        $invoice = AssetInvoice::find($invoiceId);
        if (!$invoice) return;

        $totalPaid = AssetInvoicePaymentAllocation::where('asset_invoice_id', $invoiceId)->sum('allocated_amount');

        if ($totalPaid >= $invoice->total_amount) {
            $invoice->update(['status' => 'paid']);
        } elseif ($totalPaid > 0) {
            $invoice->update(['status' => 'partially_paid']);
        } else {
            $invoice->update(['status' => 'open']);
        }
    }

    private function getFilteredPayments(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_invoice_payments.index_filters', []);

        $query = AssetInvoicePayment::with(['partner', 'allocations.assetInvoice']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(reference)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(notes)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('partner', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['type'])) {
            $query->whereIn('type', $filters['type']);
        }

        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', $filters['partner_id']);
        }

        if (!empty($filters['payment_method'])) {
            $query->whereIn('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('payment_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('payment_date', '<=', $filters['to_date']);
        }

        $sortColumn = $filters['sort'] ?? 'payment_date';
        $sortOrder = $filters['order'] ?? 'desc';

        if ($sortColumn === 'partner.name') {
            $query->join('partners', 'asset_invoice_payments.partner_id', '=', 'partners.id')
                  ->orderBy('partners.name', $sortOrder)
                  ->select('asset_invoice_payments.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $payments = $this->getFilteredPayments($request);
        return Excel::download(new AssetInvoicePaymentsExport($payments), 'asset_invoice_payments.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $payments = $this->getFilteredPayments($request);
        return Excel::download(new AssetInvoicePaymentsExport($payments), 'asset_invoice_payments.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $payments = $this->getFilteredPayments($request);
        return Excel::download(new AssetInvoicePaymentsExport($payments), 'asset_invoice_payments.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
    }

    public function print(AssetInvoicePayment $assetInvoicePayment)
    {
        $assetInvoicePayment->load(['partner', 'allocations.assetInvoice.branch.branchGroup.company', 'creator', 'branch.branchGroup.company', 'sourceAccount', 'destinationBankAccount']);
        
        return Inertia::render('AssetInvoicePayments/Print', [
            'payment' => $assetInvoicePayment,
            'paymentMethods' => AssetInvoicePayment::getPaymentMethods(),
            'paymentTypes' => AssetInvoicePayment::getTypes(),
        ]);
    }
} 