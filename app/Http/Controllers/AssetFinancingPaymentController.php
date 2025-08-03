<?php

namespace App\Http\Controllers;

use App\Models\AssetFinancingAgreement;
use App\Models\AssetFinancingPayment;
use App\Models\AssetFinancingSchedule;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Services\AssetFinancing\ScheduleRecalculationService;
use App\Models\Currency;

class AssetFinancingPaymentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_financing_payments.index_filters', []);
        Session::put('asset_financing_payments.index_filters', $filters);

        $query = AssetFinancingPayment::with(['creditor', 'branch', 'currency']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(reference)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('creditor', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
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

        if (!empty($filters['from_date'])) {
            $query->whereDate('payment_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('payment_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['creditor_id'])) {
            $query->whereIn('creditor_id', $filters['creditor_id']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'payment_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $payments = $query->paginate($perPage)->onEachSide(0)->withQueryString();
        
        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
                $query->whereIn('company_id', $filters['company_id']);
            })->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        return Inertia::render('AssetFinancingPayments/Index', [
            'payments' => $payments,
            'filters' => $filters,
            'companies' => $companies,
            'branches' => $branches,
            'creditors' => Partner::whereHas('roles', function ($query) {
                $query->where('role', 'creditor');
            })->orderBy('name')->get(),
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('AssetFinancingPayments/Create', [
            'companies' => Company::orderBy('name')->get(),
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name')->get(),
            'currencies' => fn() => Currency::whereHas('companyRates', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->with(['companyRates' => function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            }])->orderBy('code', 'asc')->get(),
            'creditors' => Partner::whereHas('roles', function ($query) {
                $query->where('role', 'creditor');
            })->orderBy('name')->get(),
            'agreements' => fn() => AssetFinancingAgreement::with('assetInvoice.assets')
                ->where('status', 'active')
                ->where('creditor_id', $request->input('creditor_id'))
                ->where('branch_id', $request->input('branch_id'))
                ->where('currency_id', $request->input('currency_id'))
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'payment_date' => 'required|date',
            'creditor_id' => 'required|exists:partners,id',
            'reference' => 'nullable|string|max:255',
            'total_paid_amount' => 'required|numeric|min:0',
            'principal_amount' => 'required|numeric|min:0',
            'interest_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0',
            'allocations' => 'required|array|min:1',
            'allocations.*.asset_financing_agreement_id' => 'required|exists:asset_financing_agreements,id',
            'allocations.*.allocated_amount' => 'required|numeric|min:0',
            'allocations.*.principal_amount' => 'required|numeric|min:0',
            'allocations.*.interest_amount' => 'required|numeric|min:0',
            'allocations.*.asset_financing_schedule_id' => 'nullable|exists:asset_financing_schedules,id',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $payment = AssetFinancingPayment::create([
                'branch_id' => $validated['branch_id'],
                'payment_date' => $validated['payment_date'],
                'creditor_id' => $validated['creditor_id'],
                'reference' => $validated['reference'],
                'total_paid_amount' => $validated['total_paid_amount'],
                'principal_amount' => $validated['principal_amount'],
                'interest_amount' => $validated['interest_amount'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'created_by' => $request->user()->global_id,
            ]);

            $affectedAgreements = [];

            foreach ($validated['allocations'] as $allocationData) {
                $allocation = $payment->allocations()->create($allocationData);

                if ($allocation->asset_financing_schedule_id) {
                    $schedule = AssetFinancingSchedule::with('assetFinancingAgreement')->find($allocation->asset_financing_schedule_id);
                    if ($schedule) {
                        $schedule->paid_principal_amount += $allocation->principal_amount;
                        $schedule->paid_interest_amount += $allocation->interest_amount;
                        $schedule->paid_date = $payment->payment_date;
                        $delta = 0.01;

                        if (floatsGreaterThanOrEqual($schedule->paid_principal_amount, $schedule->principal_amount) && floatsGreaterThanOrEqual($schedule->paid_interest_amount, $schedule->interest_amount)) {
                            $schedule->status = 'paid';
                        } else {
                            $schedule->status = 'partial';
                        }
                        $schedule->save();

                        if ($schedule->assetFinancingAgreement) {
                            $affectedAgreements[$schedule->assetFinancingAgreement->id] = $schedule->assetFinancingAgreement;
                        }
                    }
                }
            }

            // Recalculate schedules for each affected agreement
            foreach ($affectedAgreements as $agreement) {
                $lastPaidSchedule = $agreement->schedules()
                    ->whereIn('status', ['paid', 'partial'])
                    ->orderBy('payment_date', 'desc')
                    ->first();

                if ($lastPaidSchedule) {
                    (new ScheduleRecalculationService())->recalculateAfter($agreement, $lastPaidSchedule);
                } else {
                    // This case should ideally not happen when creating a payment, but as a fallback
                    (new \App\Services\AssetFinancing\ScheduleService())->generate($agreement);
                }
            }
        });

        return redirect()->route('asset-financing-payments.index')->with('success', 'Pembayaran pembiayaan aset berhasil dibuat.');
    }

    public function show(AssetFinancingPayment $assetFinancingPayment)
    {
        $assetFinancingPayment->load(['creditor', 'branch', 'currency', 'allocations.assetFinancingAgreement.assetInvoice.assets']);
        return Inertia::render('AssetFinancingPayments/Show', [
            'payment' => $assetFinancingPayment,
        ]);
    }

    public function edit(Request $request, AssetFinancingPayment $assetFinancingPayment)
    {
        $assetFinancingPayment->load('allocations');

        $companyId = $assetFinancingPayment->branch->branchGroup->company_id;
        
        if ($request->company_id) {
            $companyId = $request->company_id;
        }

        return Inertia::render('AssetFinancingPayments/Edit', [
            'payment' => $assetFinancingPayment,
            'companies' => Company::orderBy('name')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name')->get(),
            'currencies' => Currency::whereHas('companyRates', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->with(['companyRates' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            }])->orderBy('code', 'asc')->get(),
            'creditors' => Partner::whereHas('roles', function ($query) {
                $query->where('role', 'creditor');
            })->orderBy('name')->get(),
            'agreements' => AssetFinancingAgreement::with('assetInvoice.assets')
                ->where('status', 'active')
                ->where('creditor_id', $assetFinancingPayment->creditor_id)
                ->where('branch_id', $assetFinancingPayment->branch_id)
                ->where('currency_id', $assetFinancingPayment->currency_id)
                ->get(),
        ]);
    }

    public function update(Request $request, AssetFinancingPayment $assetFinancingPayment)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'payment_date' => 'required|date',
            'creditor_id' => 'required|exists:partners,id',
            'reference' => 'nullable|string|max:255',
            'total_paid_amount' => 'required|numeric|min:0',
            'principal_amount' => 'required|numeric|min:0',
            'interest_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'required|numeric|min:0',
            'allocations' => 'required|array|min:1',
            'allocations.*.id' => 'nullable|exists:asset_financing_payment_allocations,id',
            'allocations.*.asset_financing_agreement_id' => 'required|exists:asset_financing_agreements,id',
            'allocations.*.allocated_amount' => 'required|numeric|min:0',
            'allocations.*.principal_amount' => 'required|numeric|min:0',
            'allocations.*.interest_amount' => 'required|numeric|min:0',
            'allocations.*.asset_financing_schedule_id' => 'nullable|exists:asset_financing_schedules,id',
        ]);

        DB::transaction(function () use ($validated, $assetFinancingPayment, $request) {
            $affectedAgreements = [];

            // Revert old allocations and collect affected agreements
            foreach ($assetFinancingPayment->allocations as $oldAllocation) {
                if ($oldAllocation->asset_financing_schedule_id) {
                    $schedule = AssetFinancingSchedule::with('assetFinancingAgreement')->find($oldAllocation->asset_financing_schedule_id);
                    if ($schedule) {
                        $schedule->paid_principal_amount -= $oldAllocation->principal_amount;
                        $schedule->paid_interest_amount -= $oldAllocation->interest_amount;
                        $delta = 0.01;

                        if ($schedule->paid_principal_amount <= 0 && $schedule->paid_interest_amount <= 0) {
                            $schedule->status = 'unpaid';
                            $schedule->paid_date = null;
                        }
                        else if (floatsGreaterThanOrEqual($schedule->paid_principal_amount, $schedule->principal_amount) && floatsGreaterThanOrEqual($schedule->paid_interest_amount, $schedule->interest_amount)) {
                            $schedule->status = 'paid';
                        } else {
                            $schedule->status = 'partial';
                        }
                        $schedule->save();
                        
                        if ($schedule->assetFinancingAgreement) {
                            $affectedAgreements[$schedule->assetFinancingAgreement->id] = $schedule->assetFinancingAgreement;
                        }
                    }
                }
            }

            $assetFinancingPayment->update([
                'branch_id' => $validated['branch_id'],
                'payment_date' => $validated['payment_date'],
                'creditor_id' => $validated['creditor_id'],
                'reference' => $validated['reference'],
                'total_paid_amount' => $validated['total_paid_amount'],
                'principal_amount' => $validated['principal_amount'],
                'interest_amount' => $validated['interest_amount'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'updated_by' => $request->user()->global_id,
            ]);

            $assetFinancingPayment->allocations()->delete();

            // Apply new allocations and collect affected agreements
            foreach ($validated['allocations'] as $allocationData) {
                $allocation = $assetFinancingPayment->allocations()->create($allocationData);

                if ($allocation->asset_financing_schedule_id) {
                    $schedule = AssetFinancingSchedule::with('assetFinancingAgreement')->find($allocation->asset_financing_schedule_id);
                    if ($schedule) {
                        $schedule->paid_principal_amount += $allocation->principal_amount;
                        $schedule->paid_interest_amount += $allocation->interest_amount;
                        $schedule->paid_date = $assetFinancingPayment->payment_date;
                        $delta = 0.01;

                        if (floatsGreaterThanOrEqual($schedule->paid_principal_amount, $schedule->principal_amount) && floatsGreaterThanOrEqual($schedule->paid_interest_amount, $schedule->interest_amount)) {
                            $schedule->status = 'paid';
                        } else {
                            $schedule->status = 'partial';
                        }
                        $schedule->save();
                        
                        if ($schedule->assetFinancingAgreement) {
                            $affectedAgreements[$schedule->assetFinancingAgreement->id] = $schedule->assetFinancingAgreement;
                        }
                    }
                }
            }
            
            // Recalculate schedules for each affected agreement
            foreach ($affectedAgreements as $agreement) {
                $lastPaidSchedule = $agreement->schedules()
                    ->whereIn('status', ['paid', 'partial'])
                    ->orderBy('payment_date', 'desc')
                    ->first();

                if ($lastPaidSchedule) {
                    (new ScheduleRecalculationService())->recalculateAfter($agreement, $lastPaidSchedule);
                } else {
                    (new \App\Services\AssetFinancing\ScheduleService())->generate($agreement);
                }
            }
        });

        return redirect()->route('asset-financing-payments.edit', $assetFinancingPayment->id)->with('success', 'Pembayaran pembiayaan aset berhasil diubah.');
    }

    public function destroy(AssetFinancingPayment $assetFinancingPayment)
    {
        DB::transaction(function () use ($assetFinancingPayment) {
            $affectedAgreements = [];

            // Revert old allocations and collect affected agreements
            foreach ($assetFinancingPayment->allocations as $oldAllocation) {
                if ($oldAllocation->asset_financing_schedule_id) {
                    $schedule = AssetFinancingSchedule::with('assetFinancingAgreement')->find($oldAllocation->asset_financing_schedule_id);

                    if ($schedule) {
                        $schedule->paid_principal_amount -= $oldAllocation->principal_amount;
                        $schedule->paid_interest_amount -= $oldAllocation->interest_amount;
                        $delta = 0.01;

                        if ($schedule->paid_principal_amount <= 0 && $schedule->paid_interest_amount <= 0) {
                            $schedule->status = 'unpaid';
                            $schedule->paid_date = null;
                        }
                        else if (floatsGreaterThanOrEqual($schedule->paid_principal_amount, $schedule->principal_amount) && floatsGreaterThanOrEqual($schedule->paid_interest_amount, $schedule->interest_amount)) {
                            $schedule->status = 'paid';
                        }
                        else {
                            $schedule->status = 'partial';
                        }
                        $schedule->save();
                        
                        if ($schedule->assetFinancingAgreement) {
                            $affectedAgreements[$schedule->assetFinancingAgreement->id] = $schedule->assetFinancingAgreement;
                        }
                    }
                }
            }
            
            $assetFinancingPayment->allocations()->delete();
            $assetFinancingPayment->delete();

            // Recalculate schedules for each affected agreement
            foreach ($affectedAgreements as $agreement) {
                $lastPaidSchedule = $agreement->schedules()
                    ->whereIn('status', ['paid', 'partial'])
                    ->orderBy('payment_date', 'desc')
                    ->first();

                if ($lastPaidSchedule) {
                    (new ScheduleRecalculationService())->recalculateAfter($agreement, $lastPaidSchedule);
                } else {
                    (new \App\Services\AssetFinancing\ScheduleService())->generate($agreement);
                }
            }
        });

        return redirect()->route('asset-financing-payments.index')->with('success', 'Pembayaran pembiayaan aset berhasil dihapus.');
    }
} 