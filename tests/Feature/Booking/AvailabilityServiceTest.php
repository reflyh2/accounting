<?php

use App\Enums\BookingStatus;
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
use App\Services\Booking\AvailabilityService;
use Illuminate\Support\Carbon;

it('calculates available capacity for a pool', function () {
    [$pool, $product] = createPool();
    $currency = createCurrency();
    $partner = createPartner();

    $booking = Booking::create([
        'booking_number' => 'BK-TEST',
        'partner_id' => $partner->id,
        'booking_type' => 'accommodation',
        'status' => BookingStatus::CONFIRMED->value,
        'booked_at' => now(),
        'currency_id' => $currency->id,
    ]);

    BookingLine::create([
        'booking_id' => $booking->id,
        'product_id' => $product->id,
        'resource_pool_id' => $pool->id,
        'start_datetime' => Carbon::parse('2025-01-01 14:00'),
        'end_datetime' => Carbon::parse('2025-01-03 10:00'),
        'qty' => 1,
        'unit_price' => 100000,
        'amount' => 200000,
    ]);

    $service = app(AvailabilityService::class);
    $result = $service->searchPoolAvailability(
        $pool->id,
        Carbon::parse('2025-01-01 10:00'),
        Carbon::parse('2025-01-04 10:00'),
        1
    );

    expect($result->capacity)->toBe(3)
        ->and($result->bookedQty)->toBe(1)
        ->and($result->availableQty)->toBe(2);
});

it('returns free instances within range', function () {
    [$pool, $product] = createPool();
    $currency = createCurrency();
    $partner = createPartner();
    $instances = collect(range(1, 2))->map(function ($idx) use ($pool) {
        return ResourceInstance::create([
            'resource_pool_id' => $pool->id,
            'code' => "INST-{$idx}",
            'status' => 'active',
        ]);
    });

    $booking = Booking::create([
        'booking_number' => 'BK-TEST2',
        'partner_id' => $partner->id,
        'booking_type' => 'rental',
        'status' => BookingStatus::CONFIRMED->value,
        'booked_at' => now(),
        'currency_id' => $currency->id,
    ]);

    BookingLine::create([
        'booking_id' => $booking->id,
        'product_id' => $product->id,
        'resource_pool_id' => $pool->id,
        'resource_instance_id' => $instances[0]->id,
        'start_datetime' => Carbon::parse('2025-02-01 09:00'),
        'end_datetime' => Carbon::parse('2025-02-01 18:00'),
        'qty' => 1,
        'unit_price' => 150000,
        'amount' => 150000,
    ]);

    $service = app(AvailabilityService::class);
    $free = $service->findFreeInstances(
        $pool->id,
        Carbon::parse('2025-02-01 08:00'),
        Carbon::parse('2025-02-01 19:00'),
        5
    );

    expect($free->pluck('id'))->not()->toContain($instances[0]->id)
        ->and($free->pluck('id'))->toContain($instances[1]->id);
});

function createPool(): array
{
    $company = Company::create([
        'name' => 'Acme Hospitality',
        'legal_name' => 'Acme Hospitality',
        'tax_id' => 'NPWP123456',
        'business_registration_number' => 'BRN123',
        'address' => 'Jl. Kebon Jeruk No.1',
        'city' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'postal_code' => '11530',
        'phone' => '021123456',
        'email' => 'info@example.com',
    ]);

    $branchGroup = BranchGroup::create([
        'name' => 'Utama',
        'company_id' => $company->id,
    ]);

    $branch = Branch::create([
        'name' => 'Jakarta',
        'address' => 'Jl. Sudirman',
        'branch_group_id' => $branchGroup->id,
    ]);

    $product = Product::create([
        'code' => 'ROOM-DELUXE',
        'name' => 'Deluxe Room',
        'kind' => 'accommodation',
    ]);

    $pool = ResourcePool::create([
        'product_id' => $product->id,
        'branch_id' => $branch->id,
        'name' => 'Tower A',
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

function createCurrency(): Currency
{
    return Currency::firstOrCreate(
        ['code' => 'IDR'],
        ['name' => 'Rupiah', 'symbol' => 'Rp', 'is_primary' => true]
    );
}

function createPartner(): Partner
{
    return Partner::create([
        'name' => 'John Wick',
        'phone' => '0800000',
        'email' => 'john@example.com',
    ]);
}

