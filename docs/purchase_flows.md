# PURCHASE_FLOWS.md

## Purchase Flow – End-to-End (with Purchase Planning)

This document defines the **complete purchase lifecycle** in the ERP, from demand planning to supplier payment, and how it integrates with inventory, products, assets, and accounting.

---

## 1. Design Principles

- Purchase is **demand-driven**, not invoice-driven
- Planning and commitment happen **before money**
- Physical flow (goods) and financial flow (invoices/payments) are decoupled
- One flow supports:
  - inventory goods
  - non-stock goods
  - assets
  - services

---

## 2. Purchase Planning

### Purpose
Identify *what* needs to be purchased and *why*, before committing to suppliers.

### Sources of Demand
- Inventory replenishment (reorder point / MRP)
- Sales orders (make-to-order / drop-ship)
- Manufacturing requirements (raw materials)
- Asset acquisition plans
- Manual planning

### Core Tables (Conceptual)
- purchase_plan
- purchase_plan_line

### Key Fields
- product_id
- planned_qty
- required_date
- branch_id
- source_type (inventory | sales | manufacturing | asset | manual)
- source_ref_id

### Outcome
- Informational only
- Can be merged, split, or converted into Purchase Orders

---

## 3. Purchase Requisition (Optional)

### Purpose
Internal approval step before contacting suppliers.

### Characteristics
- Internal document
- No accounting impact

### Outcome
- Approved requisitions generate Purchase Orders

---

## 4. Purchase Order (PO)

### Purpose
Formal commitment to a supplier.

### Core Tables
- purchase_order
- purchase_order_line

### Key Fields
- partner_id (supplier)
- order_date
- expected_delivery_date
- currency_id
- status (draft, confirmed, partially_received, closed)

Line-level:
- product_id
- ordered_qty
- uom_id
- unit_price
- source_plan_line_id

### Accounting
- No journal posted
- May reserve budget

---

## 5. Receiving / Goods Receipt

### Purpose
Record physical receipt of goods or confirmation of services.

### Core Tables
- goods_receipt
- goods_receipt_line

### Behavior by Product Kind
- Inventory goods → increase stock
- Assets → create asset instance
- Services / non-stock → informational only

### Accounting
- Dr Inventory / Asset / Expense clearing
- Cr GRNI (if accrual-based)

---

## 6. Supplier Invoice (AP Invoice)

### Purpose
Record the financial obligation to the supplier.

### Core Tables
- invoice (type = purchase)
- invoice_line

### Matching
- 2-way match: PO ↔ Invoice
- 3-way match: PO ↔ Receipt ↔ Invoice

### Accounting
- Dr Inventory / Asset / Expense
- Cr Accounts Payable

---

## 7. Supplier Payment

### Purpose
Settle AP invoices.

### Core Tables
- payment (type = outbound)
- payment_allocation

### Characteristics
- One payment can settle multiple invoices
- Partial payments allowed

### Accounting
- Dr Accounts Payable
- Cr Cash / Bank

---

## 8. Special Cases

### Asset Purchases
- Receiving creates asset record
- Depreciation handled in Asset module

### Non-Stock / Drop-Ship
- No inventory impact
- Cost directly expensed or linked to sale

---

## 9. Summary Flow

Purchase Planning → PO → Receiving → AP Invoice → Payment

Planning ensures purchases are **intentional**, auditable, and scalable.

