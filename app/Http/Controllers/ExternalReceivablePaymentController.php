<?php

namespace App\Http\Controllers;

use App\Exports\ExternalDebtPaymentsExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExternalDebt;
use App\Models\ExternalDebtPayment;
use App\Models\PartnerBankAccount;
use App\Models\Partner;
use App\Events\Debt\ExternalDebtPaymentCreated;
use App\Events\Debt\ExternalDebtPaymentUpdated;
use App\Events\Debt\ExternalDebtPaymentDeleted;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ExternalReceivablePaymentController extends ExternalDebtPaymentController
{
    protected string $type = 'receivable';

    public function __construct()
    {
        parent::__construct($this->type);
    }

    public function index(Request $request)
    {
        // identical to parent but rendering different view
        $filters = $request->all() ?: Session::get('external_receivable_payments.index_filters', []);
        Session::put('external_receivable_payments.index_filters', $filters);

        $query = ExternalDebtPayment::query()
            ->with([
                'partner',
                'branch.branchGroup.company',
                'currency',
            ])
            ->where('type', $this->type);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(number)'), 'like', "%{$search}%")
                    ->orWhere(DB::raw('lower(notes)'), 'like', "%{$search}%")
                    ->orWhere(DB::raw('lower(reference_number)'), 'like', "%{$search}%")
                    ->orWhereHas('partner', function ($pq) use ($search) {
                        $pq->where(DB::raw('lower(name)'), 'like', "%{$search}%");
                    })
                    ->orWhereHas('branch', function ($bq) use ($search) {
                        $bq->where(DB::raw('lower(name)'), 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('branch.branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', $filters['company_id']);
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['partner_id'])) {
            $query->whereIn('partner_id', $filters['partner_id']);
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
            $query->join('partners', 'external_debt_payments.partner_id', '=', 'partners.id')
                ->orderBy('partners.name', $sortOrder)
                ->select('external_debt_payments.*');
        } elseif ($sortColumn === 'branch.name') {
            $query->join('branches', 'external_debt_payments.branch_id', '=', 'branches.id')
                ->orderBy('branches.name', $sortOrder)
                ->select('external_debt_payments.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $items = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        $partners = Partner::orderBy('name', 'asc')->get();
        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', fn($q) => $q->whereIn('company_id', $filters['company_id']))->orderBy('name', 'asc')->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('Debts/ExternalReceivablePayments/Index', [
            'items' => $items,
            'companies' => $companies,
            'branches' => $branches,
            'partners' => $partners,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'paymentMethodOptions' => ExternalDebtPayment::paymentMethodOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('external_receivable_payments.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();
        $primaryCurrency = Currency::where('is_primary', true)->first();

        $companyId = $request->company_id;
        $branchId = $request->branch_id;
        $partnerId = $request->partner_id;
        $currencyId = $request->currency_id;

        $branches = collect();
        $partners = collect();
        $currencies = collect();
        $debts = collect();
        $accounts = collect();

        if ($companyId) {
            $branches = Branch::whereHas('branchGroup', fn($q) => $q->where('company_id', $companyId))
                ->orderBy('name', 'asc')->get();

            $partners = Partner::whereHas('companies', fn($q) => $q->where('company_id', $companyId))
                ->orderBy('name', 'asc')->get();

            $currencies = Currency::whereHas('companyRates', fn($q) => $q->where('company_id', $companyId))
                ->with(['companyRates' => fn($q) => $q->where('company_id', $companyId)])
                ->orderBy('code', 'asc')->get();

            $debts = $this->getUnpaidDebts($companyId, $branchId, $partnerId, $currencyId);

            $accounts = Account::whereHas('companies', fn($q) => $q->where('company_id', $companyId))
                ->where('is_parent', false)
                ->orderBy('code', 'asc')->get();
        }

        return Inertia::render('Debts/ExternalReceivablePayments/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'branches' => fn() => $branches,
            'partners' => fn() => $partners,
            'currencies' => fn() => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'debts' => fn() => $debts,
            'accounts' => fn() => $accounts,
            'paymentMethodOptions' => ExternalDebtPayment::paymentMethodOptions(),
        ]);
    }

    public function show(Request $request, ExternalDebtPayment $externalReceivablePayment)
    {
        $filters = Session::get('external_receivable_payments.index_filters', []);
        $externalReceivablePayment->load([
            'partner', 
            'branch.branchGroup.company', 
            'currency', 
            'details.externalDebt', 
            'partnerBankAccount', 
            'account',
            'creator', 
            'updater'
        ]);

        return Inertia::render('Debts/ExternalReceivablePayments/Show', [
            'item' => $externalReceivablePayment,
            'filters' => $filters,
            'paymentMethodOptions' => ExternalDebtPayment::paymentMethodOptions(),
        ]);
    }

    public function edit(Request $request, ExternalDebtPayment $externalReceivablePayment)
    {
        $filters = Session::get('external_receivable_payments.index_filters', []);
        $externalReceivablePayment->load(['branch.branchGroup', 'partner', 'currency', 'details']);

        $companyId = $externalReceivablePayment->branch->branchGroup->company_id;
        if ($request->company_id) {
            $companyId = $request->company_id;
        }
        $branchId = $request->branch_id ?: $externalReceivablePayment->branch_id;
        if ($request->branch_id) {
            $branchId = $request->branch_id;
        }
        $partnerId = $request->partner_id ?: $externalReceivablePayment->partner_id;
        $currencyId = $request->currency_id ?: $externalReceivablePayment->currency_id;

        $primaryCurrency = Currency::where('is_primary', true)->first();
        $currencies = Currency::whereHas('companyRates', fn($q) => $q->where('company_id', $companyId))
            ->with(['companyRates' => fn($q) => $q->where('company_id', $companyId)])
            ->orderBy('code', 'asc')->get();

        return Inertia::render('Debts/ExternalReceivablePayments/Edit', [
            'item' => $externalReceivablePayment,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', fn($q) => $q->where('company_id', $companyId))->orderBy('name', 'asc')->get(),
            'partners' => Partner::whereHas('companies', fn($q) => $q->where('company_id', $companyId))->orderBy('name', 'asc')->get(),
            'currencies' => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'debts' => $this->getUnpaidDebts($companyId, $branchId, $partnerId, $currencyId, $externalReceivablePayment->id),
            'accounts' => Account::whereHas('companies', fn($q) => $q->where('company_id', $companyId))->where('is_parent', false)->orderBy('code', 'asc')->get(),
            'paymentMethodOptions' => ExternalDebtPayment::paymentMethodOptions(),
        ]);
    }

    public function update(Request $request, ExternalDebtPayment $externalReceivablePayment)
    {
        // duplicate parent validations because type-specific differences are minimal, but we enforce method fields here as well
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'payment_date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'payment_method' => 'required|in:tunai,transfer,cek,giro',
            'partner_bank_account_id' => 'nullable|required_if:payment_method,transfer|exists:partner_bank_accounts,id',
            'instrument_date' => 'nullable|required_if:payment_method,cek,\\,giro|date',
            'withdrawal_date' => 'nullable|required_if:payment_method,cek,\\,giro|date|after_or_equal:instrument_date',
            'details' => 'required|array|min:1',
            'details.*.external_debt_id' => 'required|exists:external_debts,id',
            'details.*.amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validated['payment_method'] === 'transfer' && !empty($validated['partner_bank_account_id'])) {
            $pba = PartnerBankAccount::findOrFail($validated['partner_bank_account_id']);
            if ($pba->partner_id != $validated['partner_id']) {
                return Redirect::back()->with('error', 'Rekening bank tidak sesuai dengan partner yang dipilih.');
            }
        }

        $debtIds = collect($validated['details'])->pluck('external_debt_id')->unique()->values();
        $debts = ExternalDebt::whereIn('id', $debtIds)->get();
        if ($debts->isEmpty() || $debts->contains(fn($d) => $d->type !== $this->type)) {
            return Redirect::back()->with('error', 'Jenis hutang/piutang tidak sesuai.');
        }
        if ($debts->contains(fn($d) => $d->partner_id != $validated['partner_id'])) {
            return Redirect::back()->with('error', 'Semua dokumen harus untuk partner yang sama.');
        }
        $overpaid = $this->detectOverpay($validated['details'], $externalReceivablePayment->id);
        if ($overpaid) {
            return Redirect::back()->with('error', 'Jumlah pembayaran melebihi sisa piutang untuk salah satu dokumen.');
        }

        $sumAmount = collect($validated['details'])->sum('amount');

        DB::transaction(function () use ($validated, $externalReceivablePayment, $sumAmount) {
            $primaryCurrencyAmount = $sumAmount * $validated['exchange_rate'];
            $externalReceivablePayment->update([
                'type' => $this->type,
                'partner_id' => $validated['partner_id'],
                'branch_id' => $validated['branch_id'],
                'account_id' => $validated['account_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'payment_date' => $validated['payment_date'],
                'amount' => $sumAmount,
                'primary_currency_amount' => $primaryCurrencyAmount,
                'payment_method' => $validated['payment_method'] ?? null,
                'partner_bank_account_id' => $validated['partner_bank_account_id'] ?? null,
                'instrument_date' => $validated['instrument_date'] ?? null,
                'withdrawal_date' => $validated['withdrawal_date'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Sync details
            \App\Models\ExternalDebtPaymentDetail::where('external_debt_payment_id', $externalReceivablePayment->id)->delete();
            foreach ($validated['details'] as $detail) {
                \App\Models\ExternalDebtPaymentDetail::create([
                    'external_debt_payment_id' => $externalReceivablePayment->id,
                    'external_debt_id' => $detail['external_debt_id'],
                    'amount' => $detail['amount'],
                    'primary_currency_amount' => $detail['amount'] * $validated['exchange_rate'],
                ]);
            }
        });

        ExternalDebtPaymentUpdated::dispatch($externalReceivablePayment);
        return redirect()->route('external-receivable-payments.show', $externalReceivablePayment->id)
            ->with('success', 'Penerimaan piutang berhasil diubah.');
    }

    public function destroy(Request $request, ExternalDebtPayment $externalReceivablePayment)
    {
        ExternalDebtPaymentDeleted::dispatch($externalReceivablePayment->loadMissing('details'));
        DB::transaction(function () use ($externalReceivablePayment) {
            $externalReceivablePayment->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('external-receivable-payments.index') . ($currentQuery ? '?' . $currentQuery : '');
            return Redirect::to($redirectUrl)->with('success', 'Penerimaan piutang berhasil dihapus.');
        }

        return Redirect::route('external-receivable-payments.index')->with('success', 'Penerimaan piutang berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:external_debt_payments,id',
        ]);

        DB::transaction(function () use ($validated) {
            $items = ExternalDebtPayment::whereIn('id', $validated['ids'])
                ->whereHas('externalDebt', fn($q) => $q->where('type', $this->type))
                ->get();
            foreach ($items as $item) {
                ExternalDebtPaymentDeleted::dispatch($item->loadMissing('details.externalDebt'));
                $item->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('external-receivable-payments.index') . ($currentQuery ? '?' . $currentQuery : '');
            return Redirect::to($redirectUrl)->with('success', 'Penerimaan piutang terpilih berhasil dihapus.');
        }
        return redirect()->route('external-receivable-payments.index')->with('success', 'Penerimaan piutang terpilih berhasil dihapus.');
    }

    private function getFiltered(Request $request)
    {
        $filters = $request->all() ?: Session::get('external_receivable_payments.index_filters', []);
        $query = ExternalDebtPayment::with(['partner', 'branch.branchGroup.company', 'currency'])
            ->where('type', $this->type);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(number)'), 'like', "%{$search}%")
                    ->orWhere(DB::raw('lower(notes)'), 'like', "%{$search}%")
                    ->orWhere(DB::raw('lower(reference_number)'), 'like', "%{$search}%");
            });
        }
        if (!empty($filters['company_id'])) {
            $query->whereHas('externalDebt.branch.branchGroup', fn($q) => $q->whereIn('company_id', $filters['company_id']));
        }
        if (!empty($filters['branch_id'])) {
            $query->whereHas('externalDebt', fn($q) => $q->whereIn('branch_id', $filters['branch_id']));
        }
        if (!empty($filters['partner_id'])) {
            $query->whereHas('externalDebt', fn($q) => $q->whereIn('partner_id', $filters['partner_id']));
        }
        if (!empty($filters['from_date'])) {
            $query->whereDate('payment_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('payment_date', '<=', $filters['to_date']);
        }
        $sort = $filters['sort'] ?? 'payment_date';
        $order = $filters['order'] ?? 'desc';
        if ($sort === 'partner.name') {
            $query->join('partners', 'external_debt_payments.partner_id', '=', 'partners.id')
                ->orderBy('partners.name', $order)
                ->select('external_debt_payments.*');
        } elseif ($sort === 'branch.name') {
            $query->join('branches', 'external_debt_payments.branch_id', '=', 'branches.id')
                ->orderBy('branches.name', $order)
                ->select('external_debt_payments.*');
        } else {
            $query->orderBy($sort, $order);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $items = $this->getFiltered($request);
        return Excel::download(new ExternalDebtPaymentsExport($items, 'Penerimaan Piutang Eksternal'), 'external-receivable-payments.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $items = $this->getFiltered($request);
        return Excel::download(new ExternalDebtPaymentsExport($items, 'Penerimaan Piutang Eksternal'), 'external-receivable-payments.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        return redirect()->back()->with('error', 'Ekspor PDF belum diimplementasikan.');
    }

    public function print(ExternalDebtPayment $externalReceivablePayment)
    {
        $externalReceivablePayment->load(['partner', 'branch.branchGroup.company', 'currency', 'details.externalDebt', 'creator', 'updater']);
        return Inertia::render('Debts/ExternalReceivablePayments/Print', [
            'item' => $externalReceivablePayment,
            'paymentMethodOptions' => ExternalDebtPayment::paymentMethodOptions(),
        ]);
    }
}


