<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AttributeSet;
use App\Models\AttributeDef;
use App\Models\Company;

/**
 * AttributeSetsSeeder v2
 *
 * Seeds all 28 attribute sets per ATTRIBUTE_SET_SEEDS.md specification.
 */
class AttributeSetsSeeder extends Seeder
{
    public function run(): void
    {
        $sets = $this->getAttributeSets();

        foreach ($sets as $setCode => $setData) {
            $set = AttributeSet::query()->updateOrCreate(['code' => $setCode], [
                'name' => $setData['label'],
            ]);

            // Sync to all companies
            $companies = Company::get()->pluck('id');
            $set->companies()->sync($companies);

            // Create attribute definitions
            foreach ($setData['attributes'] as $def) {
                AttributeDef::query()->updateOrCreate(
                    ['attribute_set_id' => $set->id, 'code' => $def['code']],
                    [
                        'label' => $def['label'],
                        'data_type' => $def['type'],
                        'is_required' => $def['required'] ?? false,
                        'is_variant_axis' => $def['is_variant_axis'] ?? false,
                        'options_json' => $def['options'] ?? null,
                        'default_value' => $def['default'] ?? null,
                    ]
                );
            }
        }
    }

    private function getAttributeSets(): array
    {
        return [
            // ===== TRADE / GOODS =====
            'goods_stock' => [
                'label' => 'Goods (Stock)',
                'attributes' => [
                    ['code' => 'brand', 'label' => 'Brand', 'type' => 'string', 'required' => false],
                    ['code' => 'model', 'label' => 'Model', 'type' => 'string', 'required' => false],
                    ['code' => 'barcode', 'label' => 'Barcode', 'type' => 'string', 'required' => false],
                    ['code' => 'origin_country', 'label' => 'Country of Origin', 'type' => 'string', 'required' => false],
                    ['code' => 'warranty_months', 'label' => 'Warranty (months)', 'type' => 'number', 'required' => false],
                    ['code' => 'color', 'label' => 'Color', 'type' => 'string', 'is_variant_axis' => true],
                    ['code' => 'size', 'label' => 'Size', 'type' => 'string', 'is_variant_axis' => true],
                ],
            ],
            'goods_nonstock' => [
                'label' => 'Goods (Non-Stock)',
                'attributes' => [
                    ['code' => 'brand', 'label' => 'Brand', 'type' => 'string', 'required' => false],
                    ['code' => 'supplier_sku', 'label' => 'Supplier SKU', 'type' => 'string', 'required' => false],
                    ['code' => 'lead_time_days', 'label' => 'Lead Time (days)', 'type' => 'number', 'required' => false],
                ],
            ],
            'consumable' => [
                'label' => 'Consumable',
                'attributes' => [
                    ['code' => 'expiry_tracking', 'label' => 'Track Expiry', 'type' => 'boolean', 'required' => false],
                    ['code' => 'shelf_life_days', 'label' => 'Shelf Life (days)', 'type' => 'number', 'required' => false],
                    ['code' => 'hazard_class', 'label' => 'Hazard Class', 'type' => 'string', 'required' => false],
                ],
            ],
            'digital_good' => [
                'label' => 'Digital Good',
                'attributes' => [
                    ['code' => 'delivery_method', 'label' => 'Delivery Method', 'type' => 'select', 'required' => true, 'options' => ['download', 'license_key', 'email']],
                    ['code' => 'license_type', 'label' => 'License Type', 'type' => 'string', 'required' => false],
                    ['code' => 'support_included_days', 'label' => 'Support Included (days)', 'type' => 'number', 'required' => false],
                ],
            ],
            'bundle' => [
                'label' => 'Bundle',
                'attributes' => [
                    ['code' => 'bundle_type', 'label' => 'Bundle Type', 'type' => 'select', 'required' => false, 'options' => ['virtual', 'pack']],
                    ['code' => 'notes', 'label' => 'Notes', 'type' => 'string', 'required' => false],
                ],
            ],
            'gift_card' => [
                'label' => 'Gift Card',
                'attributes' => [
                    ['code' => 'expiry_days', 'label' => 'Expiry (days)', 'type' => 'number', 'required' => true],
                    ['code' => 'redemption_rules', 'label' => 'Redemption Rules', 'type' => 'string', 'required' => false],
                ],
            ],

            // ===== SERVICES =====
            'service_professional' => [
                'label' => 'Service (Professional)',
                'attributes' => [
                    ['code' => 'deliverables', 'label' => 'Deliverables', 'type' => 'text', 'required' => false],
                    ['code' => 'estimated_days', 'label' => 'Estimated Days', 'type' => 'number', 'required' => false],
                    ['code' => 'includes_revisions', 'label' => 'Revisions Included', 'type' => 'number', 'required' => false],
                ],
            ],
            'service_managed' => [
                'label' => 'Service (Managed)',
                'attributes' => [
                    ['code' => 'sla_response_hours', 'label' => 'SLA Response (hours)', 'type' => 'number', 'required' => true],
                    ['code' => 'sla_uptime_percent', 'label' => 'SLA Uptime (%)', 'type' => 'number', 'required' => false],
                    ['code' => 'service_window', 'label' => 'Service Window', 'type' => 'string', 'required' => false],
                ],
            ],
            'service_labor' => [
                'label' => 'Service (Labor)',
                'attributes' => [
                    ['code' => 'minimum_hours', 'label' => 'Minimum Hours', 'type' => 'number', 'required' => true],
                    ['code' => 'overtime_multiplier', 'label' => 'Overtime Multiplier', 'type' => 'number', 'required' => false, 'default' => 1.5],
                ],
            ],
            'service_fee' => [
                'label' => 'Service Fee',
                'attributes' => [
                    ['code' => 'fee_basis', 'label' => 'Fee Basis', 'type' => 'select', 'required' => true, 'options' => ['per_invoice', 'per_item', 'percentage']],
                    ['code' => 'percentage_rate', 'label' => 'Percentage Rate', 'type' => 'number', 'required' => false],
                ],
            ],
            'service_installation' => [
                'label' => 'Service (Installation)',
                'attributes' => [
                    ['code' => 'onsite_required', 'label' => 'Onsite Required', 'type' => 'boolean', 'required' => false],
                    ['code' => 'prerequisites', 'label' => 'Prerequisites', 'type' => 'text', 'required' => false],
                ],
            ],

            // ===== BOOKING / CAPACITY / EVENTS =====
            'accommodation' => [
                'label' => 'Accommodation',
                'attributes' => [
                    ['code' => 'max_occupancy', 'label' => 'Max Occupancy', 'type' => 'number', 'required' => true],
                    ['code' => 'bed_type', 'label' => 'Bed Type', 'type' => 'select', 'required' => false, 'options' => ['single', 'double', 'queen', 'king', 'twin']],
                    ['code' => 'smoking_allowed', 'label' => 'Smoking Allowed', 'type' => 'boolean', 'required' => false],
                    ['code' => 'amenities', 'label' => 'Amenities', 'type' => 'json', 'required' => false],
                ],
            ],
            'venue_booking' => [
                'label' => 'Venue Booking',
                'attributes' => [
                    ['code' => 'max_capacity', 'label' => 'Max Capacity', 'type' => 'number', 'required' => true],
                    ['code' => 'equipment_included', 'label' => 'Equipment Included', 'type' => 'json', 'required' => false],
                ],
            ],
            'event_ticket' => [
                'label' => 'Event Ticket',
                'attributes' => [
                    ['code' => 'venue_name', 'label' => 'Venue Name', 'type' => 'string', 'required' => true],
                    ['code' => 'seat_class', 'label' => 'Seat Class', 'type' => 'string', 'required' => false],
                    ['code' => 'gate', 'label' => 'Gate', 'type' => 'string', 'required' => false],
                ],
            ],
            'tour_activity' => [
                'label' => 'Tour / Activity',
                'attributes' => [
                    ['code' => 'meeting_point', 'label' => 'Meeting Point', 'type' => 'string', 'required' => true],
                    ['code' => 'inclusions', 'label' => 'Inclusions', 'type' => 'text', 'required' => false],
                    ['code' => 'duration_minutes', 'label' => 'Duration (minutes)', 'type' => 'number', 'required' => false],
                ],
            ],
            'appointment' => [
                'label' => 'Appointment',
                'attributes' => [
                    ['code' => 'duration_minutes', 'label' => 'Duration (minutes)', 'type' => 'number', 'required' => true],
                    ['code' => 'buffer_minutes', 'label' => 'Buffer (minutes)', 'type' => 'number', 'required' => false],
                    ['code' => 'location_mode', 'label' => 'Location Mode', 'type' => 'select', 'required' => false, 'options' => ['onsite', 'online']],
                ],
            ],

            // ===== RENTAL / HIRE =====
            'asset_rental' => [
                'label' => 'Asset Rental',
                'attributes' => [
                    ['code' => 'license_required', 'label' => 'License Required', 'type' => 'boolean', 'required' => false],
                    ['code' => 'min_age', 'label' => 'Minimum Age', 'type' => 'number', 'required' => false],
                    ['code' => 'mileage_limit_per_day', 'label' => 'Mileage Limit/Day', 'type' => 'number', 'required' => false],
                    ['code' => 'fuel_policy', 'label' => 'Fuel Policy', 'type' => 'select', 'required' => false, 'options' => ['full_to_full', 'same_to_same', 'prepay']],
                ],
            ],
            'rental_with_operator' => [
                'label' => 'Rental with Operator',
                'attributes' => [
                    ['code' => 'operator_included', 'label' => 'Operator Included', 'type' => 'boolean', 'required' => false, 'default' => true],
                    ['code' => 'operator_notes', 'label' => 'Operator Notes', 'type' => 'text', 'required' => false],
                ],
            ],
            'lease' => [
                'label' => 'Lease',
                'attributes' => [
                    ['code' => 'tenor_months', 'label' => 'Tenor (months)', 'type' => 'number', 'required' => true],
                    ['code' => 'billing_frequency', 'label' => 'Billing Frequency', 'type' => 'select', 'required' => false, 'options' => ['monthly', 'quarterly', 'yearly']],
                ],
            ],

            // ===== TRAVEL / TRANSPORT (AGENCY) =====
            'air_ticket' => [
                'label' => 'Air Ticket',
                'attributes' => [
                    ['code' => 'airline', 'label' => 'Airline', 'type' => 'string', 'required' => true],
                    ['code' => 'route', 'label' => 'Route', 'type' => 'string', 'required' => false],
                    ['code' => 'fare_class', 'label' => 'Fare Class', 'type' => 'string', 'required' => false],
                ],
            ],
            'train_ticket' => [
                'label' => 'Train Ticket',
                'attributes' => [
                    ['code' => 'operator', 'label' => 'Operator', 'type' => 'string', 'required' => true],
                    ['code' => 'route', 'label' => 'Route', 'type' => 'string', 'required' => false],
                ],
            ],
            'bus_ferry_ticket' => [
                'label' => 'Bus/Ferry Ticket',
                'attributes' => [
                    ['code' => 'operator', 'label' => 'Operator', 'type' => 'string', 'required' => true],
                    ['code' => 'route', 'label' => 'Route', 'type' => 'string', 'required' => false],
                ],
            ],
            'hotel_resale' => [
                'label' => 'Hotel Resale',
                'attributes' => [
                    ['code' => 'property_name', 'label' => 'Property Name', 'type' => 'string', 'required' => true],
                    ['code' => 'room_type', 'label' => 'Room Type', 'type' => 'string', 'required' => false],
                ],
            ],
            'travel_package' => [
                'label' => 'Travel Package',
                'attributes' => [
                    ['code' => 'itinerary', 'label' => 'Itinerary', 'type' => 'text', 'required' => true],
                    ['code' => 'inclusions', 'label' => 'Inclusions', 'type' => 'text', 'required' => false],
                    ['code' => 'exclusions', 'label' => 'Exclusions', 'type' => 'text', 'required' => false],
                ],
            ],

            // ===== FINANCIAL / UTILITY / OTHER =====
            'shipping_charge' => [
                'label' => 'Shipping Charge',
                'attributes' => [
                    ['code' => 'service_level', 'label' => 'Service Level', 'type' => 'string', 'required' => false],
                    ['code' => 'zone', 'label' => 'Zone', 'type' => 'string', 'required' => false],
                ],
            ],
            'insurance_addon' => [
                'label' => 'Insurance Add-on',
                'attributes' => [
                    ['code' => 'insurer', 'label' => 'Insurer', 'type' => 'string', 'required' => true],
                    ['code' => 'coverage_summary', 'label' => 'Coverage Summary', 'type' => 'text', 'required' => false],
                ],
            ],
            'deposit' => [
                'label' => 'Deposit',
                'attributes' => [
                    ['code' => 'refundable', 'label' => 'Refundable', 'type' => 'boolean', 'required' => true],
                    ['code' => 'refund_terms', 'label' => 'Refund Terms', 'type' => 'text', 'required' => false],
                ],
            ],
            'penalty_fee' => [
                'label' => 'Penalty Fee',
                'attributes' => [
                    ['code' => 'trigger', 'label' => 'Trigger', 'type' => 'select', 'required' => true, 'options' => ['late', 'cancel', 'no_show', 'damage']],
                    ['code' => 'notes', 'label' => 'Notes', 'type' => 'text', 'required' => false],
                ],
            ],
            'membership' => [
                'label' => 'Membership',
                'attributes' => [
                    ['code' => 'duration_unit', 'label' => 'Duration Unit', 'type' => 'select', 'required' => true, 'options' => ['month', 'year']],
                    ['code' => 'duration_value', 'label' => 'Duration Value', 'type' => 'number', 'required' => true],
                    ['code' => 'entitlements', 'label' => 'Entitlements', 'type' => 'json', 'required' => false],
                ],
            ],
        ];
    }
}
