<?php

use App\Enums\Documents\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderException;
use App\Exceptions\DocumentStateException;
use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\PartnerRole;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Uom;
use App\Models\User;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->company = Company::create([
        'name' => 'Acme',
        'legal_name' => 'Acme Corp',
        'costing_policy' => 'fifo',
        'reservation_strictness' => 'soft',
        'default_backflush' => false,
    ]);

    $this->branchGroup = BranchGroup::create([
        'name' => 'HQ',
        'company_id' => $this->company->id,
    ]);

    $this->branch = Branch::create([
        'name' => 'Cabang Utama',
        'address' => 'Jl. Utama',
        'branch_group_id' => $this->branchGroup->id,
    ]);

    $this->currency = Currency::create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
    ]);

    $this->uom = Uom::create([
        'company_id' => $this->company->id,
        'code' => 'PCS',
        'name' => 'Pieces',
        'kind' => 'each',
    ]);

    $this->product = Product::create([
        'code' => 'SKU-001',
        'name' => 'Kertas A4',
        'kind' => 'goods',
        'default_uom_id' => $this->uom->id,
    ]);

    DB::table('company_product')->insert([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'SKU-001-STD',
        'uom_id' => $this->uom->id,
        'track_inventory' => true,
    ]);

    $this->supplier = Partner::create([
        'name' => 'Paper Supplier',
        'code' => 'SUP-01',
    ]);

    PartnerRole::create([
        'partner_id' => $this->supplier->id,
        'role' => 'supplier',
    ]);

    DB::table('partner_company')->insert([
        'partner_id' => $this->supplier->id,
        'company_id' => $this->company->id,
    ]);

    $this->service = app(PurchaseService::class);
});

function purchaseOrderPayload(array $overrides = []): array
{
    $defaults = [
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'partner_id' => test()->supplier->id,
        'currency_id' => test()->currency->id,
        'order_date' => now()->toDateString(),
        'exchange_rate' => 1,
        'lines' => [
            [
                'product_variant_id' => test()->variant->id,
                'uom_id' => test()->uom->id,
                'quantity' => 5,
                'unit_price' => 100000,
                'tax_rate' => 11,
                'description' => 'Kertas',
            ],
        ],
    ];

    return array_replace_recursive($defaults, $overrides);
}

it('creates purchase orders with calculated totals', function () {
    $user = User::factory()->create();
    actingAs($user);

    $order = $this->service->create(purchaseOrderPayload());

    expect($order->order_number)->toStartWith('PO.');
    expect($order->status)->toBe(PurchaseOrderStatus::DRAFT->value);
    expect((float) $order->subtotal)->toBe(500000.0);
    expect((float) $order->tax_total)->toBe(55000.0);
    expect((float) $order->total_amount)->toBe(555000.0);
    expect($order->lines)->toHaveCount(1);
    expect($order->lines->first()->quantity)->toBeFloat();
});

it('prevents approving by maker when maker-checker enforced', function () {
    config()->set('purchasing.maker_checker.enforce', true);

    $maker = User::factory()->create();
    $checker = User::factory()->create();

    actingAs($maker);
    $order = $this->service->create(purchaseOrderPayload());

    expect(fn () => $this->service->approve($order))->toThrow(DocumentStateException::class);

    actingAs($checker);
    $approved = $this->service->approve($order->fresh());

    expect($approved->status)->toBe(PurchaseOrderStatus::APPROVED->value);
});

it('rejects branch-company mismatch payloads', function () {
    $user = User::factory()->create();
    actingAs($user);

    $otherCompany = Company::create([
        'name' => 'Other Co',
        'legal_name' => 'Other Co',
        'costing_policy' => 'fifo',
        'reservation_strictness' => 'soft',
        'default_backflush' => false,
    ]);

    expect(fn () => $this->service->create(purchaseOrderPayload([
        'company_id' => $otherCompany->id,
    ])))->toThrow(PurchaseOrderException::class);
});

