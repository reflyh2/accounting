<?php

use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\CostLayer;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Uom;
use App\Services\Inventory\DTO\IssueDTO;
use App\Services\Inventory\DTO\IssueLineDTO;
use App\Services\Inventory\DTO\ReceiptDTO;
use App\Services\Inventory\DTO\ReceiptLineDTO;
use App\Services\Inventory\DTO\TransferDTO;
use App\Services\Inventory\DTO\TransferLineDTO;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

it('records receipts and creates cost layers', function () {
    [$variant, $location] = createInventoryVariantWithLocation();
    $service = app(InventoryService::class);

    $result = $service->receipt(new ReceiptDTO(
        Carbon::now(),
        $location->id,
        [
            new ReceiptLineDTO($variant->id, $variant->uom_id, 5, 100),
        ],
    ));

    expect($result->transaction->lines)->toHaveCount(1);

    $item = InventoryItem::where('product_variant_id', $variant->id)
        ->where('location_id', $location->id)
        ->first();

    expect((float) $item->qty_on_hand)->toBe(5.0);
    expect(CostLayer::count())->toBe(1);
});

it('issues inventory using fifo costing', function () {
    [$variant, $location] = createInventoryVariantWithLocation();
    $service = app(InventoryService::class);

    $service->receipt(new ReceiptDTO(
        Carbon::now(),
        $location->id,
        [
            new ReceiptLineDTO($variant->id, $variant->uom_id, 3, 100),
        ],
    ));

    $service->receipt(new ReceiptDTO(
        Carbon::now()->addMinute(),
        $location->id,
        [
            new ReceiptLineDTO($variant->id, $variant->uom_id, 2, 150),
        ],
    ));

    $issueResult = $service->issue(new IssueDTO(
        Carbon::now()->addMinutes(2),
        $location->id,
        [
            new IssueLineDTO($variant->id, $variant->uom_id, 4),
        ],
    ));

    expect($issueResult->transaction->lines)->toHaveCount(1);
    expect((float) $issueResult->transaction->lines->first()->unit_cost)->toBe(112.5);

    $layers = CostLayer::where('product_variant_id', $variant->id)
        ->where('location_id', $location->id)
        ->orderBy('id')
        ->get();

    expect((float) $layers[0]->qty_remaining)->toBe(0.0);
    expect((float) $layers[1]->qty_remaining)->toBe(1.0);

    $item = InventoryItem::where('product_variant_id', $variant->id)
        ->where('location_id', $location->id)
        ->first();

    expect((float) $item->qty_on_hand)->toBe(1.0);
});

it('issues inventory using company costing policy when not overridden', function () {
    [$variant, $location] = createInventoryVariantWithLocation(companyAttributes: [
        'costing_policy' => 'moving_avg',
    ]);
    $service = app(InventoryService::class);

    $service->receipt(new ReceiptDTO(
        Carbon::now(),
        $location->id,
        [
            new ReceiptLineDTO($variant->id, $variant->uom_id, 3, 100),
        ],
    ));

    $service->receipt(new ReceiptDTO(
        Carbon::now()->addMinute(),
        $location->id,
        [
            new ReceiptLineDTO($variant->id, $variant->uom_id, 2, 150),
        ],
    ));

    $issueResult = $service->issue(new IssueDTO(
        Carbon::now()->addMinutes(2),
        $location->id,
        [
            new IssueLineDTO($variant->id, $variant->uom_id, 4),
        ],
    ));

    expect($issueResult->transaction->lines)->toHaveCount(1);
    expect((float) $issueResult->transaction->lines->first()->unit_cost)->toBe(120.0);
});

it('transfers inventory between locations', function () {
    [$variant, $sourceLocation, $branch] = createInventoryVariantWithLocation(returnBranch: true);
    $destination = createLocation($branch, 'LOC-B');
    $service = app(InventoryService::class);

    $service->receipt(new ReceiptDTO(
        Carbon::now(),
        $sourceLocation->id,
        [
            new ReceiptLineDTO($variant->id, $variant->uom_id, 4, 80),
        ],
    ));

    $service->transfer(new TransferDTO(
        Carbon::now()->addHour(),
        $sourceLocation->id,
        $destination->id,
        [
            new TransferLineDTO($variant->id, $variant->uom_id, 2),
        ],
    ));

    $sourceItem = InventoryItem::where('product_variant_id', $variant->id)
        ->where('location_id', $sourceLocation->id)
        ->first();

    $destinationItem = InventoryItem::where('product_variant_id', $variant->id)
        ->where('location_id', $destination->id)
        ->first();

    expect((float) $sourceItem->qty_on_hand)->toBe(2.0);
    expect((float) $destinationItem->qty_on_hand)->toBe(2.0);

    $destinationLayers = CostLayer::where('location_id', $destination->id)->get();
    expect($destinationLayers)->toHaveCount(1);
    expect((float) $destinationLayers->first()->qty_remaining)->toBe(2.0);
});

function createInventoryVariantWithLocation(bool $returnBranch = false, array $companyAttributes = []): array
{
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
        'reservation_strictness' => 'soft',
        'default_backflush' => false,
    ], $companyAttributes);

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

    $location = createLocation($branch, 'LOC-A');

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

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'SKU-01-RED',
        'attrs_json' => [],
        'track_inventory' => true,
        'uom_id' => $uom->id,
    ]);

    return $returnBranch
        ? [$variant, $location, $branch]
        : [$variant, $location];
}

function createLocation(Branch $branch, string $code): Location
{
    return Location::create([
        'branch_id' => $branch->id,
        'code' => $code . '-' . Str::upper(Str::random(3)),
        'name' => "Gudang {$code}",
        'type' => 'warehouse',
        'is_active' => true,
    ]);
}


