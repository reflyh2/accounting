<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetFinancingPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use App\Models\Account;

class AssetFinancingPaymentController extends Controller
{
    public function index(Request $request, Asset $asset)
    {
        $filters = $request->all() ?: Session::get('asset_financing_payments.index_filters', []);
        Session::put('asset_financing_payments.index_filters', $filters);

        $query = $asset->financingPayments();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('notes', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('due_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('due_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'due_date';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $payments = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('AssetFinancingPayments/Index', [
            'asset' => $asset->load(['branch.branchGroup.company', 'financingPayments']),
            'accounts' => Account::where('type', 'kas_bank')
                ->where('is_parent', false)
                ->orderBy('code')
                ->get(),
            'payments' => $payments,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Asset $asset)
    {
        return Inertia::render('AssetFinancingPayments/Create', [
            'asset' => $asset->load('branch.branchGroup.company'),
            'accounts' => Account::where('type', 'kas_bank')
                ->where('is_parent', false)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'due_date' => 'required|date',
            'payment_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0',
            'principal_portion' => 'required|numeric|min:0',
            'interest_portion' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'credited_account_id' => 'required_unless:payment_date,null|nullable|exists:accounts,id',
        ]);

        $status = $validated['payment_date'] ? 'paid' : 'pending';
        if ($validated['payment_date'] && strtotime($validated['payment_date']) > strtotime($validated['due_date'])) {
            $status = 'overdue';
        }

        $payment = $asset->financingPayments()->create([
            'due_date' => $validated['due_date'],
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'principal_portion' => $validated['principal_portion'],
            'interest_portion' => $validated['interest_portion'],
            'notes' => $validated['notes'],
            'status' => $status,
            'credited_account_id' => $validated['credited_account_id'],
        ]);

        return redirect()
            ->route('asset-financing-payments.index', $asset->id)
            ->with('success', 'Pembayaran berhasil ditambahkan.');
    }

    public function show(AssetFinancingPayment $assetFinancingPayment)
    {
        return Inertia::render('AssetFinancingPayments/Show', [
            'asset' => $assetFinancingPayment->asset->load(['branch.branchGroup.company']),
            'payment' => $assetFinancingPayment,
        ]);
    }

    public function edit(AssetFinancingPayment $assetFinancingPayment)
    {
        return Inertia::render('AssetFinancingPayments/Edit', [
            'asset' => $assetFinancingPayment->asset->load('branch.branchGroup.company'),
            'payment' => $assetFinancingPayment,
            'accounts' => Account::where('type', 'kas_bank')
                ->where('is_parent', false)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(Request $request, AssetFinancingPayment $assetFinancingPayment)
    {
        $validated = $request->validate([
            'due_date' => 'required|date',
            'payment_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0',
            'principal_portion' => 'required|numeric|min:0',
            'interest_portion' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'credited_account_id' => 'required_unless:payment_date,null|nullable|exists:accounts,id',
        ]);

        $status = $validated['payment_date'] ? 'paid' : 'pending';
        if ($validated['payment_date'] && strtotime($validated['payment_date']) > strtotime($validated['due_date'])) {
            $status = 'overdue';
        }

        $assetFinancingPayment->update([
            'due_date' => $validated['due_date'],
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'principal_portion' => $validated['principal_portion'],
            'interest_portion' => $validated['interest_portion'],
            'notes' => $validated['notes'],
            'status' => $status,
            'credited_account_id' => $validated['credited_account_id'],
        ]);

        return redirect()
            ->route('asset-financing-payments.index', $assetFinancingPayment->asset->id)
            ->with('success', 'Pembayaran berhasil diperbarui.');
    }

    public function destroy(AssetFinancingPayment $assetFinancingPayment)
    {
        $assetFinancingPayment->delete();

        return redirect()->back()
            ->with('success', 'Pembayaran berhasil dihapus.');
    }

    public function complete(Request $request, AssetFinancingPayment $assetFinancingPayment)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'credited_account_id' => 'required_unless:payment_date,null|nullable|exists:accounts,id',
            'principal_portion' => 'required|numeric|min:0',
            'interest_portion' => 'required|numeric|min:0',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'paid';
        $assetFinancingPayment->update($validated);

        return redirect()->back()
            ->with('success', 'Pembayaran pembiayaan berhasil diselesaikan.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_financing_payments,id'
        ]);

        $assetId = AssetFinancingPayment::find($validated['ids'][0])->asset_id;

        DB::transaction(function () use ($validated) {
            foreach ($validated['ids'] as $id) {
                $payment = AssetFinancingPayment::find($id);
                $payment->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-financing-payments.index', $assetId) . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pembayaran pembiayaan yang dipilih berhasil dihapus.');
        }

        return redirect()->back()
            ->with('success', 'Pembayaran pembiayaan yang dipilih berhasil dihapus.');
    }
} 