<?php

namespace App\Services\AssetFinancing;

use App\Models\AssetFinancingAgreement;
use Carbon\Carbon;

class ScheduleService
{
    public function generate(AssetFinancingAgreement $agreement, $startDate = null, $endDate = null, $startingPrincipal = null, $startPaymentNumber = 1)
    {
        // Clear existing unpaid schedules from the start date if provided
        $query = $agreement->schedules()->where('status', 'unpaid');
        if ($startDate) {
            $query->where('payment_date', '>=', $startDate);
        }
        $query->delete();

        $principal = $startingPrincipal ?? $agreement->total_amount;
        $scheduleStartDate = $startDate ? Carbon::parse($startDate) : Carbon::parse($agreement->start_date);
        $scheduleEndDate = $endDate ? Carbon::parse($endDate) : Carbon::parse($agreement->end_date);

        switch ($agreement->interest_calculation_method) {
            case 'annuity':
                $this->generateAnnuitySchedule($agreement, $scheduleStartDate, $scheduleEndDate, $principal, $startPaymentNumber);
                break;
            case 'straight_line':
                $this->generateStraightLineSchedule($agreement, $scheduleStartDate, $scheduleEndDate, $principal, $startPaymentNumber);
                break;
            case 'flat_rate':
                $this->generateFlatRateSchedule($agreement, $scheduleStartDate, $scheduleEndDate, $principal, $startPaymentNumber);
                break;
            case 'sum_of_digits':
                $this->generateSumOfDigitsSchedule($agreement, $scheduleStartDate, $scheduleEndDate, $principal, $startPaymentNumber);
                break;
            case 'interest_only':
                $this->generateInterestOnlySchedule($agreement, $scheduleStartDate, $scheduleEndDate, $principal, $startPaymentNumber);
                break;
            case 'simple_interest_daily_accrual':
                $this->generateSimpleInterestDailyAccrualSchedule($agreement, $scheduleStartDate, $scheduleEndDate, $principal, $startPaymentNumber);
                break;
        }
    }

    private function generateAnnuitySchedule(AssetFinancingAgreement $agreement, Carbon $startDate, Carbon $endDate, float $principal, int $startPaymentNumber)
    {
        $interestRate = $agreement->interest_rate / 100;
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);

        if ($numberOfPayments <= 0) {
            return;
        }

        $periodicInterestRate = $interestRate / 12; // Assuming monthly payments for now
        $annuity = $principal * ($periodicInterestRate * pow(1 + $periodicInterestRate, $numberOfPayments)) / (pow(1 + $periodicInterestRate, $numberOfPayments) - 1);

        $remainingPrincipal = $principal;

        for ($i = 0; $i < $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i + 1, $agreement->payment_frequency);
            $interestAmount = $remainingPrincipal * $periodicInterestRate;
            $principalAmount = $annuity - $interestAmount;
            $remainingPrincipal -= $principalAmount;

