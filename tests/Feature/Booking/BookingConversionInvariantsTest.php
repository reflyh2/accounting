<?php

use App\Enums\BookingStatus;
use App\Exceptions\BookingConversionException;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Services\Booking\BookingConversionService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    [$pool, $product] = createAccommodationPool();
    $this->pool = $pool;
    $this->product = $product;
    $this->currency = createBookingCurrency();
    $this->partner = createBookingPartner();
    $this->supplier = \App\Models\Partner::create([
        'name' => 'Garuda Indonesia',
        'phone' => '0211234',
        'email' => 'ga@example.com',
    ]);
});

function makeBooking(string $mode, array $lineOverrides = []): Booking
{
    $booking = Booking::create([
        'booking_number' => 'BK-INV-'.uniqid(),
        'partner_id' => test()->partner->id,
        'booking_type' => 'accommodation',
        'booking_subtype' => 'hotel',
        'fulfillment_mode' => $mode,
        'status' => BookingStatus::CONFIRMED->value,
        'booked_at' => now(),
        'currency_id' => test()->currency->id,
    ]);

    BookingLine::create(array_merge([
        'booking_id' => $booking->id,
        'product_id' => test()->product->id,
        'resource_pool_id' => test()->pool->id,
        'start_datetime' => Carbon::parse('2026-06-01 14:00'),
        'end_datetime' => Carbon::parse('2026-06-03 12:00'),
        'qty' => 1,
        'unit_price' => 1000000,
        'amount' => 1000000,
    ], $lineOverrides));

    return $booking->load('lines');
}

it('rejects conversion when booking is still on hold', function () {
    $booking = makeBooking('self_operated');
    $booking->update(['status' => BookingStatus::HOLD->value]);

    $service = app(BookingConversionService::class);

    expect(fn () => $service->convertToSalesOrder($booking))
        ->toThrow(BookingConversionException::class);
});

it('rejects reseller conversion missing supplier_partner_id', function () {
    $booking = makeBooking('reseller', [
        'supplier_partner_id' => null,
        'supplier_cost' => 700000,
    ]);

    $service = app(BookingConversionService::class);

    expect(fn () => $service->convertToSalesOrder($booking))
        ->toThrow(BookingConversionException::class, 'supplier_partner_id');
});

it('rejects reseller conversion with zero supplier_cost', function () {
    $booking = makeBooking('reseller', [
        'supplier_partner_id' => test()->supplier->id,
        'supplier_cost' => 0,
    ]);

    $service = app(BookingConversionService::class);

    expect(fn () => $service->convertToSalesOrder($booking))
        ->toThrow(BookingConversionException::class, 'supplier_cost');
});

it('rejects agent conversion when commission + passthrough != amount', function () {
    $booking = makeBooking('agent', [
        'supplier_partner_id' => test()->supplier->id,
        'amount' => 1000000,
        'commission_amount' => 100000,
        'passthrough_amount' => 800000, // 100k + 800k = 900k, not 1M
    ]);

    $service = app(BookingConversionService::class);

    expect(fn () => $service->convertToSalesOrder($booking))
        ->toThrow(BookingConversionException::class, 'tidak sama dengan amount');
});
