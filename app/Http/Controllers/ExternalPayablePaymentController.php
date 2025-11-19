<?php

namespace App\Http\Controllers;

use App\Exports\ExternalDebtPaymentsExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExternalDebt;
use App\Models\ExternalDebtPayment;
use App\Models\ExternalDebtPaymentDetail;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ExternalPayablePaymentController extends Controller
{
    protected string $type = 'payable';

    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('external_payable_payments.index_filters', []);
        Session::put('external_payable_payments.index_filters', $filters);

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
            $branches = Branch::whereHas('branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', $filters['company_id']);
            })->orderBy('name', 'asc')->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('Debts/ExternalPayablePayments/Index', [
            'items' => $items,
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
        $filters = Session::get('external_payable_payments.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();
        $primaryCurrency = Currency::where('is_primary', true)->first();

        $companyId = $request->company_id;
        $branchId = $request->branch_id;
        $partnerId = $request->partner_id;
        $currencyId = $request->currency_id;

        $branches = collect();
        $partners = collect();
        $currencies = collect();
        $debts = collect(); // unpaid with remaining_amount

        if ($companyId) {
            $branches = Branch::whereHas('branchGroup', fn($q) => $q->where('company_id', $companyId))
                ->orderBy('name', 'asc')->get();

            $partners = Partner::whereHas('companies', fn($q) => $q->where('company_id', $companyId))
                ->orderBy('name', 'asc')->get();

            $currencies = Currency::whereHas('companyRates', fn($q) => $q->where('company_id', $companyId))
                ->with(['companyRates' => fn($q) => $q->where('company_id', $companyId)])
                ->orderBy('code', 'asc')->get();

            $debts = $this->getUnpaidDebts($companyId, $branchId, $partnerId, $currencyId);
        }

        return Inertia::render('Debts/ExternalPayablePayments/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'branches' => fn() => $branches,
            'partners' => fn() => $partners,
            'currencies' => fn() => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'debts' => fn() => $debts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'payment_date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'details' => 'required|array|min:1',
            'details.*.external_debt_id' => 'required|exists:external_debts,id',
            'details.*.amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Validate debts belong to same partner and type, and not overpay
        $debtIds = collect($validated['details'])->pluck('external_debt_id')->unique()->values();
        $debts = ExternalDebt::whereIn('id', $debtIds)->get();
        if ($debts->isEmpty() || $debts->contains(fn($d) => $d->type !== $this->type)) {
            return Redirect::back()->with('error', 'Jenis hutang/piutang tidak sesuai.');
        }
        if ($debts->contains(fn($d) => $d->partner_id != $validated['partner_id'])) {
            return Redirect::back()->with('error', 'Semua dokumen harus untuk partner yang sama.');
        }
        $overpaid = $this->detectOverpay($validated['details']);
        if ($overpaid) {
            return Redirect::back()->with('error', 'Jumlah pembayaran melebihi sisa hutang untuk salah satu dokumen.');
        }

        $sumAmount = collect($validated['details'])->sum('amount');

        $payment = DB::transaction(function () use ($validated, $sumAmount) {
            $primaryCurrencyAmount = $sumAmount * $validated['exchange_rate'];
            $payment = ExternalDebtPayment::create([
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
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['details'] as $detail) {
                ExternalDebtPaymentDetail::create([
                    'external_debt_payment_id' => $payment->id,
                    'external_debt_id' => $detail['external_debt_id'],
                    'amount' => $detail['amount'],
                    'primary_currency_amount' => $detail['amount'] * $validated['exchange_rate'],
                    'notes' => null,
                ]);
            }
            return $payment;
        });

        return redirect()->route('external-payable-payments.show', $payment->id)
            ->with('success', 'Pembayaran hutang berhasil dibuat.');
    }

    public function show(Request $request, ExternalDebtPayment $externalPayablePayment)
    {
        $filters = Session::get('external_payable_payments.index_filters', []);
        $externalPayablePayment->load([
            'partner',
            'branch.branchGroup.company',
            'currency',
            'details.externalDebt',
            'creator',
            'updater'
        ]);

        return Inertia::render('Debts/ExternalPayablePayments/Show', [
            'item' => $externalPayablePayment,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, ExternalDebtPayment $externalPayablePayment)
    {
        $filters = Session::get('external_payable_payments.index_filters', []);
        $externalPayablePayment->load(['branch.branchGroup', 'partner', 'currency', 'details']);

        $companyId = $externalPayablePayment->branch->branchGroup->company_id;
        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        $branchId = $request->branch_id ?: $externalPayablePayment->branch_id;
        if ($request->branch_id) {
            $branchId = $request->branch_id;
        }

        $partnerId = $request->partner_id ?: $externalPayablePayment->partner_id;
        $currencyId = $request->currency_id ?: $externalPayablePayment->currency_id;

        $primaryCurrency = Currency::where('is_primary', true)->first();
        $currencies = Currency::whereHas('companyRates', fn($q) => $q->where('company_id', $companyId))
            ->with(['companyRates' => fn($q) => $q->where('company_id', $companyId)])
            ->orderBy('code', 'asc')->get();

        return Inertia::render('Debts/ExternalPayablePayments/Edit', [
            'item' => $externalPayablePayment,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', fn($q) => $q->where('company_id', $companyId))->orderBy('name', 'asc')->get(),
            'partners' => Partner::whereHas('companies', fn($q) => $q->where('company_id', $companyId))->orderBy('name', 'asc')->get(),
            'currencies' => $currencies,
            'primaryCurrency' => $primaryCurrency,
            'debts' => $this->getUnpaidDebts($companyId, $branchId, $partnerId, $currencyId, $externalPayablePayment->id),
        ]);
    }

    public function update(Request $request, ExternalDebtPayment $externalPayablePayment)
    {
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'payment_date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'details' => 'required|array|min:1',
            'details.*.external_debt_id' => 'required|exists:external_debts,id',
            'details.*.amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $debtIds = collect($validated['details'])->pluck('external_debt_id')->unique()->values();
        $debts = ExternalDebt::whereIn('id', $debtIds)->get();
        if ($debts->isEmpty() || $debts->contains(fn($d) => $d->type !== $this->type)) {
            return Redirect::back()->with('error', 'Jenis hutang/piutang tidak sesuai.');
        }
        if ($debts->contains(fn($d) => $d->partner_id != $validated['partner_id'])) {
            return Redirect::back()->with('error', 'Semua dokumen harus untuk partner yang sama.');
        }
        $overpaid = $this->detectOverpay($validated['details'], $externalPayablePayment->id);
        if ($overpaid) {
            return Redirect::back()->with('error', 'Jumlah pembayaran melebihi sisa hutang untuk salah satu dokumen.');
        }

        $sumAmount = collect($validated['details'])->sum('amount');

        DB::transaction(function () use ($validated, $externalPayablePayment, $sumAmount) {
            $primaryCurrencyAmount = $sumAmount * $validated['exchange_rate'];
            $externalPayablePayment->update([
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
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Sync details: simple approach - delete and recreate
            ExternalDebtPaymentDetail::where('external_debt_payment_id', $externalPayablePayment->id)->delete();
            foreach ($validated['details'] as $detail) {
                ExternalDebtPaymentDetail::create([
                    'external_debt_payment_id' => $externalPayablePayment->id,
                    'external_debt_id' => $detail['external_debt_id'],
                    'amount' => $detail['amount'],
                    'primary_currency_amount' => $detail['amount'] * $validated['exchange_rate'],
                ]);
            }
        });

        return redirect()->route('external-payable-payments.show', $externalPayablePayment->id)
            ->with('success', 'Pembayaran hutang berhasil diubah.');
    }

    public function destroy(Request $request, ExternalDebtPayment $externalPayablePayment)
    {
        DB::transaction(function () use ($externalPayablePayment) {
            $externalPayablePayment->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('external-payable-payments.index') . ($currentQuery ? '?' . $currentQuery : '');
            return Redirect::to($redirectUrl)->with('success', 'Pembayaran hutang berhasil dihapus.');
        }

        return Redirect::route('external-payable-payments.index')->with('success', 'Pembayaran hutang berhasil dihapus.');
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
                $item->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('external-payable-payments.index') . ($currentQuery ? '?' . $currentQuery : '');
            return Redirect::to($redirectUrl)->with('success', 'Pembayaran hutang terpilih berhasil dihapus.');
        }
        return redirect()->route('external-payable-payments.index')->with('success', 'Pembayaran hutang terpilih berhasil dihapus.');
    }

    private function getFiltered(Request $request)
    {
        $filters = $request->all() ?: Session::get('external_payable_payments.index_filters', []);
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
            $query->whereHas('branch.branchGroup', fn($q) => $q->whereIn('company_id', $filters['company_id']));
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
        return Excel::download(new ExternalDebtPaymentsExport($items, 'Pembayaran Hutang Eksternal'), 'external-payable-payments.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $items = $this->getFiltered($request);
        return Excel::download(new ExternalDebtPaymentsExport($items, 'Pembayaran Hutang Eksternal'), 'external-payable-payments.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        return redirect()->back()->with('error', 'Ekspor PDF belum diimplementasikan.');
    }

    public function print(ExternalDebtPayment $externalPayablePayment)
    {
        $externalPayablePayment->load(['partner', 'branch.branchGroup.company', 'currency', 'details.externalDebt', 'creator', 'updater']);
        return Inertia::render('Debts/ExternalPayablePayments/Print', [
            'item' => $externalPayablePayment,
        ]);
    }

    private function getUnpaidDebts($companyId = null, $branchId = null, $partnerId = null, $currencyId = null, $includePaymentId = null)
    {
        $debts = ExternalDebt::with(['partner', 'currency'])
            ->where('type', $this->type)
            ->when($companyId, function ($q) use ($companyId) {
                $q->whereHas('branch.branchGroup', fn($bq) => $bq->where('company_id', $companyId));
            })
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($partnerId, fn($q) => $q->where('partner_id', $partnerId))
            ->when($currencyId, fn($q) => $q->where('currency_id', $currencyId))
            ->orderBy('due_date', 'asc')
            ->orderBy('issue_date', 'asc')
            ->get();

        $paidSumsQuery = DB::table('external_debt_payment_details')
            ->join('external_debt_payments', 'external_debt_payments.id', '=', 'external_debt_payment_details.external_debt_payment_id')
            ->whereNull('external_debt_payment_details.deleted_at')
            ->whereNull('external_debt_payments.deleted_at')
            ->where('external_debt_payments.type', $this->type);
        if ($includePaymentId) {
            // exclude current payment from sum to allow editing amounts
            $paidSumsQuery->where('external_debt_payment_details.external_debt_payment_id', '!=', $includePaymentId);
        }
        $paidSums = $paidSumsQuery
            ->groupBy('external_debt_id')
            ->pluck(DB::raw('SUM(external_debt_payment_details.amount) as total'), 'external_debt_id');

        return $debts->map(function ($d) use ($paidSums) {
            $paid = (float)($paidSums[$d->id] ?? 0);
            $remaining = (float)$d->amount - $paid;
            $d->remaining_amount = max($remaining, 0);
            return $d;
        })->filter(fn($d) => $d->remaining_amount > 0)->values();
    }

    private function detectOverpay(array $details, $excludePaymentId = null): bool
    {
        $grouped = collect($details)->groupBy('external_debt_id')->map->sum('amount');
        if ($grouped->isEmpty()) return false;
        $paidSumsQuery = DB::table('external_debt_payment_details')
            ->join('external_debt_payments', 'external_debt_payments.id', '=', 'external_debt_payment_details.external_debt_payment_id')
            ->whereNull('external_debt_payment_details.deleted_at')
            ->whereNull('external_debt_payments.deleted_at')
            ->where('external_debt_payments.type', $this->type);
        if ($excludePaymentId) {
            $paidSumsQuery->where('external_debt_payment_details.external_debt_payment_id', '!=', $excludePaymentId);
        }
        $paidSums = $paidSumsQuery
            ->whereIn('external_debt_payment_details.external_debt_id', $grouped->keys())
            ->groupBy('external_debt_payment_details.external_debt_id')
            ->pluck(DB::raw('SUM(external_debt_payment_details.amount) as total'), 'external_debt_payment_details.external_debt_id');

        $debts = ExternalDebt::whereIn('id', $grouped->keys())->pluck('amount', 'id');
        foreach ($grouped as $debtId => $newAmount) {
            $alreadyPaid = (float)($paidSums[$debtId] ?? 0);
            $totalDebt = (float)($debts[$debtId] ?? 0);
            if ($newAmount + $alreadyPaid - 0.00001 > $totalDebt) {
                return true;
            }
        }
        return false;
    }
}