            $agreement->schedules()->create([
                'payment_number' => $startPaymentNumber + $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestAmount,
                'total_payment' => $annuity,
            ]);
        }
    }

    private function generateStraightLineSchedule(AssetFinancingAgreement $agreement, Carbon $startDate, Carbon $endDate, float $principal, int $startPaymentNumber)
    {
        $interestRate = $agreement->interest_rate / 100;
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);

        if ($numberOfPayments <= 0) {
            return;
        }

        $principalPerPayment = $principal / $numberOfPayments;

        for ($i = 0; $i < $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i + 1, $agreement->payment_frequency);
            $interestAmount = ($principal - ($i * $principalPerPayment)) * $interestRate / 12; // Assuming monthly interest for now
            $totalPayment = $principalPerPayment + $interestAmount;

            $agreement->schedules()->create([
                'payment_number' => $startPaymentNumber + $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalPerPayment,
                'interest_amount' => $interestAmount,
                'total_payment' => $totalPayment,
            ]);
        }
    }

    private function generateFlatRateSchedule(AssetFinancingAgreement $agreement, Carbon $startDate, Carbon $endDate, float $principal, int $startPaymentNumber)
    {
        $interestRate = $agreement->interest_rate / 100;
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);
        
        $originalNumberOfPayments = $this->getNumberOfPayments(Carbon::parse($agreement->start_date), Carbon::parse($agreement->end_date), $agreement->payment_frequency);
        $originalNumberOfYears = Carbon::parse($agreement->start_date)->diffInYears(Carbon::parse($agreement->end_date));

        if ($numberOfPayments <= 0) {
            return;
        }

        $totalInterest = $agreement->total_amount * $interestRate * $originalNumberOfYears;
        $interestPerPayment = $totalInterest / $originalNumberOfPayments;
        $principalPerPayment = $principal / $numberOfPayments;
        $totalPayment = $principalPerPayment + $interestPerPayment;

        for ($i = 0; $i < $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i + 1, $agreement->payment_frequency);

            $agreement->schedules()->create([
                'payment_number' => $startPaymentNumber + $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalPerPayment,
                'interest_amount' => $interestPerPayment,
                'total_payment' => $totalPayment,
            ]);
        }
    }

    private function generateSumOfDigitsSchedule(AssetFinancingAgreement $agreement, Carbon $startDate, Carbon $endDate, float $principal, int $startPaymentNumber)
    {
        $interestRate = $agreement->interest_rate / 100;
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);
        
        $originalNumberOfPayments = $this->getNumberOfPayments(Carbon::parse($agreement->start_date), Carbon::parse($agreement->end_date), $agreement->payment_frequency);
        $originalNumberOfYears = Carbon::parse($agreement->start_date)->diffInYears(Carbon::parse($agreement->end_date));

        if ($numberOfPayments <= 0) {
            return;
        }

        $totalInterest = $agreement->total_amount * $interestRate * $originalNumberOfYears;
        $sumOfDigits = ($originalNumberOfPayments * ($originalNumberOfPayments + 1)) / 2;
        $principalPerPayment = $principal / $numberOfPayments;

        for ($i = 0; $i < $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i + 1, $agreement->payment_frequency);
            $interestAmount = $totalInterest * (($originalNumberOfPayments - ($startPaymentNumber + $i) + 1) / $sumOfDigits);
            $totalPayment = $principalPerPayment + $interestAmount;

            $agreement->schedules()->create([
                'payment_number' => $startPaymentNumber + $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalPerPayment,
                'interest_amount' => $interestAmount,
                'total_payment' => $totalPayment,
            ]);
        }
    }

    private function generateInterestOnlySchedule(AssetFinancingAgreement $agreement, Carbon $startDate, Carbon $endDate, float $principal, int $startPaymentNumber)
    {
        $interestRate = $agreement->interest_rate / 100;
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);

        if ($numberOfPayments <= 0) {
            return;
        }

        $interestPerPayment = $principal * $interestRate / 12; // Assuming monthly interest for now

        for ($i = 0; $i < $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i + 1, $agreement->payment_frequency);
            $principalAmount = ($i === ($numberOfPayments - 1)) ? $principal : 0;
            $totalPayment = $interestPerPayment + $principalAmount;

            $agreement->schedules()->create([
                'payment_number' => $startPaymentNumber + $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestPerPayment,
                'total_payment' => $totalPayment,
            ]);
        }
    }

    private function generateSimpleInterestDailyAccrualSchedule(AssetFinancingAgreement $agreement, Carbon $startDate, Carbon $endDate, float $principal, int $startPaymentNumber)
    {
        $interestRate = $agreement->interest_rate / 100;
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);

        if ($numberOfPayments <= 0) {
            return;
        }

        $dailyInterestRate = $interestRate / 365;
        $principalPerPayment = $principal / $numberOfPayments;
        $remainingPrincipal = $principal;

        for ($i = 0; $i < $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i + 1, $agreement->payment_frequency);
            $previousPaymentDate = $this->getPaymentDate($startDate, $i, $agreement->payment_frequency);
            $days = $previousPaymentDate->diffInDays($paymentDate);
            $interestAmount = $remainingPrincipal * $dailyInterestRate * $days;
            $totalPayment = $principalPerPayment + $interestAmount;
            $remainingPrincipal -= $principalPerPayment;

            $agreement->schedules()->create([
                'payment_number' => $startPaymentNumber + $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalPerPayment,
                'interest_amount' => $interestAmount,
                'total_payment' => $totalPayment,
            ]);
        }
    }

    private function getNumberOfPayments(Carbon $startDate, Carbon $endDate, string $frequency): int
    {
        switch ($frequency) {
            case 'monthly':
                return $startDate->diffInMonths($endDate) + 1;
            case 'quarterly':
                return (int) floor($startDate->diffInMonths($endDate) / 3) + 1;
            case 'annually':
                return $startDate->diffInYears($endDate) + 1;
            default:
                return 0;
        }
    }

    public function getPaymentDate(Carbon $startDate, int $paymentNumber, string $frequency): Carbon
    {
        $originalDay = $startDate->day;

        switch ($frequency) {
            case 'monthly':
                $nextDate = $startDate->copy()->addMonthsNoOverflow($paymentNumber - 1);
                break;
            case 'quarterly':
                $nextDate = $startDate->copy()->addMonthsNoOverflow(($paymentNumber - 1) * 3);
                break;
            case 'annually':
                $nextDate = $startDate->copy()->addYearsNoOverflow($paymentNumber - 1);
                break;
            default:
                return $startDate;
        }

        // Preserve the original day of the month if possible
        if ($nextDate->day !== $originalDay) {
            $nextDate->day($originalDay);
            // If the original day doesn't exist in the new month (e.g., Feb 31), Carbon defaults to the last day.
            // To be more precise, we can check if the month changed.
            if ($startDate->copy()->addMonths($paymentNumber - 1)->month != $nextDate->month) {
                 // This logic might be complex depending on desired behavior for short months.
                 // For now, setting the day and letting Carbon handle it is a reasonable default.
            }
        }

        return $nextDate;
    }
}
