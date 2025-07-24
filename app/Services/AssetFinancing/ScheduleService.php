<?php

namespace App\Services\AssetFinancing;

use App\Models\AssetFinancingAgreement;
use Carbon\Carbon;

class ScheduleService
{
    public function generate(AssetFinancingAgreement $agreement)
    {
        // Clear existing unpaid schedules
        $agreement->schedules()->where('status', 'unpaid')->delete();

        switch ($agreement->interest_calculation_method) {
            case 'annuity':
                $this->generateAnnuitySchedule($agreement);
                break;
            case 'straight_line':
                $this->generateStraightLineSchedule($agreement);
                break;
            case 'flat_rate':
                $this->generateFlatRateSchedule($agreement);
                break;
            case 'sum_of_digits':
                $this->generateSumOfDigitsSchedule($agreement);
                break;
            case 'interest_only':
                $this->generateInterestOnlySchedule($agreement);
                break;
            case 'simple_interest_daily_accrual':
                $this->generateSimpleInterestDailyAccrualSchedule($agreement);
                break;
        }
    }

    private function generateAnnuitySchedule(AssetFinancingAgreement $agreement)
    {
        $principal = $agreement->total_amount;
        $interestRate = $agreement->interest_rate / 100;
        $startDate = Carbon::parse($agreement->start_date);
        $endDate = Carbon::parse($agreement->end_date);
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);

        if ($numberOfPayments <= 0) {
            return;
        }

        $periodicInterestRate = $interestRate / 12; // Assuming monthly payments for now
        $annuity = $principal * ($periodicInterestRate * pow(1 + $periodicInterestRate, $numberOfPayments)) / (pow(1 + $periodicInterestRate, $numberOfPayments) - 1);

        $remainingPrincipal = $principal;

        for ($i = 1; $i <= $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i, $agreement->payment_frequency);
            $interestAmount = $remainingPrincipal * $periodicInterestRate;
            $principalAmount = $annuity - $interestAmount;
            $remainingPrincipal -= $principalAmount;

