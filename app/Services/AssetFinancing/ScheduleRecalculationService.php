<?php

namespace App\Services\AssetFinancing;

use App\Models\AssetFinancingAgreement;
use App\Models\AssetFinancingSchedule;
use Carbon\Carbon;

class ScheduleRecalculationService
{
    /**
     * Recalculate schedules starting from the period AFTER the provided schedule.
     * This is used when a schedule is paid, potentially with an overpayment.
     */
    public function recalculateAfter(AssetFinancingAgreement $agreement, AssetFinancingSchedule $currentSchedule)
    {
        // 1. Get the total principal paid up to and including the current schedule.
        $totalPrincipalPaid = $agreement->schedules()
            ->where('payment_date', '<=', $currentSchedule->payment_date)
            ->sum('paid_principal_amount');

        // 2. Calculate new outstanding principal.
        $newOutstandingPrincipal = $agreement->total_amount - $totalPrincipalPaid;

        // 3. If loan is paid off.
        if ($newOutstandingPrincipal <= 0) {
            $agreement->schedules()->where('payment_date', '>', $currentSchedule->payment_date)->delete();
            $agreement->status = 'closed';
            $agreement->save();
            return;
        }

        // 4. Delete future unpaid schedules.
        $agreement->schedules()
            ->where('payment_date', '>', $currentSchedule->payment_date)
            ->delete();

        // 5. Regenerate schedules for the remaining term.
        $scheduleService = new ScheduleService();
        $nextPaymentNumber = $currentSchedule->payment_number + 1;
        $nextPaymentDate = $scheduleService->getPaymentDate(Carbon::parse($agreement->start_date), $nextPaymentNumber, $agreement->payment_frequency);

        $scheduleService->generate(
            $agreement,
            $nextPaymentDate,
            $agreement->end_date,
            $newOutstandingPrincipal,
            $nextPaymentNumber
        );
    }

    /**
     * Recalculate schedules starting from a specific schedule's period.
     * This is used when a payment is deleted or modified, requiring a reset from that point.
     */
    public function recalculateFrom(AssetFinancingAgreement $agreement, AssetFinancingSchedule $scheduleToStartFrom)
    {
        // 1. Get total principal paid *before* the schedule we are starting from.
        $totalPrincipalPaid = $agreement->schedules()
            ->where('payment_date', '<', $scheduleToStartFrom->payment_date)
            ->sum('paid_principal_amount');

        // 2. Calculate the new outstanding principal.
        $newOutstandingPrincipal = $agreement->total_amount - $totalPrincipalPaid;

        // 3. If loan is paid off
        if ($newOutstandingPrincipal <= 0) {
            $agreement->schedules()->where('payment_date', '>=', $scheduleToStartFrom->payment_date)->delete();
            $agreement->status = 'closed';
            $agreement->save();
            return;
        }

        // 4. Delete schedules from the starting point onwards.
        $agreement->schedules()
            ->where('payment_date', '>=', $scheduleToStartFrom->payment_date)
            ->delete();

        // 5. Regenerate schedules.
        $scheduleService = new ScheduleService();
        $startPaymentNumber = $scheduleToStartFrom->payment_number;
        $startPaymentDate = Carbon::parse($scheduleToStartFrom->payment_date);

        $scheduleService->generate(
            $agreement,
            $startPaymentDate,
            $agreement->end_date,
            $newOutstandingPrincipal,
            $startPaymentNumber
        );
    }
} 