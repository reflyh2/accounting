<?php

use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ResourceInstance;
use App\Models\ResourcePool;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrderLine;
use App\Services\Booking\Allocation\NumeratorResolver;
use Illuminate\Support\Carbon;

function nrSetup(): array
{
    $company = Company::create([
        'name' => 'Acme NR',
        'legal_name' => 'Acme NR',
        'tax_id' => 'NPWP-NR',
        'business_registration_number' => 'BRN-NR',
        'address' => 'X', 'city' => 'Jakarta', 'province' => 'DKI', 'postal_code' => '00000',
        'phone' => '021', 'email' => 'nr@example.com',
    ]);
    $bg = BranchGroup::create(['name' => 'NR HQ', 'company_id' => $company->id]);
    $branch = Branch::create(['name' => 'NR Branch', 'address' => 'Y', 'branch_group_id' => $bg->id]);

    $product = Product::create(['code' => 'NR-1', 'name' => 'Room', 'kind' => 'accommodation']);
    $pool = ResourcePool::create([
        'product_id' => $product->id,
        'branch_id' => $branch->id,
        'name' => 'Pool',
        'default_capacity' => 5,
    ]);
    ResourceInstance::create(['resource_pool_id' => $pool->id, 'code' => 'NR-INS-1', 'status' => 'active']);

    $currency = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'is_primary' => true]);
    $partner = Partner::create(['name' => 'Cust', 'phone' => '08', 'email' => 'c@e.com']);

    return compact('company', 'branch', 'product', 'pool', 'currency', 'partner');
}

it('returns room_nights for hotel subtype', function () {
    $ctx = nrSetup();

    $booking = Booking::create([
        'booking_number' => 'BK-NR-1',
        'partner_id' => $ctx['partner']->id,
        'company_id' => $ctx['company']->id,
        'branch_id' => $ctx['branch']->id,
        'booking_type' => 'accommodation',
        'booking_subtype' => 'hotel',
        'fulfillment_mode' => 'self_operated',
        'status' => 'confirmed',
        'booked_at' => now(),
        'currency_id' => $ctx['currency']->id,
    ]);

    $bookingLine = BookingLine::create([
        'booking_id' => $booking->id,
        'product_id' => $ctx['product']->id,
        'resource_pool_id' => $ctx['pool']->id,
        'start_datetime' => Carbon::parse('2026-06-01 14:00'),
        'end_datetime' => Carbon::parse('2026-06-04 12:00'),
        'qty' => 2,
        'unit_price' => 500000,
        'amount' => 3000000,
    ]);

    $soLine = new SalesOrderLine(['booking_line_id' => $bookingLine->id]);
    $soLine->setRelation('bookingLine', $bookingLine);

    $invoiceLine = new SalesInvoiceLine(['line_total_base' => 3000000]);
    $invoiceLine->setRelation('salesOrderLine', $soLine);

    $resolver = new NumeratorResolver;
    $resolved = $resolver->resolve($invoiceLine);

    expect($resolved['basis'])->toBe('room_nights')
        ->and($resolved['numerator'])->toBe(6.0); // 2 qty × 3 nights
});

it('returns rental_days for car_rental subtype', function () {
    $ctx = nrSetup();

    $booking = Booking::create([
        'booking_number' => 'BK-NR-2',
        'partner_id' => $ctx['partner']->id,
        'company_id' => $ctx['company']->id,
        'branch_id' => $ctx['branch']->id,
        'booking_type' => 'rental',
        'booking_subtype' => 'car_rental',
        'fulfillment_mode' => 'self_operated',
        'status' => 'confirmed',
        'booked_at' => now(),
        'currency_id' => $ctx['currency']->id,
    ]);

    $bookingLine = BookingLine::create([
        'booking_id' => $booking->id,
        'product_id' => $ctx['product']->id,
        'resource_pool_id' => $ctx['pool']->id,
        'start_datetime' => Carbon::parse('2026-06-10 09:00'),
        'end_datetime' => Carbon::parse('2026-06-12 09:00'),
        'qty' => 1,
        'unit_price' => 800000,
        'amount' => 1600000,
    ]);

    $soLine = new SalesOrderLine(['booking_line_id' => $bookingLine->id]);
    $soLine->setRelation('bookingLine', $bookingLine);

    $invoiceLine = new SalesInvoiceLine(['line_total_base' => 1600000]);
    $invoiceLine->setRelation('salesOrderLine', $soLine);

    $resolver = new NumeratorResolver;
    $resolved = $resolver->resolve($invoiceLine);

    expect($resolved['basis'])->toBe('rental_days')
        ->and($resolved['numerator'])->toBe(2.0);
});

it('falls back to revenue when no booking is linked', function () {
    $invoiceLine = new SalesInvoiceLine(['line_total_base' => 12345.67]);
    $invoiceLine->setRelation('salesOrderLine', null);

    $resolver = new NumeratorResolver;
    $resolved = $resolver->resolve($invoiceLine);

    expect($resolved['basis'])->toBe('revenue')
        ->and($resolved['numerator'])->toBe(12345.67);
});
