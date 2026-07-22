<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDeposit;
use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'name' => 'Acme Mobility',
        'legal_name' => 'Acme Mobility',
        'address' => 'Jl. Gatot Subroto',
        'city' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'postal_code' => '12950',
        'phone' => '021789012',
    ]);

    $retainedEarningsAccount = \App\Models\Account::create([
        'code' => '31000',
        'name' => 'Laba Ditahan',
        'type' => 'modal',
    ]);
    $retainedEarningsAccount->companies()->attach($this->company->id);

    $this->company->update([
        'default_retained_earnings_account_id' => $retainedEarningsAccount->id,
    ]);

    $this->branchGroup = BranchGroup::create([
        'name' => 'Rental HQ',
        'company_id' => $this->company->id,
    ]);

    $this->branch = Branch::create([
        'name' => 'Jakarta Selatan',
        'address' => 'Jl. Gatot Subroto',
        'branch_group_id' => $this->branchGroup->id,
    ]);

    $this->currency = Currency::firstOrCreate(
        ['code' => 'IDR'],
        ['name' => 'Rupiah', 'symbol' => 'Rp', 'is_primary' => true]
    );

    $this->partner = Partner::create([
        'name' => 'Test Customer',
        'phone' => '08123456789',
        'email' => 'customer@example.com',
    ]);

    $globalId = (string) \Illuminate\Support\Str::uuid();
    $this->centralUser = \App\Models\CentralUser::withoutEvents(function () use ($globalId) {
        return \App\Models\CentralUser::create([
            'name' => 'Test Central User',
            'email' => 'central.beforeeach@example.com',
            'password' => bcrypt('password'),
            'global_id' => $globalId,
        ]);
    });

    $this->user = \App\Models\User::withoutEvents(function () use ($globalId) {
        return \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test.beforeeach@example.com',
            'password' => bcrypt('password'),
            'global_id' => $globalId,
        ]);
    });

    $this->booking = Booking::create([
        'booking_number' => 'BK-TEST',
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'booking_type' => 'accommodation',
        'status' => BookingStatus::HOLD->value,
        'booked_at' => now(),
        'currency_id' => $this->currency->id,
    ]);

    $this->actingAs($this->centralUser);

    $this->mock(App\Services\Accounting\AccountingEventBus::class, function ($mock) {
        $mock->shouldReceive('dispatch')->zeroOrMoreTimes()->andReturn(new \App\Models\AccountingEventLog);
    });
});

it('adds deposit successfully when booking is not converted to sales order', function () {
    $response = $this->withoutMiddleware([
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])->post(route('booking-deposits.store', $this->booking->id), [
        'amount' => 500000,
        'payment_method' => 'cash',
        'received_at' => now()->toDateString(),
        'notes' => 'Tanda jadi deposit',
    ]);

    $response->assertStatus(302);
    expect($this->booking->deposits)->toHaveCount(1)
        ->and((float) $this->booking->deposits->first()->amount)->toBe(500000.0)
        ->and($this->booking->deposits->first()->notes)->toBe('Tanda jadi deposit')
        ->and((float) $this->booking->fresh()->total_deposit_amount)->toBe(500000.0);
});

it('adds deposit successfully when booking is converted to sales order but no invoice has been created', function () {
    $so = SalesOrder::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'order_number' => 'SO-TEST-01',
        'order_date' => now(),
    ]);

    $this->booking->update(['converted_sales_order_id' => $so->id]);

    $response = $this->withoutMiddleware([
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])->post(route('booking-deposits.store', $this->booking->id), [
        'amount' => 500000,
        'payment_method' => 'cash',
        'received_at' => now()->toDateString(),
        'notes' => 'Tanda jadi deposit',
    ]);

    $response->assertStatus(302);
    expect($this->booking->deposits)->toHaveCount(1)
        ->and((float) $this->booking->deposits->first()->amount)->toBe(500000.0)
        ->and((float) $this->booking->fresh()->total_deposit_amount)->toBe(500000.0);
});

it('prevents adding deposit when booking has a created invoice', function () {
    $so = SalesOrder::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'order_number' => 'SO-TEST-01',
        'order_date' => now(),
    ]);

    $this->booking->update(['converted_sales_order_id' => $so->id]);

    $si = \App\Models\SalesInvoice::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'invoice_number' => 'SI-001',
        'invoice_date' => now(),
    ]);

    $so->salesInvoices()->attach($si->id);

    \Illuminate\Support\Facades\Log::info('test hasInvoice debug info', [
        'connection' => \Illuminate\Support\Facades\DB::connection()->getName(),
        'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
        'sales_orders' => \App\Models\SalesOrder::all()->map(fn ($so) => $so->only('id', 'order_number'))->toArray(),
    ]);

    $response = $this->withoutMiddleware([
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])->post(route('booking-deposits.store', $this->booking->id), [
        'amount' => 500000,
        'payment_method' => 'cash',
        'received_at' => now()->toDateString(),
        'notes' => 'Tanda jadi deposit',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('error', 'Deposit tidak dapat ditambahkan setelah booking dibuatkan faktur.');
    expect($this->booking->deposits)->toHaveCount(0);
});

it('deletes deposit successfully when booking is not converted to sales order', function () {
    $deposit = BookingDeposit::create([
        'booking_id' => $this->booking->id,
        'amount' => 300000,
        'payment_method' => 'cash',
        'received_at' => now(),
    ]);

    $response = $this->withoutMiddleware([
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])->delete(route('booking-deposits.destroy', [$this->booking->id, $deposit->id]));

    $response->assertStatus(302);
    expect($this->booking->fresh()->deposits)->toHaveCount(0);
});

it('deletes deposit successfully when booking is converted to sales order but no invoice has been created', function () {
    $so = SalesOrder::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'order_number' => 'SO-TEST-02',
        'order_date' => now(),
    ]);

    $this->booking->update(['converted_sales_order_id' => $so->id]);

    $deposit = BookingDeposit::create([
        'booking_id' => $this->booking->id,
        'amount' => 300000,
        'payment_method' => 'cash',
        'received_at' => now(),
    ]);

    $response = $this->withoutMiddleware([
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])->delete(route('booking-deposits.destroy', [$this->booking->id, $deposit->id]));

    $response->assertStatus(302);
    expect($this->booking->fresh()->deposits)->toHaveCount(0);
});

it('prevents deleting deposit when booking has a created invoice', function () {
    $so = SalesOrder::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'order_number' => 'SO-TEST-02',
        'order_date' => now(),
    ]);

    $this->booking->update(['converted_sales_order_id' => $so->id]);

    $si = \App\Models\SalesInvoice::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'invoice_number' => 'SI-002',
        'invoice_date' => now(),
    ]);

    $so->salesInvoices()->attach($si->id);

    $deposit = BookingDeposit::create([
        'booking_id' => $this->booking->id,
        'amount' => 300000,
        'payment_method' => 'cash',
        'received_at' => now(),
    ]);

    $response = $this->withoutMiddleware([
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])->delete(route('booking-deposits.destroy', [$this->booking->id, $deposit->id]));

    $response->assertStatus(302);
    $response->assertSessionHas('error', 'Deposit tidak dapat dihapus setelah booking dibuatkan faktur.');
    expect(BookingDeposit::find($deposit->id))->not->toBeNull();
});

it('validates required fields for storing deposit', function () {
    $response = $this->withoutMiddleware([
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])->post(route('booking-deposits.store', $this->booking->id), [
        'amount' => '',
        'received_at' => '',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['amount', 'received_at']);
});