            $agreement->schedules()->create([
                'payment_number' => $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestAmount,
                'total_payment' => $annuity,
            ]);
        }
    }

    private function generateStraightLineSchedule(AssetFinancingAgreement $agreement)
    {
        $principal = $agreement->total_amount;
        $interestRate = $agreement->interest_rate / 100;
        $startDate = Carbon::parse($agreement->start_date);
        $endDate = Carbon::parse($agreement->end_date);
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);

        if ($numberOfPayments <= 0) {
            return;
        }

        $principalPerPayment = $principal / $numberOfPayments;

        for ($i = 1; $i <= $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i, $agreement->payment_frequency);
            $interestAmount = ($principal - (($i - 1) * $principalPerPayment)) * $interestRate / 12; // Assuming monthly interest for now
            $totalPayment = $principalPerPayment + $interestAmount;

            $agreement->schedules()->create([
                'payment_number' => $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalPerPayment,
                'interest_amount' => $interestAmount,
                'total_payment' => $totalPayment,
            ]);
        }
    }

    private function generateFlatRateSchedule(AssetFinancingAgreement $agreement)
    {
        $principal = $agreement->total_amount;
        $interestRate = $agreement->interest_rate / 100;
        $startDate = Carbon::parse($agreement->start_date);
        $endDate = Carbon::parse($agreement->end_date);
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);
        $numberOfYears = $startDate->diffInYears($endDate);

        if ($numberOfPayments <= 0) {
            return;
        }

        $totalInterest = $principal * $interestRate * $numberOfYears;
        $interestPerPayment = $totalInterest / $numberOfPayments;
        $principalPerPayment = $principal / $numberOfPayments;
        $totalPayment = $principalPerPayment + $interestPerPayment;

        for ($i = 1; $i <= $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i, $agreement->payment_frequency);

            $agreement->schedules()->create([
                'payment_number' => $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalPerPayment,
                'interest_amount' => $interestPerPayment,
                'total_payment' => $totalPayment,
            ]);
        }
    }

    private function generateSumOfDigitsSchedule(AssetFinancingAgreement $agreement)
    {
        $principal = $agreement->total_amount;
        $interestRate = $agreement->interest_rate / 100;
        $startDate = Carbon::parse($agreement->start_date);
        $endDate = Carbon::parse($agreement->end_date);
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);
        $numberOfYears = $startDate->diffInYears($endDate);

        if ($numberOfPayments <= 0) {
            return;
        }

        $totalInterest = $principal * $interestRate * $numberOfYears;
        $sumOfDigits = ($numberOfPayments * ($numberOfPayments + 1)) / 2;
        $principalPerPayment = $principal / $numberOfPayments;

        for ($i = 1; $i <= $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i, $agreement->payment_frequency);
            $interestAmount = $totalInterest * (($numberOfPayments - $i + 1) / $sumOfDigits);
            $totalPayment = $principalPerPayment + $interestAmount;

            $agreement->schedules()->create([
                'payment_number' => $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalPerPayment,
                'interest_amount' => $interestAmount,
                'total_payment' => $totalPayment,
            ]);
        }
    }

    private function generateInterestOnlySchedule(AssetFinancingAgreement $agreement)
    {
        $principal = $agreement->total_amount;
        $interestRate = $agreement->interest_rate / 100;
        $startDate = Carbon::parse($agreement->start_date);
        $endDate = Carbon::parse($agreement->end_date);
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);

        if ($numberOfPayments <= 0) {
            return;
        }

        $interestPerPayment = $principal * $interestRate / 12; // Assuming monthly interest for now

        for ($i = 1; $i <= $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i, $agreement->payment_frequency);
            $principalAmount = ($i === $numberOfPayments) ? $principal : 0;
            $totalPayment = $interestPerPayment + $principalAmount;

            $agreement->schedules()->create([
                'payment_number' => $i,
                'payment_date' => $paymentDate,
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestPerPayment,
                'total_payment' => $totalPayment,
            ]);
        }
    }

    private function generateSimpleInterestDailyAccrualSchedule(AssetFinancingAgreement $agreement)
    {
        $principal = $agreement->total_amount;
        $interestRate = $agreement->interest_rate / 100;
        $startDate = Carbon::parse($agreement->start_date);
        $endDate = Carbon::parse($agreement->end_date);
        $numberOfPayments = $this->getNumberOfPayments($startDate, $endDate, $agreement->payment_frequency);

        if ($numberOfPayments <= 0) {
            return;
        }

        $dailyInterestRate = $interestRate / 365;
        $principalPerPayment = $principal / $numberOfPayments;
        $remainingPrincipal = $principal;

        for ($i = 1; $i <= $numberOfPayments; $i++) {
            $paymentDate = $this->getPaymentDate($startDate, $i, $agreement->payment_frequency);
            $previousPaymentDate = $this->getPaymentDate($startDate, $i - 1, $agreement->payment_frequency);
            $days = $previousPaymentDate->diffInDays($paymentDate);
            $interestAmount = $remainingPrincipal * $dailyInterestRate * $days;
            $totalPayment = $principalPerPayment + $interestAmount;
            $remainingPrincipal -= $principalPerPayment;

            $agreement->schedules()->create([
                'payment_number' => $i,
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
                return $startDate->diffInMonths($endDate);
            case 'quarterly':
                return (int) ($startDate->diffInMonths($endDate) / 3);
            case 'annually':
                return $startDate->diffInYears($endDate);
            default:
                return 0;
        }
    }

    private function getPaymentDate(Carbon $startDate, int $paymentNumber, string $frequency): Carbon
    {
        switch ($frequency) {
            case 'monthly':
                return $startDate->copy()->addMonths($paymentNumber - 1);
            case 'quarterly':
                return $startDate->copy()->addQuarters($paymentNumber - 1);
            case 'annually':
                return $startDate->copy()->addYears($paymentNumber - 1);
            default:
                return $startDate;
        }
    }
}
