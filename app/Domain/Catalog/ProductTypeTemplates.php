<?php

namespace App\Domain\Catalog;

class ProductTypeTemplates
{
    public static function all(): array
    {
        return [
            'goods' => [
                'capabilities' => ['variantable', 'inventory_tracked'],
                'attribute_sets' => [
                    'retail_apparel',
                    'retail_electronics',
                    'retail_furniture',
                    'retail_footwear',
                    'retail_jewelry',
                    'retail_cosmetics',
                    'retail_sporting_goods',
                ],
                'form' => [
                    'base' => ['code','name','category_id','default_uom_id','tax_category_id'],
                    'attributes' => ['brand','material','color','size','gender'],
                    'pricing' => ['price_list_id','price'],
                    'inventory' => ['track_inventory' => true],
                ],
                'variant_axes' => ['color','size'],
            ],
            'service' => [
                'capabilities' => ['service'],
                'attribute_sets' => ['service_basic'],
                'form' => [
                    'base' => ['code','name','tax_category_id'],
                    'attributes' => ['duration_minutes','service_level'],
                ],
            ],
            'accommodation' => [
                'capabilities' => ['bookable'],
                'attribute_sets' => ['hotel_room'],
                'form' => [
                    'base' => ['code','name','tax_category_id'],
                    'attributes' => ['bed_type','view','smoking','max_occupancy'],
                    'pool' => ['branch_id','default_capacity'],
                    'calendar_seed' => [],
                ],
            ],
            'rental' => [
                'capabilities' => ['rental','serialized','bookable'],
                'attribute_sets' => ['vehicle_class'],
                'form' => [
                    'base' => ['code','name','tax_category_id'],
                    'attributes' => ['transmission','fuel','seats','luggage'],
                    'policy' => ['granularity','min_duration','max_duration','fuel_policy','mileage'],
                ],
            ],
            'package' => [
                'capabilities' => ['package'],
                'attribute_sets' => ['travel_package'],
                'form' => [
                    'base' => ['code','name','tax_category_id'],
                    'attributes' => ['duration_nights','meals','private_transfer'],
                ],
            ],
        ];
    }
}


