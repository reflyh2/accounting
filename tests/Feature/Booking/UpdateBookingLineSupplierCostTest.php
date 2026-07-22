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
use App\Models\ResourcePool;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceCost;
use App\Models\SalesOrder;
use App\Models\SalesOrderCost;
use App\Services\Booking\BookingLineSupplierCostService;
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

    $this->supplier = Partner::create([
        'name' => 'Test Supplier',
        'phone' => '021123456',
        'email' => 'supplier@example.com',
    ]);

    $this->product = Product::create([
        'code' => 'ROOM-DELUXE',
        'name' => 'Deluxe Room',
        'kind' => 'accommodation',
    ]);

    $this->uom = \App\Models\Uom::firstOrCreate(
        ['code' => 'PCS', 'company_id' => $this->company->id],
        ['name' => 'Pieces', 'kind' => 'each']
    );

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

    $this->pool = ResourcePool::create([
        'product_id' => $this->product->id,
        'branch_id' => $this->branch->id,
        'name' => 'Tower B',
        'default_capacity' => 3,
    ]);
});

function createTestBooking(array $bookingOverrides = [], array $lineOverrides = []): array
{
    $booking = Booking::create(array_merge([
        'booking_number' => 'BK-'.uniqid(),
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'partner_id' => test()->partner->id,
        'booking_type' => 'accommodation',
        'status' => BookingStatus::CONFIRMED->value,
        'booked_at' => now(),
        'currency_id' => test()->currency->id,
        'fulfillment_mode' => 'reseller',
    ], $bookingOverrides));

    $line1 = BookingLine::create(array_merge([
        'booking_id' => $booking->id,
        'product_id' => test()->product->id,
        'resource_pool_id' => test()->pool->id,
        'start_datetime' => now()->addDays(1),
        'end_datetime' => now()->addDays(2),
        'qty' => 1,
        'unit_price' => 1000000,
        'amount' => 1000000,
        'supplier_partner_id' => test()->supplier->id,
        'supplier_cost' => 800000,
    ], $lineOverrides));

    $line2 = BookingLine::create([
        'booking_id' => $booking->id,
        'product_id' => test()->product->id,
        'resource_pool_id' => test()->pool->id,
        'start_datetime' => now()->addDays(1),
        'end_datetime' => now()->addDays(2),
        'qty' => 1,
        'unit_price' => 1000000,
        'amount' => 1000000,
        'supplier_partner_id' => test()->supplier->id,
        'supplier_cost' => 200000,
    ]);

    return [$booking, $line1, $line2];
}

it('updates supplier cost successfully when not settled', function () {
    [$booking, $line1, $line2] = createTestBooking();

    // Sediakan Sales Order dan SalesOrderCost
    $so = SalesOrder::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'order_number' => 'SO-001',
        'order_date' => now(),
    ]);
    $booking->update(['converted_sales_order_id' => $so->id]);

    $soCost = SalesOrderCost::create([
        'sales_order_id' => $so->id,
        'amount' => 1000000, // Total cost line 1 (800k) + line 2 (200k)
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
    ]);

    // Sediakan Sales Invoice dan SalesInvoiceCost
    $si = SalesInvoice::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'invoice_number' => 'SI-001',
        'invoice_date' => now(),
    ]);

    $siCost = SalesInvoiceCost::create([
        'sales_invoice_id' => $si->id,
        'sales_order_cost_id' => null,
        'cost_item_id' => null,
        'amount' => 1000000,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
    ]);

    // Hubungkan SalesOrder dan SalesInvoice di pivot table
    \Illuminate\Support\Facades\DB::table('sales_invoice_sales_order')->insert([
        'sales_invoice_id' => $si->id,
        'sales_order_id' => $so->id,
    ]);

    // Sediakan SalesOrderLine dan SalesInvoiceLine
    $soLine = \App\Models\SalesOrderLine::create([
        'sales_order_id' => $so->id,
        'product_id' => $this->product->id,
        'uom_id' => $this->uom->id,
        'base_uom_id' => $this->uom->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_price' => 1000000,
        'line_total' => 1000000,
        'line_number' => 1,
        'description' => 'Test',
        'booking_line_id' => $line1->id,
    ]);

    $siLine = \App\Models\SalesInvoiceLine::create([
        'sales_invoice_id' => $si->id,
        'sales_order_line_id' => $soLine->id,
        'product_id' => $this->product->id,
        'uom_label' => 'PCS',
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_price' => 1000000,
        'line_total' => 1000000,
        'line_total_base' => 1000000,
        'cost_total' => 800000,
        'gross_margin' => 200000,
        'line_number' => 1,
        'description' => 'Test',
        'delivery_value_base' => 0,
        'revenue_variance' => 0,
    ]);

    // Sediakan Account
    $account1 = \App\Models\Account::create([
        'code' => 'COGS-001',
        'name' => 'COGS',
        'type' => 'beban_pokok_penjualan',
    ]);
    $account1->companies()->attach($this->company->id);

    $account2 = \App\Models\Account::create([
        'code' => 'CLEAR-001',
        'name' => 'Clearing',
        'type' => 'hutang_usaha',
    ]);
    $account2->companies()->attach($this->company->id);

    // Sediakan Journal dan JournalEntry untuk event COGS Reseller
    $journal = \App\Models\Journal::create([
        'branch_id' => $this->branch->id,
        'user_global_id' => $this->user->global_id,
        'date' => now(),
        'journal_type' => 'sales',
        'reference_number' => $si->invoice_number,
        'description' => 'Booking Principal COGS Posted - '.$si->invoice_number,
    ]);

    $debitEntry = $journal->journalEntries()->create([
        'account_id' => $account1->id,
        'debit' => 1000000,
        'credit' => 0,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
        'primary_currency_debit' => 1000000,
        'primary_currency_credit' => 0,
    ]);

    $creditEntry = $journal->journalEntries()->create([
        'account_id' => $account2->id,
        'debit' => 0,
        'credit' => 1000000,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
        'primary_currency_debit' => 0,
        'primary_currency_credit' => 1000000,
    ]);

    $service = app(BookingLineSupplierCostService::class);
    $updatedLine = $service->updateSupplierCost($line1, 900000);

    expect($updatedLine->supplier_cost)->toBe('900000.00');

    // Cek SalesOrderCost ter-update: line1 (900k) + line2 (200k) = 1.1M
    expect((float) $soCost->fresh()->amount)->toBe(1100000.0);

    // Cek SalesInvoiceCost ter-update
    expect((float) $siCost->fresh()->amount)->toBe(1100000.0);

    // Cek SalesInvoiceLine ter-update
    expect((float) $siLine->fresh()->cost_total)->toBe(900000.0);
    expect((float) $siLine->fresh()->gross_margin)->toBe(100000.0);

    // Cek JournalEntry ter-update ke total cost 1.1M
    expect((float) $debitEntry->fresh()->debit)->toBe(1100000.0);
    expect((float) $creditEntry->fresh()->credit)->toBe(1100000.0);
});

