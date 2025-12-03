<?php

use App\Exceptions\SalesOrderException;
use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerRole;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxCategory;
use App\Models\TaxComponent;
use App\Models\TaxJurisdiction;
use App\Models\TaxRule;
use App\Models\Uom;
use App\Services\Sales\SalesService;
use Illuminate\Support\Carbon;

it('creates sales order using pricing and tax services', function () {
    [$company, $branch, $customer, $variant, $uom, $location, $currency] = createSalesOrderDependencies();

    $priceList = PriceList::create([
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'name' => 'Retail Price',
        'is_active' => true,
    ]);

    PriceListItem::create([
        'price_list_id' => $priceList->id,
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'uom_id' => $uom->id,
        'price' => 150000,
        'min_qty' => 1,
    ]);

    $taxRule = createTaxRule($company);

    $variant->product->update(['tax_category_id' => $taxRule->tax_category_id]);

    $service = app(SalesService::class);

    $order = $service->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'partner_id' => $customer->id,
        'price_list_id' => $priceList->id,
        'currency_id' => $currency->id,
        'order_date' => Carbon::now()->toDateString(),
        'reserve_stock' => false,
        'lines' => [
            [
                'product_variant_id' => $variant->id,
                'uom_id' => $uom->id,
                'quantity' => 2,
            ],
        ],
    ]);

    expect($order->lines)->toHaveCount(1);

    $line = $order->lines->first();
    expect((float) $line->unit_price)->toBe(150000.0);
    expect((float) $line->tax_rate)->toBe((float) $taxRule->rate_value);
    expect((float) $order->subtotal)->toBe(300000.0);
    expect((float) $order->tax_total)->toBeCloseTo(300000 * ($taxRule->rate_value / 100), 2);
});

it('applies and releases reservations for soft strictness companies', function () {
    [$company, $branch, $customer, $variant, $uom, $location, $currency] = createSalesOrderDependencies();

    InventoryItem::create([
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'qty_on_hand' => 10,
        'qty_reserved' => 0,
    ]);

    $service = app(SalesService::class);

    $order = $service->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'partner_id' => $customer->id,
        'currency_id' => $currency->id,
        'order_date' => Carbon::now()->toDateString(),
        'reserve_stock' => true,
        'lines' => [
            [
                'product_variant_id' => $variant->id,
                'uom_id' => $uom->id,
                'quantity' => 5,
                'reservation_location_id' => $location->id,
            ],
        ],
    ]);

    $service->confirm($order);

    $item = InventoryItem::firstWhere([
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
    ]);

    expect((float) $item->qty_reserved)->toBe(5.0);

    $service->releaseReservation($order);

    $item->refresh();
    expect((float) $item->qty_reserved)->toBe(0.0);
});

it('enforces hard reservation strictness', function () {
    [$company, $branch, $customer, $variant, $uom, $location, $currency] = createSalesOrderDependencies([
        'reservation_strictness' => 'hard',
    ]);

    InventoryItem::create([
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'qty_on_hand' => 2,
        'qty_reserved' => 0,
    ]);

    $service = app(SalesService::class);

    $order = $service->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'partner_id' => $customer->id,
        'currency_id' => $currency->id,
        'order_date' => Carbon::now()->toDateString(),
        'reserve_stock' => true,
        'lines' => [
            [
                'product_variant_id' => $variant->id,
                'uom_id' => $uom->id,
                'quantity' => 5,
                'reservation_location_id' => $location->id,
            ],
        ],
    ]);

    $service->confirm($order);
})->throws(SalesOrderException::class, 'Persediaan tidak mencukupi');

function createSalesOrderDependencies(array $companyOverrides = []): array
{
    $currency = Currency::create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $companyData = array_merge([
        'name' => 'Acme Corp',
        'legal_name' => 'Acme Corp',
        'tax_id' => 'NPWP123456789',
        'business_registration_number' => 'BRN123',
        'address' => 'Jl. Kebon Jeruk No.1',
        'city' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'postal_code' => '11530',
        'phone' => '021123456',
        'email' => 'info@example.com',
        'costing_policy' => 'fifo',
        'reservation_strictness' => $companyOverrides['reservation_strictness'] ?? 'soft',
        'default_backflush' => false,
    ], $companyOverrides);

    $company = Company::create($companyData);

    $branchGroup = BranchGroup::create([
        'name' => 'Pusat',
        'company_id' => $company->id,
    ]);

    $branch = Branch::create([
        'name' => 'Cabang Utama',
        'address' => 'Jl. Cabang',
        'branch_group_id' => $branchGroup->id,
    ]);

    $uom = Uom::create([
        'company_id' => $company->id,
        'code' => 'PCS',
        'name' => 'Pieces',
        'kind' => 'each',
    ]);

    $product = Product::create([
        'code' => 'SKU-01',
        'name' => 'Sample Shirt',
        'kind' => 'goods',
        'default_uom_id' => $uom->id,
        'attrs_json' => [],
    ]);
    $product->companies()->sync([$company->id]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'SKU-01-RED',
        'attrs_json' => [],
        'track_inventory' => true,
        'uom_id' => $uom->id,
    ]);

    $customer = Partner::create([
        'name' => 'PT Pelanggan',
    ]);
    $customer->companies()->sync([$company->id]);
    PartnerRole::create([
        'partner_id' => $customer->id,
        'role' => 'customer',
        'status' => 'active',
    ]);

    $location = Location::create([
        'branch_id' => $branch->id,
        'code' => 'LOC-A',
        'name' => 'Gudang Utama',
        'type' => 'warehouse',
        'is_active' => true,
    ]);

    return [$company, $branch, $customer, $variant, $uom, $location, $currency];
}

function createTaxRule(Company $company): TaxRule
{
    $taxCategory = TaxCategory::create([
        'company_id' => $company->id,
        'code' => 'VAT',
        'name' => 'PPN',
        'default_behavior' => 'taxable',
        'attributes_json' => [],
    ]);

    $jurisdiction = TaxJurisdiction::create([
        'code' => 'ID',
        'name' => 'Indonesia',
        'country_code' => 'ID',
        'level' => 'country',
    ]);

    $component = TaxComponent::create([
        'tax_jurisdiction_id' => $jurisdiction->id,
        'code' => 'VAT11',
        'name' => 'PPN 11%',
        'kind' => 'vat',
    ]);

    return TaxRule::create([
        'tax_category_id' => $taxCategory->id,
        'tax_jurisdiction_id' => $jurisdiction->id,
        'tax_component_id' => $component->id,
        'rate_type' => 'percent',
        'rate_value' => 11,
        'tax_inclusive' => false,
        'effective_from' => now()->subDay(),
    ]);
}


