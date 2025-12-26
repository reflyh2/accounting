# ATTRIBUTE_SET_SEEDS.md

## Attribute Set Seeds (Companion to PRODUCT_TYPE_TEMPLATES.json.md v2)

This document defines recommended **attribute_set** seed data so the UI can render product details dynamically.

It is designed to match the `attribute_set_code` values referenced in:
- PRODUCT_TYPE_TEMPLATES.json.md (v2)

Attribute sets are meant to be:
- **minimal but useful by default**
- safe across industries
- extensible by admins

---

## 1) Seed List (by `attribute_set_code`)

| attribute_set_code | Label | Used by templates | Notes |
|---|---|---|---|
| goods_stock | Goods (Stock) | goods_stock_retail | Add variant axes (color/size) as needed |
| goods_nonstock | Goods (Non-Stock) | goods_nonstock_basic | Simplified goods metadata |
| consumable | Consumable | consumable_basic | Hazard/expiry optional |
| digital_good | Digital Good | digital_good_basic | Delivery/license fields |
| bundle | Bundle | bundle_package | Component hints |
| gift_card | Gift Card | gift_card | Expiry/redemption rules |
| service_professional | Service (Professional) | service_professional | Project scope/deliverables |
| service_managed | Service (Managed) | service_managed | SLA, scope, response windows |
| service_labor | Service (Labor) | service_labor | Rate rules (overtime, min hours) |
| service_fee | Service Fee | service_fee | Fee basis (per invoice/per item) |
| service_installation | Service (Installation) | service_installation | Location, prerequisites |
| accommodation | Accommodation | accommodation_room_type | Occupancy, bed type, amenities |
| venue_booking | Venue Booking | venue_booking | Capacity, equipment |
| event_ticket | Event Ticket | event_ticket_resale | Venue, seat class |
| tour_activity | Tour / Activity | tour_activity | Meeting point, inclusions |
| appointment | Appointment | appointment | Duration, buffer times |
| asset_rental | Asset Rental | asset_rental_class | Fuel policy, mileage, requirements |
| rental_with_operator | Rental + Operator | rental_with_operator | Driver/operator details |
| lease | Lease | lease | Tenor, billing frequency |
| air_ticket | Air Ticket | air_ticket_resale | Airline, route, fare class |
| train_ticket | Train Ticket | train_ticket_resale | Operator, route |
| bus_ferry_ticket | Bus/Ferry Ticket | bus_ferry_ticket_resale | Operator, route |
| hotel_resale | Hotel Resale | hotel_resale | Property, room type |
| travel_package | Travel Package | travel_package | Itinerary, inclusions |
| shipping_charge | Shipping Charge | shipping_charge | Service level, zone |
| insurance_addon | Insurance Add-on | insurance_addon | Coverage, insurer |
| deposit | Deposit | deposit | Refund rules |
| penalty_fee | Penalty Fee | penalty_fee | Trigger conditions |
| membership | Membership | membership | Duration, entitlements |

---

## 2) JSON Seed Format (Recommended)

Use this structure to seed attribute sets and attribute definitions.

```json
{
  "attribute_sets": [
    {
      "code": "goods_stock",
      "label": "Goods (Stock)",
      "description": "Physical goods tracked in inventory.",
      "attribute_defs": [
        {"code": "brand", "label": "Brand", "type": "string", "required": false},
        {"code": "model", "label": "Model", "type": "string", "required": false},
        {"code": "barcode", "label": "Barcode", "type": "string", "required": false},
        {"code": "origin_country", "label": "Country of Origin", "type": "string", "required": false}
      ],
      "variant_axes": [
        {"code": "color", "label": "Color", "type": "enum", "required": false},
        {"code": "size", "label": "Size", "type": "enum", "required": false}
      ]
    }
  ]
}
```

---

## 3) Minimal Seed Definitions (Recommended Defaults)

Below are minimal starter defs for each `attribute_set_code`. Keep them small; admins can extend.

### goods_stock
- brand (string)
- model (string)
- barcode (string)
- origin_country (string)
- warranty_months (int)
Variant axes (optional): color, size

### goods_nonstock
- brand (string)
- supplier_sku (string)
- lead_time_days (int)

### consumable
- expiry_tracking (bool)
- shelf_life_days (int)
- hazard_class (string)

### digital_good
- delivery_method (enum: download|license_key|email)
- license_type (string)
- support_included_days (int)

### bundle
- bundle_type (enum: virtual|pack)
- notes (string)

### gift_card
- expiry_days (int)
- redemption_rules (string)

### service_professional
- deliverables (text)
- estimated_days (int)
- includes_revisions (int)

### service_managed
- sla_response_hours (int)
- sla_uptime_percent (decimal)
- service_window (string)

### service_labor
- minimum_hours (decimal)
- overtime_multiplier (decimal)

### service_fee
- fee_basis (enum: per_invoice|per_item|percentage)
- percentage_rate (decimal)

### service_installation
- onsite_required (bool)
- prerequisites (text)

### accommodation
- max_occupancy (int)
- bed_type (enum)
- smoking_allowed (bool)
- amenities (json)

### venue_booking
- max_capacity (int)
- equipment_included (json)

### event_ticket
- venue_name (string)
- seat_class (string)
- gate (string)

### tour_activity
- meeting_point (string)
- inclusions (text)
- duration_minutes (int)

### appointment
- duration_minutes (int)
- buffer_minutes (int)
- location_mode (enum: onsite|online)

### asset_rental
- license_required (bool)
- min_age (int)
- mileage_limit_per_day (decimal)
- fuel_policy (enum)

### rental_with_operator
- operator_included (bool)
- operator_notes (text)

### lease
- tenor_months (int)
- billing_frequency (enum: monthly|quarterly|yearly)

### air_ticket
- airline (string)
- route (string)
- fare_class (string)

### train_ticket
- operator (string)
- route (string)

### bus_ferry_ticket
- operator (string)
- route (string)

### hotel_resale
- property_name (string)
- room_type (string)

### travel_package
- itinerary (text)
- inclusions (text)
- exclusions (text)

### shipping_charge
- service_level (string)
- zone (string)

### insurance_addon
- insurer (string)
- coverage_summary (text)

### deposit
- refundable (bool)
- refund_terms (text)

### penalty_fee
- trigger (enum: late|cancel|no_show|damage)
- notes (text)

### membership
- duration_unit (enum: month|year)
- duration_value (int)
- entitlements (json)

---

## 4) Implementation Notes

- Attribute sets are tenant-scoped, optionally company-scoped.
- Admins can extend attribute defs without migrations.
- Frontend should render fields from `attribute_defs` + `variant_axes`.
- Use PRODUCT_VALIDATION_RULES.json.md to enforce which attribute codes are required per kind.

