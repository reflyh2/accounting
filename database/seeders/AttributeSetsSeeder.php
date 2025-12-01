<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AttributeSet;
use App\Models\AttributeDef;
use App\Models\Company;

class AttributeSetsSeeder extends Seeder
{
    public function run(): void
    {
        // Define minimal attribute sets and defs
        $sets = [
            'retail_apparel' => [
                ['code' => 'brand', 'label' => 'Brand', 'data_type' => 'string'],
                ['code' => 'material', 'label' => 'Material', 'data_type' => 'string'],
                ['code' => 'color', 'label' => 'Color', 'data_type' => 'string', 'is_variant_axis' => true],
                ['code' => 'size', 'label' => 'Size', 'data_type' => 'string', 'is_variant_axis' => true],
                ['code' => 'gender', 'label' => 'Gender', 'data_type' => 'select', 'options_json' => ['Men','Women','Unisex']],
            ],
            'retail_electronics' => [
                ['code' => 'brand', 'label' => 'Brand', 'data_type' => 'string'],
                ['code' => 'model', 'label' => 'Model', 'data_type' => 'string'],
                ['code' => 'warranty', 'label' => 'Warranty Period', 'data_type' => 'number'],
                ['code' => 'color', 'label' => 'Color', 'data_type' => 'string', 'is_variant_axis' => true],
            ],
            'retail_furniture' => [
                ['code' => 'material', 'label' => 'Material', 'data_type' => 'string'],
                ['code' => 'dimensions', 'label' => 'Dimensions', 'data_type' => 'string'],
                ['code' => 'color', 'label' => 'Color', 'data_type' => 'string', 'is_variant_axis' => true],
                ['code' => 'assembly_required', 'label' => 'Assembly Required', 'data_type' => 'boolean'],
            ],
            'retail_footwear' => [
                ['code' => 'brand', 'label' => 'Brand', 'data_type' => 'string'],
                ['code' => 'size', 'label' => 'Size', 'data_type' => 'string', 'is_variant_axis' => true],
                ['code' => 'material', 'label' => 'Material', 'data_type' => 'string'],
                ['code' => 'color', 'label' => 'Color', 'data_type' => 'string', 'is_variant_axis' => true],
                ['code' => 'gender', 'label' => 'Gender', 'data_type' => 'select', 'options_json' => ['Men','Women','Unisex']],
            ],
            'retail_jewelry' => [
                ['code' => 'brand', 'label' => 'Brand', 'data_type' => 'string'],
                ['code' => 'material', 'label' => 'Material', 'data_type' => 'string'],
                ['code' => 'type', 'label' => 'Type', 'data_type' => 'select', 'options_json' => ['Ring','Necklace','Bracelet','Earrings']],
            ],
            'retail_cosmetics' => [
                ['code' => 'brand', 'label' => 'Brand', 'data_type' => 'string'],
                ['code' => 'shade', 'label' => 'Shade', 'data_type' => 'string'],
                ['code' => 'type', 'label' => 'Type', 'data_type' => 'select', 'options_json' => ['Lipstick','Foundation','Mascara','Blush']],
            ],
            'retail_sporting_goods' => [
                ['code' => 'brand', 'label' => 'Brand', 'data_type' => 'string'],
                ['code' => 'sport', 'label' => 'Sport Type', 'data_type' => 'select', 'options_json' => ['Soccer','Basketball','Tennis','Golf']],
                ['code' => 'material', 'label' => 'Material', 'data_type' => 'string'],
                ['code' => 'color', 'label' => 'Color', 'data_type' => 'string', 'is_variant_axis' => true],
            ],
            'service_basic' => [
                ['code' => 'duration_minutes', 'label' => 'Duration (minutes)', 'data_type' => 'number'],
                ['code' => 'service_level', 'label' => 'Service Level', 'data_type' => 'select', 'options_json' => ['Basic','Premium']],
            ],
            'hotel_room' => [
                ['code' => 'bed_type', 'label' => 'Bed Type', 'data_type' => 'select', 'options_json' => ['Single','Double','Queen','King']],
                ['code' => 'view', 'label' => 'View', 'data_type' => 'string'],
                ['code' => 'smoking', 'label' => 'Smoking', 'data_type' => 'boolean'],
                ['code' => 'max_occupancy', 'label' => 'Max Occupancy', 'data_type' => 'number'],
            ],
            'vehicle_class' => [
                ['code' => 'transmission', 'label' => 'Transmission', 'data_type' => 'select', 'options_json' => ['Manual','Automatic']],
                ['code' => 'fuel', 'label' => 'Fuel', 'data_type' => 'select', 'options_json' => ['Gasoline','Diesel','EV','Hybrid']],
                ['code' => 'seats', 'label' => 'Seats', 'data_type' => 'number'],
                ['code' => 'luggage', 'label' => 'Luggage Capacity', 'data_type' => 'number'],
            ],
            'travel_package' => [
                ['code' => 'duration_nights', 'label' => 'Duration (nights)', 'data_type' => 'number'],
                ['code' => 'meals', 'label' => 'Meals Included', 'data_type' => 'boolean'],
                ['code' => 'private_transfer', 'label' => 'Private Transfer', 'data_type' => 'boolean'],
            ],
        ];

        foreach ($sets as $setCode => $defs) {
            $set = AttributeSet::query()->updateOrCreate(['code' => $setCode], [
                'name' => ucwords(str_replace('_', ' ', $setCode)),
            ]);

            $companies = Company::get()->pluck('id');
            $set->companies()->sync($companies);

            foreach ($defs as $def) {
                AttributeDef::query()->updateOrCreate(
                    ['attribute_set_id' => $set->id, 'code' => $def['code']],
                    [
                        'label' => $def['label'],
                        'data_type' => $def['data_type'],
                        'is_required' => $def['is_required'] ?? false,
                        'is_variant_axis' => $def['is_variant_axis'] ?? false,
                        'options_json' => $def['options_json'] ?? null,
                        'default_value' => $def['default_value'] ?? null,
                    ]
                );
            }
        }
    }
}


