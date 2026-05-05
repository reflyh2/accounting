<?php

namespace App\Services\Booking\Allocation;

use App\Models\BookingLine;
use App\Models\SalesInvoiceLine;
use Carbon\CarbonImmutable;

/**
 * Resolves the per-line allocation numerator for a booking allocation run.
 *
 * Numerator basis depends on the booking subtype:
 *  - hotel:        room_nights = qty × (check_out − check_in in days)
 *  - flight:       seat_nights = qty (one segment treated as one unit; multi-leg is v2)
 *  - car_rental:   rental_days = qty × (return_datetime − pickup_datetime in days, ceil)
 *  - other:        falls back to revenue (line_total_base) so something is always allocatable.
 */
class NumeratorResolver
{
    /**
     * Compute numerator and basis for an SI line that's linked to a Booking.
     *
     * @return array{basis: string, numerator: float}
     */
    public function resolve(SalesInvoiceLine $invoiceLine): array
    {
        $bookingLine = $invoiceLine->salesOrderLine?->bookingLine;
        if (! $bookingLine) {
            return ['basis' => 'revenue', 'numerator' => (float) $invoiceLine->line_total_base];
        }

        $booking = $bookingLine->booking;
        $subtype = $booking?->booking_subtype;

        return match ($subtype) {
            'hotel' => [
                'basis' => 'room_nights',
                'numerator' => $this->roomNights($bookingLine),
            ],
            'flight' => [
                'basis' => 'seat_nights',
                'numerator' => max(1.0, (float) $bookingLine->qty),
            ],
            'car_rental' => [
                'basis' => 'rental_days',
                'numerator' => $this->rentalDays($bookingLine),
            ],
            default => [
                'basis' => 'revenue',
                'numerator' => (float) $invoiceLine->line_total_base,
            ],
        };
    }

    private function roomNights(BookingLine $line): float
    {
        $start = $line->start_datetime ? CarbonImmutable::parse($line->start_datetime) : null;
        $end = $line->end_datetime ? CarbonImmutable::parse($line->end_datetime) : null;

        if (! $start || ! $end) {
            return max(1.0, (float) $line->qty);
        }

        $nights = max(1, (int) ceil($start->diffInDays($end, false)));

        return max(1.0, (float) $line->qty) * $nights;
    }

    private function rentalDays(BookingLine $line): float
    {
        $start = $line->start_datetime ? CarbonImmutable::parse($line->start_datetime) : null;
        $end = $line->end_datetime ? CarbonImmutable::parse($line->end_datetime) : null;

        if (! $start || ! $end) {
            return max(1.0, (float) $line->qty);
        }

        $days = max(1, (int) ceil($start->diffInHours($end, false) / 24));

        return max(1.0, (float) $line->qty) * $days;
    }
}
