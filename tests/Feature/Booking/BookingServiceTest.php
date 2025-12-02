<?php

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\Product;
use App\Models\RentalPolicy;
use App\Models\ResourceInstance;
use App\Models\ResourcePool;
use App\Services\Booking\BookingService;
use App\Services\Booking\DTO\BookingLineDTO;
use App\Services\Booking\DTO\HoldBookingDTO;
use Illuminate\Support\Carbon;

it('creates a hold booking with computed amounts', function () {
    [$pool, $product] = createAccommodationPool();
    $currency = createBookingCurrency();
    $partner = createBookingPartner();

    $service = app(BookingService::class);
    $dto = new HoldBookingDTO(
        partnerId: $partner->id,
        currencyId: $currency->id,
        bookingType: 'accommodation',
        heldUntil: Carbon::now()->addHours(2),
        depositAmount: null,
        sourceChannel: 'web',
        notes: 'Integration test',
        lines: [
            new BookingLineDTO(
                productId: $product->id,
                resourcePoolId: $pool->id,
                start: Carbon::parse('2025-05-01 14:00'),
                end: Carbon::parse('2025-05-05 12:00'),
                qty: 2,
                unitPrice: 500000,
                depositRequired: 250000,
            ),
        ],
    );

    $booking = $service->hold($dto);

    expect($booking->status)->toBe(BookingStatus::HOLD->value)
        ->and((float) $booking->lines->first()->amount)->toBe(500000 * 2 * 4)
        ->and((float) $booking->deposit_amount)->toBe(250000.0);
});

it('prevents double booking when capacity is exceeded', function () {
    [$pool, $product] = createAccommodationPool();
    $currency = createBookingCurrency();
    $partner = createBookingPartner();

    $existingBooking = Booking::create([
        'booking_number' => 'BK-EXIST',
        'partner_id' => $partner->id,
        'booking_type' => 'accommodation',
        'status' => BookingStatus::CONFIRMED->value,
        'booked_at' => now(),
        'currency_id' => $currency->id,
    ]);

    BookingLine::create([
        'booking_id' => $existingBooking->id,
        'product_id' => $product->id,
        'resource_pool_id' => $pool->id,
        'start_datetime' => Carbon::parse('2025-05-10 14:00'),
        'end_datetime' => Carbon::parse('2025-05-12 10:00'),
        'qty' => 3,
        'unit_price' => 400000,
        'amount' => 2400000,
    ]);

    $service = app(BookingService::class);
    $dto = new HoldBookingDTO(
        partnerId: $partner->id,
        currencyId: $currency->id,
        bookingType: 'accommodation',
        heldUntil: null,
        depositAmount: null,
        sourceChannel: null,
        notes: null,
        lines: [
            new BookingLineDTO(
                productId: $product->id,
                resourcePoolId: $pool->id,
                start: Carbon::parse('2025-05-11 12:00'),
                end: Carbon::parse('2025-05-13 12:00'),
                qty: 1,
                unitPrice: 400000,
            ),
        ],
    );

    expect(fn () => $service->hold($dto))
        ->toThrow(BookingException::class);
});

it('calculates rental charges using policy granularity', function () {
    [$pool, $product] = createRentalPool();
    $currency = createBookingCurrency();
    $partner = createBookingPartner();

    $service = app(BookingService::class);
    $dto = new HoldBookingDTO(
        partnerId: $partner->id,
        currencyId: $currency->id,
        bookingType: 'rental',
        heldUntil: null,
        depositAmount: null,
        sourceChannel: null,
        notes: null,
        lines: [
            new BookingLineDTO(
                productId: $product->id,
                resourcePoolId: $pool->id,
                start: Carbon::parse('2025-06-01 08:00'),
                end: Carbon::parse('2025-06-03 08:00'),
                qty: 1,
                unitPrice: 750000,
            ),
        ],
    );

    $booking = $service->hold($dto);

    expect((float) $booking->lines->first()->amount)->toBe(750000 * 2);
});

function createAccommodationPool(): array
{
    [$company, $branch] = createCompanyWithBranch();

    $product = Product::create([
        'code' => 'ROOM-DELUXE',
        'name' => 'Deluxe Room',
        'kind' => 'accommodation',
    ]);

    $pool = ResourcePool::create([
        'product_id' => $product->id,
        'branch_id' => $branch->id,
        'name' => 'Tower B',
        'default_capacity' => 3,
    ]);

    foreach (range(1, 3) as $idx) {
        ResourceInstance::create([
            'resource_pool_id' => $pool->id,
            'code' => "ROOM-{$idx}",
            'status' => 'active',
        ]);
    }

    return [$pool, $product];
}

function createRentalPool(): array
{
    [$company, $branch] = createCompanyWithBranch();

    $product = Product::create([
        'code' => 'CAR-SEDAN',
        'name' => 'Sedan AT',
        'kind' => 'rental',
    ]);

    RentalPolicy::create([
        'product_id' => $product->id,
        'billing_granularity' => 'day',
    ]);

    $pool = ResourcePool::create([
        'product_id' => $product->id,
        'branch_id' => $branch->id,
        'name' => 'Rental Pool',
    ]);

    ResourceInstance::create([
        'resource_pool_id' => $pool->id,
        'code' => 'CAR-01',
        'status' => 'active',
    ]);

    return [$pool, $product];
}

function createCompanyWithBranch(): array
{
    $company = Company::create([
        'name' => 'Acme Mobility',
        'legal_name' => 'Acme Mobility',
        'tax_id' => 'NPWP654321',
        'business_registration_number' => 'BRN654',
        'address' => 'Jl. Gatot Subroto',
        'city' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'postal_code' => '12950',
        'phone' => '021789012',
        'email' => 'mobility@example.com',
    ]);

    $branchGroup = BranchGroup::create([
        'name' => 'Rental HQ',
        'company_id' => $company->id,
    ]);

    $branch = Branch::create([
        'name' => 'Jakarta Selatan',
        'address' => 'Jl. Gatot Subroto',
        'branch_group_id' => $branchGroup->id,
    ]);

    return [$company, $branch];
}

function createBookingCurrency(): Currency
{
    return Currency::firstOrCreate(
        ['code' => 'IDR'],
        ['name' => 'Rupiah', 'symbol' => 'Rp', 'is_primary' => true]
    );
}

function createBookingPartner(): Partner
{
    return Partner::create([
        'name' => 'Rental Customer',
        'phone' => '08123456789',
        'email' => 'customer@example.com',
    ]);
}

