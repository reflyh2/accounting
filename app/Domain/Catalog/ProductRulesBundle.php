<?php

namespace App\Domain\Catalog;

/**
 * ProductRulesBundle
 *
 * Consolidated runtime artifact for product validation and UI rendering.
 * Based on PRODUCT_RULES_BUNDLE.json.md specification.
 */
class ProductRulesBundle
{
    public const BUNDLE_VERSION = '2025-12-26';

    public const CAPABILITIES = [
        'inventory_tracked',
        'variantable',
        'bookable',
        'rental',
        'serialized',
        'package',
    ];

    public const COST_MODELS = [
        'none',
        'inventory_layer',
        'direct_expense_per_sale',
        'job_costing',
        'asset_usage_costing',
        'prepaid_consumption',
        'hybrid',
    ];

    public const KINDS = [
        // Trade / Goods
        'goods_stock',
        'goods_nonstock',
        'consumable',
        'digital_good',
        'bundle',
        'gift_card',
        // Services
        'service_professional',
        'service_managed',
        'service_labor',
        'service_fee',
        'service_installation',
        // Booking / Capacity / Events
        'accommodation',
        'venue_booking',
        'event_ticket',
        'tour_activity',
        'appointment',
        // Rental / Hire
        'asset_rental',
        'rental_with_operator',
        'lease',
        // Travel / Transport (Agency)
        'air_ticket_resale',
        'train_ticket_resale',
        'bus_ferry_ticket_resale',
        'hotel_resale',
        'travel_package',
        // Financial / Utility / Other
        'shipping_charge',
        'insurance_addon',
        'deposit',
        'penalty_fee',
        'membership',
    ];

    /**
     * Kind groups for UI tabbed navigation
     */
    public const KIND_GROUPS = [
        'trade' => [
            'label' => 'Trade / Goods',
            'kinds' => ['goods_stock', 'goods_nonstock', 'consumable', 'digital_good', 'bundle', 'gift_card'],
        ],
        'service' => [
            'label' => 'Services',
            'kinds' => ['service_professional', 'service_managed', 'service_labor', 'service_fee', 'service_installation'],
        ],
        'booking' => [
            'label' => 'Booking / Events',
            'kinds' => ['accommodation', 'venue_booking', 'event_ticket', 'tour_activity', 'appointment'],
        ],
        'rental' => [
            'label' => 'Rental / Hire',
            'kinds' => ['asset_rental', 'rental_with_operator', 'lease'],
        ],
        'travel' => [
            'label' => 'Travel / Agency',
            'kinds' => ['air_ticket_resale', 'train_ticket_resale', 'bus_ferry_ticket_resale', 'hotel_resale', 'travel_package'],
        ],
        'other' => [
            'label' => 'Other',
            'kinds' => ['shipping_charge', 'insurance_addon', 'deposit', 'penalty_fee', 'membership'],
        ],
    ];

    /**
     * Default cost model per kind
     */
    public const KIND_SEEDS = [
        'goods_stock' => ['default_cost_model' => 'inventory_layer'],
        'goods_nonstock' => ['default_cost_model' => 'direct_expense_per_sale'],
        'consumable' => ['default_cost_model' => 'inventory_layer'],
        'digital_good' => ['default_cost_model' => 'direct_expense_per_sale'],
        'bundle' => ['default_cost_model' => 'hybrid'],
        'gift_card' => ['default_cost_model' => 'none'],

        'service_professional' => ['default_cost_model' => 'job_costing'],
        'service_managed' => ['default_cost_model' => 'hybrid'],
        'service_labor' => ['default_cost_model' => 'job_costing'],
        'service_fee' => ['default_cost_model' => 'direct_expense_per_sale'],
        'service_installation' => ['default_cost_model' => 'job_costing'],

        'accommodation' => ['default_cost_model' => 'hybrid'],
        'venue_booking' => ['default_cost_model' => 'hybrid'],
        'event_ticket' => ['default_cost_model' => 'direct_expense_per_sale'],
        'tour_activity' => ['default_cost_model' => 'hybrid'],
        'appointment' => ['default_cost_model' => 'job_costing'],

        'asset_rental' => ['default_cost_model' => 'asset_usage_costing'],
        'rental_with_operator' => ['default_cost_model' => 'hybrid'],
        'lease' => ['default_cost_model' => 'asset_usage_costing'],

        'air_ticket_resale' => ['default_cost_model' => 'prepaid_consumption'],
        'train_ticket_resale' => ['default_cost_model' => 'direct_expense_per_sale'],
        'bus_ferry_ticket_resale' => ['default_cost_model' => 'direct_expense_per_sale'],
        'hotel_resale' => ['default_cost_model' => 'direct_expense_per_sale'],
        'travel_package' => ['default_cost_model' => 'hybrid'],

        'shipping_charge' => ['default_cost_model' => 'direct_expense_per_sale'],
        'insurance_addon' => ['default_cost_model' => 'direct_expense_per_sale'],
        'deposit' => ['default_cost_model' => 'none'],
        'penalty_fee' => ['default_cost_model' => 'none'],
        'membership' => ['default_cost_model' => 'hybrid'],
    ];