it('prevents update when booking line is settled', function () {
    [$booking, $line1, $line2] = createTestBooking(lineOverrides: [
        'settled_by_type' => 'App\Models\PurchaseInvoiceLine',
        'settled_by_id' => 1,
    ]);

    $service = app(BookingLineSupplierCostService::class);

    expect(fn () => $service->updateSupplierCost($line1, 900000))
        ->toThrow(\App\Exceptions\BookingException::class, 'Harga supplier tidak dapat diubah karena sudah ada faktur pembelian.');
});

it('handles PATCH request correctly via controller', function () {
    [$booking, $line1, $line2] = createTestBooking();

    // Sediakan Sales Order dan SalesOrderCost agar service propagation tidak fail
    $so = SalesOrder::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'partner_id' => $this->partner->id,
        'currency_id' => $this->currency->id,
        'status' => 'draft',
        'order_number' => 'SO-002',
        'order_date' => now(),
    ]);
    $booking->update(['converted_sales_order_id' => $so->id]);

    $soCost = SalesOrderCost::create([
        'sales_order_id' => $so->id,
        'amount' => 1000000,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
    ]);

    $globalId = \Illuminate\Support\Str::uuid()->toString();

    $centralUser = \App\Models\CentralUser::withoutEvents(function () use ($globalId) {
        return \App\Models\CentralUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'global_id' => $globalId,
        ]);
    });

    \App\Models\User::withoutEvents(function () use ($globalId) {
        return \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'global_id' => $globalId,
        ]);
    });

    $this->actingAs($centralUser);

    $response = $this->withoutMiddleware([
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])->patch(route('bookings.update-supplier-cost', $line1->id), [
        'supplier_cost' => 950000,
    ]);

    $response->assertStatus(302);
    expect($line1->fresh()->supplier_cost)->toBe('950000.00');
});

it('rejects negative supplier cost validation', function () {
    [$booking, $line1, $line2] = createTestBooking();

    $globalId = \Illuminate\Support\Str::uuid()->toString();

    $centralUser = \App\Models\CentralUser::withoutEvents(function () use ($globalId) {
        return \App\Models\CentralUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'global_id' => $globalId,
        ]);
    });

    \App\Models\User::withoutEvents(function () use ($globalId) {
        return \App\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'global_id' => $globalId,
        ]);
    });

    $this->actingAs($centralUser);

    $response = $this->withoutMiddleware([
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])->patch(route('bookings.update-supplier-cost', $line1->id), [
        'supplier_cost' => -100,
    ]);

    $response->assertSessionHasErrors(['supplier_cost']);
});

it('prevents update when booking is in hold status', function () {
    [$booking, $line1, $line2] = createTestBooking([
        'status' => BookingStatus::HOLD->value,
    ]);

    $service = app(BookingLineSupplierCostService::class);

    expect(fn () => $service->updateSupplierCost($line1, 900000))
        ->toThrow(\App\Exceptions\BookingException::class, 'Harga supplier tidak dapat diubah ketika booking berstatus hold.');
});
