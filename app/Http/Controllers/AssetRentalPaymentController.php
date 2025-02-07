<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRentalPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Exports\AssetRentalPaymentsExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use App\Models\Account;

class AssetRentalPaymentController extends Controller
{
    public function index(Request $request, Asset $asset)
    {
        $query = $asset->rentalPayments();

        if ($request->has('from_date')) {
            $query->where('payment_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('payment_date', '<=', $request->to_date);
        }

        if ($request->has('status')) {
            $query->whereIn('status', $request->status);
        }

        $sort = $request->sort ?? 'period_start';
        $order = $request->order ?? 'asc';
        $perPage = $request->per_page ?? 10;

        $payments = $query->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        $accounts = Account::where('type', 'kas_bank')
            ->where('is_parent', false)
            ->orderBy('code')
            ->get();

        return Inertia::render('AssetRentalPayments/Index', [
            'asset' => $asset->load('branch.branchGroup.company'),
            'payments' => $payments,
            'accounts' => $accounts,
            'filters' => $request->only(['from_date', 'to_date', 'status']),
            'sort' => $sort,
            'order' => $order,
            'per_page' => $perPage,
        ]);
    }

    public function create(Asset $asset)
    {
        $accounts = Account::where('type', 'kas_bank')
            ->where('is_parent', false)
            ->orderBy('code')
            ->get();

        return Inertia::render('AssetRentalPayments/Create', [
            'asset' => $asset->load('branch.branchGroup.company'),
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'payment_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'credited_account_id' => 'required_with:payment_date|exists:accounts,id',
        ]);

        $validated['status'] = $validated['payment_date'] ? 'paid' : 'pending';
        $payment = $asset->rentalPayments()->create($validated);

        if ($request->create_another) {
            return redirect()->back()
                ->with('success', 'Pembayaran sewa berhasil dicatat.');
        }

        return redirect()->route('asset-rental-payments.index', $asset->id)
            ->with('success', 'Pembayaran sewa berhasil dicatat.');
    }

    public function show(AssetRentalPayment $assetRentalPayment)
    {
        return Inertia::render('AssetRentalPayments/Show', [
            'asset' => $assetRentalPayment->asset->load('branch.branchGroup.company'),
            'payment' => $assetRentalPayment->load('creditedAccount'),
        ]);
    }

    public function edit(AssetRentalPayment $assetRentalPayment)
    {
        $accounts = Account::where('type', 'kas_bank')
            ->where('is_parent', false)
            ->orderBy('code')
            ->get();

        return Inertia::render('AssetRentalPayments/Edit', [
            'asset' => $assetRentalPayment->asset->load('branch.branchGroup.company'),
            'payment' => $assetRentalPayment,
            'accounts' => $accounts,
        ]);
    }

    public function update(Request $request, AssetRentalPayment $assetRentalPayment)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'payment_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'credited_account_id' => 'required_with:payment_date|exists:accounts,id',
        ]);

        $validated['status'] = $validated['payment_date'] ? 'paid' : 'pending';
        $assetRentalPayment->update($validated);

        if ($request->create_another) {
            return redirect()->back()
                ->with('success', 'Pembayaran sewa berhasil diperbarui.');
        }

        return redirect()->route('asset-rental-payments.index', $assetRentalPayment->asset_id)
            ->with('success', 'Pembayaran sewa berhasil diperbarui.');
    }

    public function destroy(AssetRentalPayment $assetRentalPayment)
    {
        $assetRentalPayment->delete();

        return redirect()->route('asset-rental-payments.index', $assetRentalPayment->asset_id)
            ->with('success', 'Pembayaran sewa berhasil dihapus.');
    }

    public function complete(Request $request, AssetRentalPayment $assetRentalPayment)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
            'credited_account_id' => 'required|exists:accounts,id',
        ]);

        $validated['status'] = 'paid';
        $assetRentalPayment->update($validated);

        return redirect()->back()
            ->with('success', 'Pembayaran sewa berhasil diselesaikan.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_rental_payments,id'
        ]);

        $assetId = AssetRentalPayment::find($validated['ids'][0])->asset_id;

        DB::transaction(function () use ($validated) {
            foreach ($validated['ids'] as $id) {
                $payment = AssetRentalPayment::find($id);
                $payment->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-rental-payments.index', $assetId) . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pembayaran sewa yang dipilih berhasil dihapus.');
        }

        return redirect()->back()
            ->with('success', 'Pembayaran sewa yang dipilih berhasil dihapus.');
    }

    public function exportXLSX()
    {
        return Excel::download(new AssetRentalPaymentsExport, 'pembayaran-sewa.xlsx');
    }

    public function exportCSV()
    {
        return Excel::download(new AssetRentalPaymentsExport, 'pembayaran-sewa.csv');
    }

    public function exportPDF()
    {
        $payments = AssetRentalPayment::with('asset.branch.branchGroup.company')
            ->orderBy('payment_date', 'desc')
            ->get();

        $pdf = PDF::loadView('exports.asset-rental-payments', [
            'payments' => $payments
        ]);

        return $pdf->download('pembayaran-sewa.pdf');
    }
} 