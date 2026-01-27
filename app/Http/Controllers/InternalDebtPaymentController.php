<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\Debt\InternalDebtPaymentApproved;
use App\Events\Debt\InternalDebtPaymentDeleted;
use App\Exports\InternalDebtPaymentsExport;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InternalDebt;
use App\Models\InternalDebtPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class InternalDebtPaymentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('internal_debt_payments.index_filters', []);
        Session::put('internal_debt_payments.index_filters', $filters);

        $query = InternalDebtPayment::with(['branch.branchGroup.company', 'currency', 'details.internalDebt']);

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(number)'), 'like', "%$search%")
                    ->orWhere(DB::raw('lower(reference_number)'), 'like', "%$search%")
                    ->orWhere(DB::raw('lower(notes)'), 'like', "%$search%");
            });
        }

        if (! empty($filters['company_id'])) {
            $query->whereHas('branch', function ($q) use ($filters) {
                $q->whereHas('branchGroup', function ($qq) use ($filters) {
                    $qq->whereIn('company_id', $filters['company_id']);
                });
            });
        }
        if (! empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }
        if (! empty($filters['counterparty_company_id'])) {
            $query->whereHas('details.internalDebt.counterpartyBranch.branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', $filters['counterparty_company_id']);
            });
        }
        if (! empty($filters['counterparty_branch_id'])) {
            $query->whereHas('details.internalDebt', function ($q) use ($filters) {
                $q->whereIn('counterparty_branch_id', $filters['counterparty_branch_id']);
            });
        }
        if (! empty($filters['currency_id'])) {
            $query->whereIn('currency_id', (array) $filters['currency_id']);
        }
        if (! empty($filters['from_date'])) {
            $query->whereDate('payment_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('payment_date', '<=', $filters['to_date']);
        }
        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sort = $filters['sort'] ?? 'payment_date';
        $order = $filters['order'] ?? 'desc';

        if ($sort === 'branch.name') {
            $query->join('branches', 'internal_debt_payments.branch_id', '=', 'branches.id')
                ->orderBy('branches.name', $order)
                ->select('internal_debt_payments.*');
        } else {
            $query->orderBy($sort, $order);
        }

        $items = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();
        $branches = ! empty($filters['company_id'])
            ? Branch::whereHas('branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', $filters['company_id']);
            })->orderBy('name', 'asc')->get()
            : Branch::orderBy('name', 'asc')->get();

        $counterpartyBranches = ! empty($filters['counterparty_company_id'])
            ? Branch::whereHas('branchGroup', function ($q) use ($filters) {
                $q->whereIn('company_id', $filters['counterparty_company_id']);
            })->orderBy('name', 'asc')->get()
            : Branch::orderBy('name', 'asc')->get();

        return Inertia::render('InternalDebtPayments/Index', [
            'items' => $items,
            'companies' => $companies,
            'branches' => $branches,
            'counterpartyBranches' => $counterpartyBranches,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'paymentStatusOptions' => InternalDebtPayment::statusOptions(),
            'paymentStatusStyles' => InternalDebtPayment::statusStyles(),
            'paymentMethodOptions' => InternalDebtPayment::paymentMethodOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('internal_debt_payments.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();
        $currencies = collect();
        $branches = collect();
        $counterpartyBranches = collect();
        $debts = collect();
        $accounts = collect();
        $counterpartyAccounts = collect();

        $companyId = $request->company_id;
        $branchId = $request->branch_id;
        $counterpartyCompanyId = $request->counterparty_company_id;
        $counterpartyBranchId = $request->counterparty_branch_id;
        $currencyId = $request->currency_id;

        if ($companyId) {
            $branches = Branch::whereHas('branchGroup', fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('name', 'asc')->get();

            $currencies = Currency::whereHas('companyRates', fn ($q) => $q->where('company_id', $companyId))
                ->with(['companyRates' => fn ($q) => $q->where('company_id', $companyId)])
                ->orderBy('code', 'asc')
                ->get();

            $accounts = Account::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
                ->where('is_parent', false)
                ->with('currencies.companyRates')
                ->orderBy('code', 'asc')
                ->get();
        }
        if ($counterpartyCompanyId) {
            $counterpartyBranches = Branch::whereHas('branchGroup', fn ($q) => $q->where('company_id', $counterpartyCompanyId))
                ->orderBy('name', 'asc')->get();

            $counterpartyAccounts = Account::whereHas('companies', function ($query) use ($counterpartyCompanyId) {
                $query->where('company_id', $counterpartyCompanyId);
            })
                ->where('is_parent', false)
                ->with('currencies.companyRates')
                ->orderBy('code', 'asc')
                ->get();
        }
        if ($companyId && $branchId && $counterpartyCompanyId && $counterpartyBranchId && $currencyId) {
            $debts = $this->getUnpaidDebts($companyId, $branchId, $counterpartyCompanyId, $counterpartyBranchId, $currencyId, null);
        }

        return Inertia::render('InternalDebtPayments/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'branches' => fn () => $branches,
            'counterpartyBranches' => fn () => $counterpartyBranches,
            'currencies' => fn () => $currencies,
            'debts' => fn () => $debts,
            'accounts' => fn () => $accounts,
            'counterpartyAccounts' => fn () => $counterpartyAccounts,
            'paymentStatusOptions' => InternalDebtPayment::statusOptions(),
            'paymentMethodOptions' => InternalDebtPayment::paymentMethodOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'payment_date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'payment_method' => 'required|string|in:cash,transfer,cek,giro,credit_card,qris,paypal,midtrans',
            'reference_number' => 'nullable|string',
            'trace_number' => [
                'nullable',
                'required_if:payment_method,credit_card,qris',
                'string',
                'max:100',
            ],
            'counterparty_account_id' => 'nullable|exists:accounts,id',
            'instrument_date' => 'nullable|date',
            'withdrawal_date' => 'nullable|date|after_or_equal:instrument_date',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.internal_debt_id' => 'required|exists:internal_debts,id',
            'details.*.amount' => 'required|numeric|min:0.01',
        ]);

        $totalAmount = 0;
        $firstDebt = null;
        foreach ($validated['details'] as $detail) {
            $totalAmount += $detail['amount'];
            $debt = InternalDebt::find($detail['internal_debt_id']);
            if (! $firstDebt) {
                $firstDebt = $debt;
            } else {
                if ($debt->type !== $firstDebt->type) {
                    return Redirect::back()->with('error', 'Semua hutang/piutang yang dipilih harus memiliki tipe yang sama.');
                }
            }
        }

        DB::transaction(function () use ($validated, $totalAmount, $firstDebt) {
            $payment = InternalDebtPayment::create([
                'type' => $firstDebt?->type ?? 'payable',
                'branch_id' => $validated['branch_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'payment_date' => $validated['payment_date'],
                'account_id' => $validated['account_id'],
                'payment_method' => $validated['payment_method'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'counterparty_account_id' => $validated['counterparty_account_id'] ?? null,
                'instrument_date' => $validated['instrument_date'] ?? null,
                'withdrawal_date' => $validated['withdrawal_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'amount' => $totalAmount,
                'primary_currency_amount' => $totalAmount * $validated['exchange_rate'],
                'status' => 'pending',
            ]);

            foreach ($validated['details'] as $detail) {
                $debt = InternalDebt::findOrFail($detail['internal_debt_id']);
                $payment->details()->create([
                    'internal_debt_id' => $debt->id,
                    'amount' => $detail['amount'],
                    'primary_currency_amount' => $detail['amount'] * $validated['exchange_rate'],
                    'notes' => $detail['notes'] ?? null,
                ]);
            }
        });

        return redirect()->route('internal-debt-payments.index')->with('success', 'Pembayaran Internal berhasil dibuat.');
    }

    public function show(Request $request, InternalDebtPayment $internalDebtPayment)
    {
        $filters = Session::get('internal_debt_payments.index_filters', []);
        $internalDebtPayment->load(['branch.branchGroup.company', 'currency', 'details.internalDebt', 'creator', 'updater']);
        // derive counterparty company from first detail
        $counterpartyCompanyId = optional($internalDebtPayment->details->first()?->internalDebt?->counterpartyCompany)->id
            ?? optional($internalDebtPayment->details->first()?->internalDebt)->counterparty_company_id;
        $counterpartyAccounts = collect();
        if ($counterpartyCompanyId) {
            $counterpartyAccounts = Account::whereHas('companies', function ($query) use ($counterpartyCompanyId) {
                $query->where('company_id', $counterpartyCompanyId);
            })
                ->where('is_parent', false)
                ->with('currencies.companyRates')
                ->orderBy('code', 'asc')
                ->get();
        }

        return Inertia::render('InternalDebtPayments/Show', [
            'item' => $internalDebtPayment,
            'filters' => $filters,
            'paymentStatusOptions' => InternalDebtPayment::statusOptions(),
            'paymentStatusStyles' => InternalDebtPayment::statusStyles(),
            'paymentMethodOptions' => InternalDebtPayment::paymentMethodOptions(),
            'counterpartyAccounts' => fn () => $counterpartyAccounts,
        ]);
    }

    public function edit(Request $request, InternalDebtPayment $internalDebtPayment)
    {
        if ($internalDebtPayment->status !== PaymentStatus::PENDING) {
            return Redirect::back()->with('error', 'Tidak dapat mengubah pembayaran yang tidak berstatus pending.');
        }

        $filters = Session::get('internal_debt_payments.index_filters', []);
        $internalDebtPayment->load(['branch.branchGroup.company', 'currency', 'details.internalDebt']);

        $companyId = $internalDebtPayment->branch->branchGroup->company_id;
        if ($request->company_id) {
            $companyId = $request->company_id;
        }
        $branchId = $internalDebtPayment->branch_id;
        if ($request->branch_id) {
            $branchId = $request->branch_id;
        }

        $counterpartyCompanyId = $request->counterparty_company_id;
        $counterpartyBranchId = $request->counterparty_branch_id;
        $currencyId = $request->currency_id ?: $internalDebtPayment->currency_id;

        $companies = Company::orderBy('name', 'asc')->get();
        $branches = Branch::whereHas('branchGroup', fn ($q) => $q->where('company_id', $companyId))->orderBy('name', 'asc')->get();
        $currencies = Currency::whereHas('companyRates', fn ($q) => $q->where('company_id', $companyId))
            ->with(['companyRates' => fn ($q) => $q->where('company_id', $companyId)])
            ->orderBy('code', 'asc')->get();

        $counterpartyBranches = collect();
        if ($counterpartyCompanyId) {
            $counterpartyBranches = Branch::whereHas('branchGroup', fn ($q) => $q->where('company_id', $counterpartyCompanyId))->orderBy('name', 'asc')->get();
        }

        $debts = collect();
        if ($companyId && $branchId && $counterpartyCompanyId && $counterpartyBranchId && $currencyId) {
            $debts = $this->getUnpaidDebts($companyId, $branchId, $counterpartyCompanyId, $counterpartyBranchId, $currencyId, $internalDebtPayment->id);
        }

        $accounts = collect();
        if ($companyId) {
            $accounts = Account::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
                ->where('is_parent', false)
                ->with('currencies.companyRates')
                ->orderBy('code', 'asc')
                ->get();
        }
        $counterpartyAccounts = collect();
        if ($counterpartyCompanyId) {
            $counterpartyAccounts = Account::whereHas('companies', function ($query) use ($counterpartyCompanyId) {
                $query->where('company_id', $counterpartyCompanyId);
            })
                ->where('is_parent', false)
                ->with('currencies.companyRates')
                ->orderBy('code', 'asc')
                ->get();
        }

        return Inertia::render('InternalDebtPayments/Edit', [
            'item' => $internalDebtPayment,
            'filters' => $filters,
            'companies' => $companies,
            'branches' => $branches,
            'counterpartyBranches' => fn () => $counterpartyBranches,
            'currencies' => $currencies,
            'debts' => fn () => $debts,
            'accounts' => fn () => $accounts,
            'counterpartyAccounts' => fn () => $counterpartyAccounts,
            'paymentStatusOptions' => InternalDebtPayment::statusOptions(),
            'paymentMethodOptions' => InternalDebtPayment::paymentMethodOptions(),
        ]);
    }

    public function update(Request $request, InternalDebtPayment $internalDebtPayment)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'payment_date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'payment_method' => 'required|string|in:cash,transfer,cek,giro,credit_card,qris,paypal,midtrans',
            'reference_number' => 'nullable|string',
            'trace_number' => [
                'nullable',
                'required_if:payment_method,credit_card,qris',
                'string',
                'max:100',
            ],
            'counterparty_account_id' => 'nullable|exists:accounts,id',
            'instrument_date' => 'nullable|date',
            'withdrawal_date' => 'nullable|date|after_or_equal:instrument_date',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.id' => 'nullable|exists:internal_debt_payment_details,id',
            'details.*.internal_debt_id' => 'required|exists:internal_debts,id',
            'details.*.amount' => 'required|numeric|min:0.01',
        ]);

        if ($internalDebtPayment->status !== PaymentStatus::PENDING) {
            return Redirect::back()->with('error', 'Tidak dapat mengubah pembayaran yang tidak berstatus pending.');
        }

        $totalAmount = 0;
        $firstDebt = null;
        foreach ($validated['details'] as $detail) {
            $totalAmount += $detail['amount'];
            $debt = InternalDebt::find($detail['internal_debt_id']);
            if (! $firstDebt) {
                $firstDebt = $debt;
            } else {
                if ($debt->type !== $firstDebt->type) {
                    return Redirect::back()->with('error', 'Semua hutang/piutang yang dipilih harus memiliki tipe yang sama.');
                }
            }
        }

        DB::transaction(function () use ($validated, $internalDebtPayment, $totalAmount, $firstDebt) {
            $internalDebtPayment->update([
                'type' => $firstDebt?->type ?? $internalDebtPayment->type,
                'branch_id' => $validated['branch_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'payment_date' => $validated['payment_date'],
                'account_id' => $validated['account_id'],
                'payment_method' => $validated['payment_method'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'counterparty_account_id' => $validated['counterparty_account_id'] ?? null,
                'instrument_date' => $validated['instrument_date'] ?? null,
                'withdrawal_date' => $validated['withdrawal_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'amount' => $totalAmount,
                'primary_currency_amount' => $totalAmount * $validated['exchange_rate'],
            ]);

            $existingIds = $internalDebtPayment->details->pluck('id')->toArray();
            $keptIds = [];

            foreach ($validated['details'] as $detail) {
                $detailData = [
                    'internal_debt_id' => $detail['internal_debt_id'],
                    'amount' => $detail['amount'],
                    'primary_currency_amount' => $detail['amount'] * $validated['exchange_rate'],
                    'notes' => $detail['notes'] ?? null,
                ];
                if (! empty($detail['id']) && in_array($detail['id'], $existingIds)) {
                    $internalDebtPayment->details()->find($detail['id'])->update($detailData);
                    $keptIds[] = $detail['id'];
                } else {
                    $new = $internalDebtPayment->details()->create($detailData);
                    $keptIds[] = $new->id;
                }
            }

            $toDelete = array_diff($existingIds, $keptIds);
            if (! empty($toDelete)) {
                $internalDebtPayment->details()->whereIn('id', $toDelete)->delete();
            }
        });

        return redirect()->route('internal-debt-payments.show', $internalDebtPayment->id)->with('success', 'Pembayaran Internal berhasil diubah.');
    }

    public function destroy(Request $request, InternalDebtPayment $internalDebtPayment)
    {
        if ($internalDebtPayment->status === PaymentStatus::APPROVED) {
            return Redirect::back()->with('error', 'Tidak dapat menghapus pembayaran yang sudah disetujui.');
        }
        DB::transaction(function () use ($internalDebtPayment) {
            InternalDebtPaymentDeleted::dispatch($internalDebtPayment->loadMissing(['details.internalDebt']));
            $internalDebtPayment->details()->delete();
            $internalDebtPayment->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('internal-debt-payments.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)->with('success', 'Pembayaran Internal berhasil dihapus.');
        }

        return redirect()->route('internal-debt-payments.index')->with('success', 'Pembayaran Internal berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:internal_debt_payments,id',
        ]);
        DB::transaction(function () use ($validated) {
            $items = InternalDebtPayment::whereIn('id', $validated['ids'])->get();

            foreach ($items as $item) {
                if ($item->status === PaymentStatus::APPROVED) {
                    return Redirect::back()->with('error', 'Tidak dapat menghapus pembayaran yang sudah disetujui.');
                }
            }

            foreach ($items as $item) {
                // Dispatch deletion event to ensure any journals get cleaned if present
                InternalDebtPaymentDeleted::dispatch($item->loadMissing(['details.internalDebt']));
                $item->details()->delete();
                $item->delete();
            }
        });

        return redirect()->route('internal-debt-payments.index')->with('success', 'Pembayaran Internal terpilih berhasil dihapus.');
    }

    public function approve(Request $request, InternalDebtPayment $internalDebtPayment)
    {
        if ($internalDebtPayment->status !== PaymentStatus::PENDING) {
            return redirect()->back()->with('error', 'Status pembayaran tidak dapat diubah.');
        }
        if ($internalDebtPayment->payment_method !== PaymentMethod::TRANSFER) {
            $validated = $request->validate([
                'counterparty_account_id' => 'required|exists:accounts,id',
            ]);
            $internalDebtPayment->update([
                'counterparty_account_id' => $validated['counterparty_account_id'],
                'status' => 'approved',
            ]);
        } else {
            $internalDebtPayment->update(['status' => 'approved']);
        }
        InternalDebtPaymentApproved::dispatch($internalDebtPayment->loadMissing(['details.internalDebt']));

        return redirect()->back()->with('success', 'Pembayaran Internal disetujui.');
    }

    public function reject(InternalDebtPayment $internalDebtPayment)
    {
        if ($internalDebtPayment->status !== PaymentStatus::PENDING) {
            return redirect()->back()->with('error', 'Status pembayaran tidak dapat diubah.');
        }
        $internalDebtPayment->update(['status' => 'rejected']);

        return redirect()->back()->with('success', 'Pembayaran Internal ditolak.');
    }

    private function getFiltered(Request $request)
    {
        $filters = $request->all() ?: Session::get('internal_debt_payments.index_filters', []);
        $query = InternalDebtPayment::with(['branch.branchGroup.company', 'currency', 'details.internalDebt']);

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(number)'), 'like', "%$search%")
                    ->orWhere(DB::raw('lower(reference_number)'), 'like', "%$search%")
                    ->orWhere(DB::raw('lower(notes)'), 'like', "%$search%");
            });
        }
        if (! empty($filters['company_id'])) {
            $query->whereHas('branch.branchGroup', fn ($q) => $q->whereIn('company_id', $filters['company_id']));
        }
        if (! empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }
        if (! empty($filters['from_date'])) {
            $query->whereDate('payment_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('payment_date', '<=', $filters['to_date']);
        }
        $sort = $filters['sort'] ?? 'payment_date';
        $order = $filters['order'] ?? 'desc';
        if ($sort === 'branch.name') {
            $query->join('branches', 'internal_debt_payments.branch_id', '=', 'branches.id')
                ->orderBy('branches.name', $order)
                ->select('internal_debt_payments.*');
        } else {
            $query->orderBy($sort, $order);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $items = $this->getFiltered($request);

        return Excel::download(new InternalDebtPaymentsExport($items, 'Pembayaran/Penerimaan Internal'), 'internal-debt-payments.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $items = $this->getFiltered($request);

        return Excel::download(new InternalDebtPaymentsExport($items, 'Pembayaran/Penerimaan Internal'), 'internal-debt-payments.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        return redirect()->back()->with('error', 'Ekspor PDF belum diimplementasikan.');
    }

    private function getUnpaidDebts($companyId, $branchId, $counterpartyCompanyId, $counterpartyBranchId, $currencyId, $includePaymentId = null)
    {
        $debts = InternalDebt::with(['currency', 'branch', 'counterpartyBranch'])
            ->where('branch_id', $branchId)
            ->where('currency_id', $currencyId)
            ->whereHas('branch.branchGroup', fn ($q) => $q->where('company_id', $companyId))
            ->whereHas('counterpartyBranch.branchGroup', fn ($q) => $q->where('company_id', $counterpartyCompanyId))
            ->where('counterparty_branch_id', $counterpartyBranchId)
            ->orderBy('due_date', 'asc')
            ->orderBy('issue_date', 'asc')
            ->get();

        $paidSumsQuery = DB::table('internal_debt_payment_details')
            ->join('internal_debt_payments', 'internal_debt_payments.id', '=', 'internal_debt_payment_details.internal_debt_payment_id')
            ->whereNull('internal_debt_payment_details.deleted_at')
            ->whereNull('internal_debt_payments.deleted_at');
        if ($includePaymentId) {
            $paidSumsQuery->where('internal_debt_payment_details.internal_debt_payment_id', '!=', $includePaymentId);
        }
        $paidSums = $paidSumsQuery
            ->groupBy('internal_debt_id')
            ->pluck(DB::raw('SUM(internal_debt_payment_details.amount) as total'), 'internal_debt_id');

        return $debts->map(function ($d) use ($paidSums) {
            $paid = (float) ($paidSums[$d->id] ?? 0);
            $remaining = max(0, ((float) $d->amount) - $paid);

            return [
                'id' => $d->id,
                'number' => $d->number,
                'issue_date' => $d->issue_date,
                'due_date' => $d->due_date,
                'currency_id' => $d->currency_id,
                'amount' => (float) $d->amount,
                'paid' => $paid,
                'remaining_amount' => $remaining,
            ];
        })->filter(fn ($row) => $row['remaining_amount'] > 0)->values();
    }
}
