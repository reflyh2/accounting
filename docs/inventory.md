# INVENTORY.md

## Inventory & Costing – System Design

This document explains how inventory quantities, reservations, and costs are managed.
It applies to **Goods** and **Manufacturing**, and explicitly **does not** apply to bookable/rental products.

---

## 1. Core Principles

1. Inventory is **event‑based and immutable**.
2. Stock changes only via `inventory_txn`.
3. Valuation is tracked via `cost_layer` (FIFO or Moving Average).
4. Documents (PO, SO, WO) express *intent*; inventory expresses *reality*.

---

## 2. Core Tables & Roles

### inventory_txn / inventory_txn_line
- Single source of truth for stock movement.
- Types:
  - `receipt`
  - `issue`
  - `adjustment`
  - `transfer`

### inventory_item
- Cached balance per `(variant, location, lot?, serial?)`.
- Fields:
  - `qty_on_hand`
  - `qty_reserved`

### cost_layer
- Created on receipt.
- Consumed on issue.
- Fields:
  - `qty_remaining`
  - `unit_cost`

---

## 3. Inventory Movements

### Receipt
Triggered by:
- Purchase GRN
- Manufacturing FG receipt
- Sales return

Effect:
- `qty_on_hand += qty`
- New cost_layer created

### Issue
Triggered by:
- Sales delivery
- Manufacturing component issue
- Purchase return

Effect:
- Consume cost layers (FIFO/Avg)
- `qty_on_hand -= qty`

### Adjustment
Triggered by:
- Stock count
- Shrinkage / damage

### Transfer
Triggered by:
- Inter‑location movement

---

## 4. Reservations

- Reservation is optional and happens at **document level** (e.g. Sales Order).
- Implemented by incrementing `inventory_item.qty_reserved`.
- Released on shipment or cancellation.

Available Qty:
```
available = qty_on_hand - qty_reserved
```

---

## 5. Costing Methods

### FIFO
- Consume oldest open layers first.
- Accurate but more IO.

### Moving Average
- Recalculate average on receipt.
- Issue at current average.

Costing method is configurable per company.

---

## 6. Manufacturing Interaction

- Component issue consumes layers → material cost accumulated.
- FG receipt creates new cost_layer with calculated unit cost.

---

## 7. Non‑Negotiable Rules

1. No negative stock unless explicitly allowed.
2. No manual editing of `inventory_item`.
3. No cost edits outside inventory service.

---

