# PRODUCT.md (v2)

## Product, Capability & Costing Taxonomy

This document is the **authoritative description of how products are modeled** in this ERP.
It supersedes PRODUCT.md v1 and clarifies the **taxonomy**, **capability system**, and how products relate to **inventory, booking, rental, documents, and costing**.

It must be read together with:
- INVENTORY.md
- BOOKING.md
- DOCUMENTS.md
- PRICING.md
- COSTING.md

---

## 1. Core Philosophy

1. **One Product Engine, Many Businesses**  
   A single `product` model supports retail, services, rental, travel, events, and agency models.

2. **Classification ≠ Behavior**  
   - `product.kind` describes *what the product is* (semantic taxonomy)
   - `product_capability` defines *what systems activate*
   - `product.cost_model` defines *how costs and margin are calculated*

3. **Explicit Over Implicit**  
   Inventory, booking, pricing, and costing are never inferred. They are always driven by explicit records and services.

---

## 2. Mental Model (Big Picture)

```
                         ┌────────────┐
                         │  Product   │
                         │ (what it is)│
                         └─────┬──────┘
                               │
        ┌───────────────┬───────┼───────────────┬───────────────┐
        │               │       │               │               │
┌───────▼───────┐ ┌─────▼─────┐ ┌─────▼─────┐ ┌─────▼─────┐ ┌─────▼─────┐
│ product.kind  │ │Capability │ │ Cost Model │ │ Attributes │ │ Variants  │
│ (taxonomy)    │ │ (behavior)│ │ (margin)   │ │ (flexible) │ │ (SKUs)    │
└───────────────┘ └───────────┘ └───────────┘ └───────────┘ └───────────┘
                               │
               ┌───────────────┴───────────────┐
               │                               │
        ┌──────▼──────┐                 ┌──────▼──────┐
        │   Documents  │                 │   Operations │
        │ (PO/SO/WO)   │                 │ Inventory /  │
        └─────────────┘                 │ Booking /    │
                                         │ Assets       │
                                         └─────────────┘
```

---

## 3. The Product Model (Single Source of Truth)

### product
Represents **something that can be sold**.

Key fields:
- `kind` – semantic classification (see section 4)
- `attribute_set_id` – dynamic attributes
- `attrs_json` – attribute values
- `tax_category_id`
- `cost_model` – how COGS is handled

A product **never stores**:
- inventory quantities
- booking allocations
- cost values

---

## 4. Exhaustive `product.kind` Taxonomy

`product.kind` is a **semantic taxonomy** used for:
- UX defaults and grouping
- product templates
- analytics and reporting

⚠️ Business logic MUST NOT branch on `product.kind`.
Logic depends on **capabilities** and **cost_model**.

### 4.1 Trade / Goods

| kind | Description | Examples |
|-----|-------------|----------|
| goods_stock | Physical goods tracked in inventory | Retail items, spare parts |
| goods_nonstock | Tangible goods not inventory-tracked | Drop-ship items |
| consumable | Low-value goods, optional stock | Fuel, office supplies |
| digital_good | Intangible digital goods | License keys, downloads |
| bundle | Logical grouping of items | Promo kits |
| gift_card | Stored-value, deferred revenue | Vouchers |

### 4.2 Services

| kind | Description | Examples |
|-----|-------------|----------|
| service_professional | Project-based services | Consulting, design |
| service_managed | Ongoing managed services | IT maintenance |
| service_labor | Time-based labor | Technician hours |
| service_fee | Transactional fees | Admin, convenience |
| service_installation | Setup tied to goods | Equipment install |

### 4.3 Booking / Capacity / Events

| kind | Description | Examples |
|-----|-------------|----------|
| accommodation | Lodging by night/stay | Hotel rooms |
| venue_booking | Space rental by time | Meeting rooms |
| event_ticket | Capacity-based access | Concert tickets |
| tour_activity | Scheduled departures | Tours |
| appointment | Limited slots | Medical visits |

### 4.4 Rental / Hire

| kind | Description | Examples |
|-----|-------------|----------|
| asset_rental | Rental of owned assets | Cars, equipment |
| rental_with_operator | Rental + labor | Car + driver |
| lease | Long-term rental | Vehicle lease |

### 4.5 Travel / Transport (Agency Model)

| kind | Description | Examples |
|-----|-------------|----------|
| air_ticket_resale | Airline ticket agency | Flight tickets |
| train_ticket_resale | Rail ticket agency | Train tickets |
| bus_ferry_ticket_resale | Bus/ferry agency | Ferry tickets |
| hotel_resale | Hotel agency | OTA model |
| travel_package | Bundled travel | Flight + hotel |

### 4.6 Financial / Utility / Other

| kind | Description | Examples |
|-----|-------------|----------|
| shipping_charge | Logistics fees | Delivery fee |
| insurance_addon | Optional insurance | Travel insurance |
| deposit | Refundable deposit | Rental deposit |
| penalty_fee | Charges for violation | Late fee |
| membership | Non-stored entitlement | Club access |

---

## 5. Capabilities (Behavior Switches)

Capabilities activate system modules.

Common capabilities:
- `inventory_tracked`
- `variantable`
- `bookable`
- `rental`
- `serialized`
- `package`
- `deliverable`

Capabilities are assigned automatically by **Product Type Templates**.

---

## 6. Cost Models (Margin Logic)

Costing behavior is defined by `product.cost_model` (see COSTING.md).

Examples:
- goods_stock → `inventory_layer`
- air_ticket_resale → `direct_expense_per_sale`
- asset_rental → `asset_usage_costing`
- service_professional → `job_costing`
- travel_package → `hybrid`

---

## 7. Attributes & Variants

- Attributes are defined by `attribute_set` and stored in `attrs_json`.
- Variants exist **only** for goods-like products.
- Resolution order:
```
variant → product → default
```

---

## 8. How Products Interact With Other Modules

| Module | Interaction |
|------|-------------|
| Inventory | Via inventory_txn (never directly) |
| Booking | Via resource_pool / booking |
| Rental | Via assets + booking |
| Documents | Via document + document_line |
| Pricing | Via price_list resolution |
| Costing | Via invoice_detail_cost |

---

## 9. Non-Negotiable Rules

1. `product.kind` never controls logic
2. Capabilities activate modules
3. Cost model controls margin logic
4. Inventory only moves via inventory_txn
5. Booking never touches inventory
6. Revenue and cost must reconcile per invoice line

---

## 10. Summary

- **product.kind** = semantic taxonomy
- **capabilities** = system behavior
- **cost_model** = margin logic
- **documents** = business intent
- **operations** = inventory / booking / assets

This separation is the foundation that allows the ERP to support **retail, rental, travel, services, and hybrid businesses** in a single coherent model.

