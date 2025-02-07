<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetLease;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AssetLeaseController extends Controller
{
    public function show(Asset $asset)
    {
        $lease = $asset->lease()->with('payments')->first();
        
        if (!$lease) {
            return redirect()->route('asset-leases.create', $asset->id);
        }

        return Inertia::render('AssetLeases/Show', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'lease' => $lease,
        ]);
    }

    public function create(Asset $asset)
    {
        if ($asset->lease()->exists()) {
            return redirect()->route('asset-leases.show', $asset->id)
                ->with('error', 'Asset already has a lease.');
        }

        return Inertia::render('AssetLeases/Create', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'lease_type' => 'required|in:operating,finance',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'lease_amount' => 'required|numeric|min:0',
            'payment_frequency' => 'required|in:monthly,quarterly,annually',
            'payment_amount' => 'required|numeric|min:0',
            'prepaid_amount' => 'required|numeric|min:0',
            'total_obligation' => 'required|numeric|min:0',
            'interest_rate' => 'required_if:lease_type,finance|nullable|numeric|min:0',
            'has_escalation_clause' => 'boolean',
            'escalation_terms' => 'required_if:has_escalation_clause,true|nullable|string',
            'lease_terms' => 'nullable|string',
        ]);

        DB::transaction(function () use ($asset, $validated) {
            $lease = $asset->lease()->create($validated);

            // Create payment schedule
            $this->createPaymentSchedule($lease);

            // Update asset acquisition type
            $asset->update([
                'acquisition_type' => 'lease',
                'current_value' => $validated['total_obligation'],
            ]);
        });

        return redirect()->route('asset-leases.show', $asset->id)
            ->with('success', 'Lease created successfully.');
    }

    public function edit(Asset $asset)
    {
        $lease = $asset->lease()->firstOrFail();

        return Inertia::render('AssetLeases/Edit', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'lease' => $lease,
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        $lease = $asset->lease()->firstOrFail();

        $validated = $request->validate([
            'lease_type' => 'required|in:operating,finance',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'lease_amount' => 'required|numeric|min:0',
            'payment_frequency' => 'required|in:monthly,quarterly,annually',
            'payment_amount' => 'required|numeric|min:0',
            'prepaid_amount' => 'required|numeric|min:0',
            'total_obligation' => 'required|numeric|min:0',
            'interest_rate' => 'required_if:lease_type,finance|nullable|numeric|min:0',
            'has_escalation_clause' => 'boolean',
            'escalation_terms' => 'required_if:has_escalation_clause,true|nullable|string',
            'lease_terms' => 'nullable|string',
        ]);

        DB::transaction(function () use ($lease, $validated, $asset) {
            $lease->update($validated);

            // Update payment schedule if needed
            if ($this->shouldUpdatePaymentSchedule($lease, $validated)) {
                $lease->payments()->delete();
                $this->createPaymentSchedule($lease);
            }

            // Update asset current value
            $asset->update([
                'current_value' => $validated['total_obligation'],
            ]);
        });

        return redirect()->route('asset-leases.show', $asset->id)
            ->with('success', 'Lease updated successfully.');
    }

    public function destroy(Asset $asset)
    {
        $lease = $asset->lease()->firstOrFail();

        DB::transaction(function () use ($lease, $asset) {
            $lease->payments()->delete();
            $lease->delete();

            $asset->update([
                'acquisition_type' => 'purchase',
                'current_value' => $asset->calculateDepreciation(),
            ]);
        });

        return redirect()->route('assets.show', $asset->id)
            ->with('success', 'Lease deleted successfully.');
    }

    protected function createPaymentSchedule(AssetLease $lease)
    {
        $startDate = $lease->start_date;
        $endDate = $lease->end_date;
        $frequency = $lease->payment_frequency;
        $amount = $lease->payment_amount;

        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $paymentData = [
                'due_date' => $currentDate->format('Y-m-d'),
                'amount' => $amount,
                'status' => 'pending',
            ];

            // Calculate interest and principal portions for finance leases
            if ($lease->lease_type === 'finance') {
                $this->calculateFinanceLeasePortions($lease, $paymentData);
            }

            $lease->payments()->create($paymentData);

            // Increment date based on frequency
            switch ($frequency) {
                case 'monthly':
                    $currentDate->addMonth();
                    break;
                case 'quarterly':
                    $currentDate->addMonths(3);
                    break;
                case 'annually':
                    $currentDate->addYear();
                    break;
            }
        }
    }

    protected function calculateFinanceLeasePortions(AssetLease $lease, array &$paymentData)
    {
        $monthlyRate = $lease->interest_rate / 12 / 100;
        $remainingObligation = $lease->getRemainingObligationAttribute();
        
        $interestPortion = $remainingObligation * $monthlyRate;
        $principalPortion = $paymentData['amount'] - $interestPortion;

        $paymentData['interest_portion'] = $interestPortion;
        $paymentData['principal_portion'] = $principalPortion;
    }

    protected function shouldUpdatePaymentSchedule(AssetLease $lease, array $validated): bool
    {
        return $lease->payment_frequency !== $validated['payment_frequency'] ||
            $lease->payment_amount != $validated['payment_amount'] ||
            $lease->start_date->format('Y-m-d') !== $validated['start_date'] ||
            $lease->end_date->format('Y-m-d') !== $validated['end_date'];
    }
} 