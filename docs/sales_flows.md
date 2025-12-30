# SALES_FLOWS.md

## Sales Flow – End-to-End

This document defines the **sales lifecycle**, from customer demand to cash receipt, and how it integrates with inventory, booking, costing, and accounting.

---

## 1. Design Principles

- Sales documents express **customer intent** first
- Fulfillment may be physical, digital, time-based, or asset-based
- Revenue recognition and fulfillment are decoupled

---

## 2. Sales Quotation (Optional)

### Purpose
Offer pricing and terms without commitment.

### Core Tables
- sales_quote
- sales_quote_line

### Outcome
- Can be converted to Sales Order

---

## 3. Sales Order (SO)

### Purpose
Formal customer commitment.

### Core Tables
- sales_order
- sales_order_line

### Key Fields
- partner_id (customer)
- order_date
- currency_id
- status (draft, confirmed, fulfilled, closed)

Line-level:
- product_id
- qty
- uom_id
- unit_price

### Effects
- May reserve inventory
- May create bookings

---

## 4. Fulfillment

### Fulfillment Types

#### Inventory Goods
- Picking → Delivery
- Reduces stock

#### Booking / Rental
- Confirm booking
- Assign resources/assets

#### Services
- Service confirmation or job creation

### Core Tables
- delivery
- delivery_line
- booking
- booking_occurrence

---

## 5. Sales Invoice (AR Invoice)

### Purpose
Recognize revenue.

### Core Tables
- invoice (type = sales)
- invoice_line

### Accounting
- Dr Accounts Receivable
- Cr Revenue
- Cr Tax Payable

COGS posted according to cost_model.

---

## 6. Customer Payment

### Purpose
Record incoming cash.

### Core Tables
- payment (type = inbound)
- payment_allocation

### Characteristics
- One payment → many invoices
- Partial payments allowed

### Accounting
- Dr Cash / Bank
- Cr Accounts Receivable

---

## 7. Special Cases

### Booking-Based Sales
- Invoice may be created before or after fulfillment

### Deposits
- Initially posted as liability
- Recognized as revenue upon fulfillment

---

## 8. Summary Flow

Quotation → Sales Order → Fulfillment → AR Invoice → Payment

This flow supports **retail, services, booking, rental, and agency models**.

