<?php

namespace App\Domain\Catalog;

/**
 * ProductTypeTemplates
 *
 * Defines UI templates and defaults for each product kind.
 * Based on PRODUCT_TYPE_TEMPLATES.json.md v2 specification.
 */
class ProductTypeTemplates
{
    /**
     * Get all product templates indexed by template_code
     */
    public static function all(): array
    {
        return [
            // ===== TRADE / GOODS =====
            'goods_stock_retail' => [
                'template_code' => 'goods_stock_retail',
                'label' => 'Goods (Stock)',
                'description' => 'Stock-tracked goods with optional variants.',
                'kind' => 'goods_stock',
                'cost_model' => 'inventory_layer',
                'attribute_set_code' => 'goods_stock',
                'capabilities' => ['inventory_tracked', 'variantable'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Attributes'],
                        ['code' => 'variants', 'label' => 'Variants'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                        ['code' => 'inventory', 'label' => 'Inventory'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'default_uom_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'variants' => ['enabled' => true, 'variant_axes' => ['color', 'size']],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                    'inventory' => ['fields' => ['default_location_id', 'reorder_point', 'reorder_qty']],
                ],
            ],
            'goods_nonstock_basic' => [
                'template_code' => 'goods_nonstock_basic',
                'label' => 'Goods (Non-Stock / Drop-Ship)',
                'description' => 'Goods sold without inventory tracking.',
                'kind' => 'goods_nonstock',
                'cost_model' => 'direct_expense_per_sale',
                'attribute_set_code' => 'goods_nonstock',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Attributes'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
            ],
            'consumable_basic' => [
                'template_code' => 'consumable_basic',
                'label' => 'Consumable',
                'description' => 'Low-value consumables (optionally stock-tracked).',
                'kind' => 'consumable',
                'cost_model' => 'inventory_layer',
                'attribute_set_code' => 'consumable',
                'capabilities' => ['inventory_tracked'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Details'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                        ['code' => 'inventory', 'label' => 'Inventory'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'default_uom_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                    'inventory' => ['fields' => ['default_location_id']],
                ],
            ],
            'digital_good_basic' => [
                'template_code' => 'digital_good_basic',
                'label' => 'Digital Good',
                'description' => 'Digitally delivered goods (no inventory).',
                'kind' => 'digital_good',
                'cost_model' => 'direct_expense_per_sale',
                'attribute_set_code' => 'digital_good',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Delivery'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
            ],
            'bundle_package' => [
                'template_code' => 'bundle_package',
                'label' => 'Bundle (Simple)',
                'description' => 'Logical bundle sold as one line; components optional for reference.',
                'kind' => 'bundle',
                'cost_model' => 'hybrid',
                'attribute_set_code' => 'bundle',
                'capabilities' => ['package'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'components', 'label' => 'Components'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'components' => ['enabled' => true, 'fields' => ['component_items']],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
            ],
            'gift_card' => [
                'template_code' => 'gift_card',
                'label' => 'Gift Card / Voucher',
                'description' => 'Stored value product (deferred revenue).',
                'kind' => 'gift_card',
                'cost_model' => 'none',
                'attribute_set_code' => 'gift_card',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Voucher Rules'],
                        ['code' => 'pricing', 'label' => 'Value'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price']],
                ],
            ],

            // ===== SERVICES =====
            'service_professional' => [
                'template_code' => 'service_professional',
                'label' => 'Service (Professional)',
                'description' => 'Project-based services with job costing.',
                'kind' => 'service_professional',
                'cost_model' => 'job_costing',
                'attribute_set_code' => 'service_professional',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Service Details'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
            ],
            'service_managed' => [
                'template_code' => 'service_managed',
                'label' => 'Service (Managed)',
                'description' => 'Ongoing managed services (non-metered SaaS).',
                'kind' => 'service_managed',
                'cost_model' => 'hybrid',
                'attribute_set_code' => 'service_managed',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Service Scope'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
            ],
            'service_labor' => [
                'template_code' => 'service_labor',
                'label' => 'Service (Labor)',
                'description' => 'Time-based labor services.',
                'kind' => 'service_labor',
                'cost_model' => 'job_costing',
                'attribute_set_code' => 'service_labor',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Time Rules'],
                        ['code' => 'pricing', 'label' => 'Rates'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
            ],
            'service_fee' => [
                'template_code' => 'service_fee',
                'label' => 'Service (Fee)',
                'description' => 'Administrative/transaction fees.',
                'kind' => 'service_fee',
                'cost_model' => 'direct_expense_per_sale',
                'attribute_set_code' => 'service_fee',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Fee Rules'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],
            'service_installation' => [
                'template_code' => 'service_installation',
                'label' => 'Service (Installation)',
                'description' => 'Installation/setup tied to goods.',
                'kind' => 'service_installation',
                'cost_model' => 'job_costing',
                'attribute_set_code' => 'service_installation',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Scope'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],

            // ===== BOOKING / CAPACITY / EVENTS =====
            'accommodation_room_type' => [
                'template_code' => 'accommodation_room_type',
                'label' => 'Accommodation',
                'description' => 'Bookable lodging sold by stay/night.',
                'kind' => 'accommodation',
                'cost_model' => 'hybrid',
                'attribute_set_code' => 'accommodation',
                'capabilities' => ['bookable'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Room Details'],
                        ['code' => 'pool', 'label' => 'Pool & Capacity'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pool' => ['fields' => ['branch_id', 'default_capacity']],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
                'post_create' => ['create_resource_pool', 'ensure_price_list_item'],
            ],
            'venue_booking' => [
                'template_code' => 'venue_booking',
                'label' => 'Venue Booking',
                'description' => 'Bookable space rental by time.',
                'kind' => 'venue_booking',
                'cost_model' => 'hybrid',
                'attribute_set_code' => 'venue_booking',
                'capabilities' => ['bookable'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Venue Details'],
                        ['code' => 'pool', 'label' => 'Pool'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pool' => ['fields' => ['branch_id', 'default_capacity']],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
                'post_create' => ['create_resource_pool', 'ensure_price_list_item'],
            ],
            'event_ticket_resale' => [
                'template_code' => 'event_ticket_resale',
                'label' => 'Event Ticket',
                'description' => 'Ticket sales with optional dated occurrences.',
                'kind' => 'event_ticket',
                'cost_model' => 'direct_expense_per_sale',
                'attribute_set_code' => 'event_ticket',
                'capabilities' => ['bookable'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Event Details'],
                        ['code' => 'capacity', 'label' => 'Capacity'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'capacity' => ['fields' => ['uses_occurrence', 'default_capacity']],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
            ],
            'tour_activity' => [
                'template_code' => 'tour_activity',
                'label' => 'Tour / Activity',
                'description' => 'Scheduled departures; may bundle components.',
                'kind' => 'tour_activity',
                'cost_model' => 'hybrid',
                'attribute_set_code' => 'tour_activity',
                'capabilities' => ['bookable'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Activity Details'],
                        ['code' => 'occurrence', 'label' => 'Departures'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'occurrence' => ['enabled' => true, 'fields' => ['uses_occurrence', 'default_capacity']],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
            ],
            'appointment' => [
                'template_code' => 'appointment',
                'label' => 'Appointment',
                'description' => '1:1 or limited-slot appointment scheduling.',
                'kind' => 'appointment',
                'cost_model' => 'job_costing',
                'attribute_set_code' => 'appointment',
                'capabilities' => ['bookable'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Appointment Rules'],
                        ['code' => 'pool', 'label' => 'Resources'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pool' => ['fields' => ['branch_id', 'default_capacity']],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
                'post_create' => ['create_resource_pool', 'ensure_price_list_item'],
            ],

            // ===== RENTAL / HIRE =====
            'asset_rental_class' => [
                'template_code' => 'asset_rental_class',
                'label' => 'Asset Rental',
                'description' => 'Rental backed by physical assets; instance assignment required.',
                'kind' => 'asset_rental',
                'cost_model' => 'asset_usage_costing',
                'attribute_set_code' => 'asset_rental',
                'capabilities' => ['bookable', 'rental', 'serialized'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Class Details'],
                        ['code' => 'policy', 'label' => 'Rental Policy'],
                        ['code' => 'pool', 'label' => 'Pool'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'policy' => ['fields' => ['billing_granularity', 'min_duration_minutes', 'deposit_amount', 'fuel_policy', 'mileage_included']],
                    'pool' => ['fields' => ['branch_id']],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                ],
                'post_create' => ['create_resource_pool', 'create_rental_policy', 'ensure_price_list_item'],
            ],
            'rental_with_operator' => [
                'template_code' => 'rental_with_operator',
                'label' => 'Rental with Operator',
                'description' => 'Rental + labor/operator bundled.',
                'kind' => 'rental_with_operator',
                'cost_model' => 'hybrid',
                'attribute_set_code' => 'rental_with_operator',
                'capabilities' => ['bookable', 'rental', 'serialized'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Details'],
                        ['code' => 'policy', 'label' => 'Policy'],
                        ['code' => 'pool', 'label' => 'Pool'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'policy' => ['fields' => ['billing_granularity', 'deposit_amount']],
                    'pool' => ['fields' => ['branch_id']],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
                'post_create' => ['create_resource_pool', 'create_rental_policy', 'ensure_price_list_item'],
            ],
            'lease' => [
                'template_code' => 'lease',
                'label' => 'Lease',
                'description' => 'Long-term leasing sold as a service/rental product.',
                'kind' => 'lease',
                'cost_model' => 'asset_usage_costing',
                'attribute_set_code' => 'lease',
                'capabilities' => ['rental', 'serialized'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Lease Terms'],
                        ['code' => 'policy', 'label' => 'Billing'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'policy' => ['fields' => ['billing_period', 'requires_schedule']],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],

            // ===== TRAVEL / TRANSPORT (AGENCY) =====
            'air_ticket_resale' => [
                'template_code' => 'air_ticket_resale',
                'label' => 'Air Ticket (Agency)',
                'description' => 'Airline ticket sales; costs may use prepaid deposit/top-up consumption.',
                'kind' => 'air_ticket_resale',
                'cost_model' => 'prepaid_consumption',
                'attribute_set_code' => 'air_ticket',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Ticket Details'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                        ['code' => 'prepaid', 'label' => 'Prepaid/Deposit'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['price_list_id', 'base_price', 'price_uom_id']],
                    'prepaid' => ['fields' => ['prepaid_account_id', 'airline_partner_id']],
                ],
            ],
            'train_ticket_resale' => [
                'template_code' => 'train_ticket_resale',
                'label' => 'Train Ticket (Agency)',
                'description' => 'Train ticket resale with per-ticket cost.',
                'kind' => 'train_ticket_resale',
                'cost_model' => 'direct_expense_per_sale',
                'attribute_set_code' => 'train_ticket',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Ticket Details'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],
            'bus_ferry_ticket_resale' => [
                'template_code' => 'bus_ferry_ticket_resale',
                'label' => 'Bus/Ferry Ticket (Agency)',
                'description' => 'Bus or ferry ticket resale with per-ticket cost.',
                'kind' => 'bus_ferry_ticket_resale',
                'cost_model' => 'direct_expense_per_sale',
                'attribute_set_code' => 'bus_ferry_ticket',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Ticket Details'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],
            'hotel_resale' => [
                'template_code' => 'hotel_resale',
                'label' => 'Hotel (Agency)',
                'description' => 'Hotel resale/agency model with per-booking cost.',
                'kind' => 'hotel_resale',
                'cost_model' => 'direct_expense_per_sale',
                'attribute_set_code' => 'hotel_resale',
                'capabilities' => ['bookable'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Hotel Details'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],
            'travel_package' => [
                'template_code' => 'travel_package',
                'label' => 'Travel Package',
                'description' => 'Bundled travel offering (hotel + tickets + tours).',
                'kind' => 'travel_package',
                'cost_model' => 'hybrid',
                'attribute_set_code' => 'travel_package',
                'capabilities' => ['package'],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Package Details'],
                        ['code' => 'components', 'label' => 'Components'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'components' => ['enabled' => true, 'fields' => ['component_items']],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],

            // ===== FINANCIAL / UTILITY / OTHER =====
            'shipping_charge' => [
                'template_code' => 'shipping_charge',
                'label' => 'Shipping Charge',
                'description' => 'Delivery/logistics fee charged to customers.',
                'kind' => 'shipping_charge',
                'cost_model' => 'direct_expense_per_sale',
                'attribute_set_code' => 'shipping_charge',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Rules'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],
            'insurance_addon' => [
                'template_code' => 'insurance_addon',
                'label' => 'Insurance Add-on',
                'description' => 'Optional insurance sold alongside products.',
                'kind' => 'insurance_addon',
                'cost_model' => 'direct_expense_per_sale',
                'attribute_set_code' => 'insurance_addon',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Coverage'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],
            'deposit' => [
                'template_code' => 'deposit',
                'label' => 'Deposit (Refundable)',
                'description' => 'Refundable security deposit product (liability).',
                'kind' => 'deposit',
                'cost_model' => 'none',
                'attribute_set_code' => 'deposit',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Deposit Rules'],
                        ['code' => 'pricing', 'label' => 'Amount'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price']],
                ],
            ],
            'penalty_fee' => [
                'template_code' => 'penalty_fee',
                'label' => 'Penalty Fee',
                'description' => 'Penalty/late/cancellation fees.',
                'kind' => 'penalty_fee',
                'cost_model' => 'none',
                'attribute_set_code' => 'penalty_fee',
                'capabilities' => [],
                'wizard' => ['enabled' => false, 'steps' => []],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],
            'membership' => [
                'template_code' => 'membership',
                'label' => 'Membership',
                'description' => 'Entitlement/access product (non-stored-value).',
                'kind' => 'membership',
                'cost_model' => 'hybrid',
                'attribute_set_code' => 'membership',
                'capabilities' => [],
                'wizard' => [
                    'enabled' => true,
                    'steps' => [
                        ['code' => 'base', 'label' => 'Basic Info'],
                        ['code' => 'attributes', 'label' => 'Entitlements'],
                        ['code' => 'pricing', 'label' => 'Pricing'],
                    ],
                ],
                'sections' => [
                    'base' => ['fields' => ['code', 'name', 'category_id', 'tax_category_id', 'is_active']],
                    'attributes' => ['source' => 'attribute_set', 'mode' => 'dynamic'],
                    'pricing' => ['fields' => ['base_price', 'price_uom_id']],
                ],
            ],
        ];
    }

    /**
     * Get template by code
     */
    public static function get(string $templateCode): ?array
    {
        return self::all()[$templateCode] ?? null;
    }

    /**
     * Get template by kind
     */
    public static function getByKind(string $kind): ?array
    {
        foreach (self::all() as $template) {
            if ($template['kind'] === $kind) {
                return $template;
            }
        }
        return null;
    }

    /**
     * Get all templates for a specific group
     */
    public static function getByGroup(string $group): array
    {
        $groupKinds = ProductRulesBundle::KIND_GROUPS[$group]['kinds'] ?? [];
        $templates = [];
        foreach (self::all() as $code => $template) {
            if (in_array($template['kind'], $groupKinds, true)) {
                $templates[$code] = $template;
            }
        }
        return $templates;
    }

    /**
     * Get template codes indexed by group for menu building
     */
    public static function getGroupedTemplates(): array
    {
        $result = [];
        foreach (ProductRulesBundle::KIND_GROUPS as $groupKey => $group) {
            $result[$groupKey] = [
                'label' => $group['label'],
                'templates' => [],
            ];
            foreach (self::all() as $code => $template) {
                if (in_array($template['kind'], $group['kinds'], true)) {
                    $result[$groupKey]['templates'][] = [
                        'code' => $code,
                        'label' => $template['label'],
                        'kind' => $template['kind'],
                    ];
                }
            }
        }
        return $result;
    }
}
