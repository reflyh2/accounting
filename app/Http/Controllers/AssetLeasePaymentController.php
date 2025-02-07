<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetLease;
use App\Models\AssetLeasePayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AssetLeasePaymentController extends Controller
{
    public function index(Asset $asset)
    {
        $lease = $asset->lease()->firstOrFail();
        
        return Inertia::render('AssetLeasePayments/Index', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'lease' => $lease->load('payments'),
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $lease = $asset->lease()->firstOrFail();
        
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $payment = $lease->payments()
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->first();

        if (!$payment) {
            return redirect()->back()->with('error', 'No pending payments found.');
        }

        $payment->update([
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'notes' => $validated['notes'],
            'status' => 'paid',
        ]);

        return redirect()->back()->with('success', 'Payment recorded successfully.');
    }

    public function update(Request $request, Asset $asset, AssetLeasePayment $payment)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $payment->update($validated);

        return redirect()->back()->with('success', 'Payment updated successfully.');
    }

    public function destroy(Asset $asset, AssetLeasePayment $payment)
    {
        if ($payment->status === 'paid') {
            $payment->update(['status' => 'pending', 'payment_date' => null]);
            return redirect()->back()->with('success', 'Payment marked as pending.');
        }

        $payment->delete();
        return redirect()->back()->with('success', 'Payment deleted successfully.');
    }
} 