    /**
     * Capability × Kind matrix
     * Legend: required=⭐, optional=⚠️, forbidden=❌
     */
    public const CAPABILITY_MATRIX = [
        'goods_stock' => ['inventory_tracked' => 'required', 'variantable' => 'required', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'optional', 'package' => 'optional'],
        'goods_nonstock' => ['inventory_tracked' => 'forbidden', 'variantable' => 'optional', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'optional'],
        'consumable' => ['inventory_tracked' => 'optional', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'digital_good' => ['inventory_tracked' => 'forbidden', 'variantable' => 'optional', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'optional'],
        'bundle' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'required'],
        'gift_card' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],

        'service_professional' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'optional'],
        'service_managed' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'optional'],
        'service_labor' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'optional', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'service_fee' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'service_installation' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'optional', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],

        'accommodation' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'required', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'venue_booking' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'required', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'event_ticket' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'required', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'tour_activity' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'required', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'optional'],
        'appointment' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'required', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],

        'asset_rental' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'required', 'rental' => 'required', 'serialized' => 'required', 'package' => 'forbidden'],
        'rental_with_operator' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'required', 'rental' => 'required', 'serialized' => 'required', 'package' => 'forbidden'],
        'lease' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'optional', 'rental' => 'required', 'serialized' => 'required', 'package' => 'forbidden'],

        'air_ticket_resale' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'optional', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'optional'],
        'train_ticket_resale' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'optional', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'optional'],
        'bus_ferry_ticket_resale' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'optional', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'optional'],
        'hotel_resale' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'optional', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'optional'],
        'travel_package' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'optional', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'required'],

        'shipping_charge' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'insurance_addon' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'deposit' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'penalty_fee' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'forbidden', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
        'membership' => ['inventory_tracked' => 'forbidden', 'variantable' => 'forbidden', 'bookable' => 'optional', 'rental' => 'forbidden', 'serialized' => 'forbidden', 'package' => 'forbidden'],
    ];

    /**
     * Get all kinds
     */
    public static function getKinds(): array
    {
        return self::KINDS;
    }

    /**
     * Get kind groups for UI
     */
    public static function getKindGroups(): array
    {
        return self::KIND_GROUPS;
    }

    /**
     * Get default cost model for a kind
     */
    public static function getDefaultCostModel(string $kind): string
    {
        return self::KIND_SEEDS[$kind]['default_cost_model'] ?? 'direct_expense_per_sale';
    }

    /**
     * Get required capabilities for a kind
     */
    public static function getRequiredCapabilities(string $kind): array
    {
        $matrix = self::CAPABILITY_MATRIX[$kind] ?? [];
        return array_keys(array_filter($matrix, fn($v) => $v === 'required'));
    }

    /**
     * Get forbidden capabilities for a kind
     */
    public static function getForbiddenCapabilities(string $kind): array
    {
        $matrix = self::CAPABILITY_MATRIX[$kind] ?? [];
        return array_keys(array_filter($matrix, fn($v) => $v === 'forbidden'));
    }

    /**
     * Check if a capability is allowed for a kind
     */
    public static function isCapabilityAllowed(string $kind, string $capability): bool
    {
        $matrix = self::CAPABILITY_MATRIX[$kind] ?? [];
        $status = $matrix[$capability] ?? 'optional';
        return $status !== 'forbidden';
    }

    /**
     * Get label for a kind
     */
    public static function getKindLabel(string $kind): string
    {
        $labels = [
            'goods_stock' => 'Goods (Stock)',
            'goods_nonstock' => 'Goods (Non-Stock)',
            'consumable' => 'Consumable',
            'digital_good' => 'Digital Good',
            'bundle' => 'Bundle',
            'gift_card' => 'Gift Card / Voucher',
            'service_professional' => 'Service (Professional)',
            'service_managed' => 'Service (Managed)',
            'service_labor' => 'Service (Labor)',
            'service_fee' => 'Service (Fee)',
            'service_installation' => 'Service (Installation)',
            'accommodation' => 'Accommodation',
            'venue_booking' => 'Venue Booking',
            'event_ticket' => 'Event Ticket',
            'tour_activity' => 'Tour / Activity',
            'appointment' => 'Appointment',
            'asset_rental' => 'Asset Rental',
            'rental_with_operator' => 'Rental with Operator',
            'lease' => 'Lease',
            'air_ticket_resale' => 'Air Ticket (Agency)',
            'train_ticket_resale' => 'Train Ticket (Agency)',
            'bus_ferry_ticket_resale' => 'Bus/Ferry Ticket (Agency)',
            'hotel_resale' => 'Hotel (Agency)',
            'travel_package' => 'Travel Package',
            'shipping_charge' => 'Shipping Charge',
            'insurance_addon' => 'Insurance Add-on',
            'deposit' => 'Deposit',
            'penalty_fee' => 'Penalty Fee',
            'membership' => 'Membership',
        ];
        return $labels[$kind] ?? ucwords(str_replace('_', ' ', $kind));
    }

    /**
     * Find which group a kind belongs to
     */
    public static function getGroupForKind(string $kind): ?string
    {
        foreach (self::KIND_GROUPS as $groupKey => $group) {
            if (in_array($kind, $group['kinds'], true)) {
                return $groupKey;
            }
        }
        return null;
    }

    /**
     * Get all validation rules for API response
     */
    public static function toArray(): array
    {
        return [
            'bundle_version' => self::BUNDLE_VERSION,
            'capabilities' => self::CAPABILITIES,
            'cost_models' => self::COST_MODELS,
            'kinds' => self::KINDS,
            'kind_groups' => self::KIND_GROUPS,
            'kind_seeds' => self::KIND_SEEDS,
            'capability_matrix' => self::CAPABILITY_MATRIX,
        ];
    }
}
