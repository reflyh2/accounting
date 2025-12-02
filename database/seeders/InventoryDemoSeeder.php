<?php

namespace Database\Seeders;

use App\Models\AttributeSet;
use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\Company;
use App\Models\Location;
use App\Models\Lot;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Uom;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first() ?? Company::create([
            'name' => 'PT. Inventory Demo',
            'legal_name' => 'PT. Inventory Demo',
            'address' => 'Jl. Inventori No.1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '12345',
            'phone' => '0211234567',
        ]);

        $branchGroup = BranchGroup::first();

        $branch = Branch::first();

        $locations = [
            ['code' => 'WH-HQ', 'name' => 'Gudang Kantor Pusat', 'type' => 'warehouse'],
            ['code' => 'STORE-HQ', 'name' => 'Toko Pusat', 'type' => 'store'],
        ];

        foreach ($locations as $data) {
            Location::updateOrCreate(
                ['code' => $data['code']],
                [
                    'branch_id' => $branch->id,
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'is_active' => true,
                ]
            );
        }

        // $attributeSet = AttributeSet::where('code', 'retail_apparel')->first();
        // $uom = Uom::where('code', 'pcs')->first();

        // if (!$attributeSet || !$uom) {
        //     return;
        // }

        // $category = ProductCategory::firstOrCreate(
        //     ['code' => 'APPAREL'],
        //     [
        //         'name' => 'Apparel Demo',
        //         'attribute_set_id' => $attributeSet->id,
        //     ]
        // );

        // $product = Product::firstOrCreate(
        //     ['code' => 'SKU-DEMO-TSHIRT'],
        //     [
        //         'name' => 'Kaos Demo',
        //         'kind' => 'goods',
        //         'product_category_id' => $category->id,
        //         'attribute_set_id' => $attributeSet->id,
        //         'attrs_json' => ['brand' => 'Demo', 'color' => 'Blue', 'size' => 'M'],
        //         'default_uom_id' => $uom->id,
        //         'is_active' => true,
        //     ]
        // );

        // $product->companies()->syncWithoutDetaching([$company->id]);

        // $variant = ProductVariant::firstOrCreate(
        //     ['product_id' => $product->id, 'sku' => 'SKU-DEMO-TSHIRT-BL-M'],
        //     [
        //         'attrs_json' => ['color' => 'Blue', 'size' => 'M'],
        //         'track_inventory' => true,
        //         'uom_id' => $uom->id,
        //         'is_active' => true,
        //     ]
        // );

        // Lot::firstOrCreate(
        //     ['product_variant_id' => $variant->id, 'lot_code' => 'LOT-DEMO-001'],
        //     ['status' => 'active']
        // );
    }
}


