# PRODUCT.md

## Product & Inventory Module – System Overview

This document explains **how the Products, Inventory, Booking, Rental, Purchase, Sales, and Manufacturing systems work together** in this ERP.

It is intended for **developers and AI coding agents** working on this repository.

---

## 1. Core Philosophy

- The system uses **one unified product engine** that supports multiple industries:
  - Retail goods
  - Services
  - Accommodation (hotel rooms)
  - Rentals (vehicles, equipment)
  - Packages (bundled offerings)

- The **database schema is shared**, but the **user experience is type-specific**.
- Users never configure low-level mechanics (inventory, booking, costing) manually.
- Users choose a **product type**, and the system automatically applies the correct behavior.

---

## 2. Product as the Single Source of Truth

### `product`
Represents *what is sold*.

Key fields:
- `kind`: `goods | service | accommodation | rental | package`
- `attribute_set_id`: defines dynamic attributes
- `attrs_json`: attribute values
- `tax_category_id`

Products do **not** contain inventory quantities or booking data.

---

## 3. Capabilities Drive Behavior

### `product_capability`
Capabilities activate system modules.

Common capabilities:
- `inventory_tracked`
- `variantable`
- `bookable`
- `rental`
- `serialized`
- `package`

Capabilities are assigned automatically via **Product Type Templates**.

---

## 4. Product Types (User Abstraction Layer)

Users never manage a generic product form.

They select one of the following types:

| Type | Capabilities | Active Modules |
|----|----|----|
| Goods | variantable, inventory_tracked | Inventory, Pricing |
| Service | service | Pricing |
| Accommodation | bookable | Booking, Availability |
| Rental | rental, bookable, serialized | Booking, Rental, Assets |
| Package | package | Pricing (future BOM-like) |

Each type is defined by a **Product Type Template** that specifies:
- capability bundle
- attribute set
- form layout
- post-create actions

---

## 5. Attribute System (Flexibility Layer)

### `attribute_set` / `attribute_def`
Defines dynamic, industry-specific fields without schema changes.

Examples:
- Retail: `color`, `size`, `material`
- Hotel: `bed_type`, `view`, `max_occupancy`
- Rental: `transmission`, `fuel`, `seats`

### `attrs_json`
Stores actual attribute values on:
- `product` (defaults)
- `product_variant` (overrides)

Resolution order:
```
product_variant → product → default_value
```

Attributes control:
- UI form generation
- Validation
- Variant generation
- Filtering & search

---

## 6. Variants (Goods Only)

### `product_variant`
- Represents a concrete SKU
- Generated from variant-axis attributes (e.g. color × size)
- Inventory is always tracked at the **variant level**

---

## 7. Inventory Model (Event-Based)

Inventory is **immutable and event-driven**.

### `inventory_txn` / `inventory_txn_line`
- The **only way stock moves**
- Types: `receipt`, `issue`, `adjustment`, `transfer`

Triggered by:
- Purchase receipt (GRN)
- Sales delivery
- Manufacturing issue / receipt
- Manual adjustment

### `inventory_item`
Cached balances per `(variant, location, lot?, serial?)`:
- `qty_on_hand`
- `qty_reserved`

### `cost_layer`
- Tracks inventory valuation (FIFO or Moving Average)
- Created on receipt, consumed on issue
- Used for COGS and manufacturing costing

> Orders and invoices never directly change stock.

---

## 8. Purchase & Sales Flows

### Purchase
```
Purchase Order → Goods Receipt → AP Invoice
```
- GRN creates `inventory_txn(receipt)` + cost layers
- AP Invoice reconciles price differences (PPV or revaluation)

### Sales
```
Sales Order → Delivery → AR Invoice
```
- Sales Order may reserve stock
- Delivery creates `inventory_txn(issue)` and posts COGS
- Invoice posts revenue and tax

---

## 9. Manufacturing Flow

```
BOM → Work Order → Component Issue → FG Receipt
```

- Components issued via `inventory_txn(issue)`
- Finished goods received via `inventory_txn(receipt)`
- FG unit cost = (materials + labor + overhead) / good_qty
- WIP is conceptual or represented by a WIP location

---

## 10. Booking & Rental Model

### `resource_pool`
Groups similar resources (room type, vehicle class).

### `resource_instance`
Represents physical units (room #101, car VIN).
May link to asset management.

### `availability_rule`
Defines open/close/blackout windows.

### `occurrence` (optional)
Time buckets (hotel nights, showtimes).

### `booking` / `booking_line`
- booking = reservation header
- booking_line = booked item, time range, price

### `booking_line_resource`
Assigns specific instances.

**Hard rule:**
- No double booking (enforced via Postgres EXCLUDE constraint)

---

## 11. Pricing & Tax (Overview)

- Pricing resolved via `price_list` + `price_list_item`
- Targeting via partner, partner group, and company precedence
- Tax resolved via:
  - `tax_category`
  - `tax_rule` (jurisdiction, date, B2B/B2C)

---

## 12. Partner Groups

- Partners can belong to multiple groups
- Groups belong to **namespaces**:
  - Exclusive (e.g. Bronze/Silver/Gold)
  - Non-exclusive (e.g. Reseller, NGO)
- Memberships may be tenant-wide or company-specific
- Used primarily for pricing and segmentation

---

## 13. Document Architecture

- Shared base tables:
  - `document`
  - `document_line`

- Process-specific tables:
  - `purchase_order`
  - `sales_order`
  - `work_order`

- Inventory movement is always externalized to `inventory_txn`

---

## 14. UX & Implementation Rules

- Laravel 11 backend
- InertiaJS + Vue 3 frontend
- Type-specific routes:
  - `/catalog/goods`
  - `/catalog/services`
  - `/catalog/accommodation`
  - `/catalog/rental`

- Controllers are thin
- Business logic lives in services
- Inventory, Booking, Pricing must be accessed only via services

---

## 15. Non-Negotiable Rules

1. No stock change without `inventory_txn`
2. No booking without availability validation
3. No pricing logic in controllers
4. No cross-company stock leakage
5. Attributes must conform to `attribute_def`
6. Variants exist only for goods
7. Bookable products never use inventory quantities

---

## 16. Mental Model

- **Product** = what is sold
- **Capability** = which systems activate
- **Inventory Txn** = stock movement
- **Cost Layer** = valuation
- **Booking** = time allocation
- **Document** = business intent
- **Service Layer** = business truth

---

This file is the **authoritative reference** for how the product module works. Any implementation should align with the concepts and constraints described above.

