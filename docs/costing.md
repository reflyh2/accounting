# COSTING.md

## Cost of Goods Sold (COGS) & Cost Allocation Framework

This document complements **PRODUCT.md**, **INVENTORY.md**, **BOOKING.md**, **DOCUMENTS.md**, and **PRICING.md**.

It explains **how costs are captured, allocated, and recognized** for products that are:
- not inventory‑tracked
- partially inventory‑tracked
- asset‑backed (rental)
- resale / agency based (tickets, travel)
- professional services

The goal is to provide **accurate gross margin** across all product kinds without forcing everything into inventory.

---

## 1. Design Principles

1. **Revenue is anchored at `invoice_detail`**  
   All gross margin analysis ultimately compares revenue vs cost at the invoice line level.

2. **Inventory costing stays isolated**  
   Inventory COGS continues to be handled exclusively by `inventory_txn` + `cost_layer`.

3. **Non‑inventory COGS uses explicit cost records**  
   Costs are captured as data, not inferred.

4. **Direct first, allocation second**  
   Prefer direct attribution to invoice lines whenever possible; allocate only when unavoidable.

5. **Cost model is product‑driven**  
   Each product declares *how* its cost should be handled.

---

## 2. Cost Models

Each product (or product type template) declares a `cost_model`.

### Supported Cost Models

| Cost Model | Description | Common Use Cases |
|---------|-------------|------------------|
| `inventory_layer` | COGS derived from inventory cost layers | Retail goods, manufacturing |
| `direct_expense_per_sale` | Cost recorded per invoice line | Ticket resale, organizer fee |
| `job_costing` | Costs accumulated on a job/project | Web design, construction |
| `asset_usage_costing` | Costs tied to asset usage | Car rental, equipment hire |
| `prepaid_consumption` | Costs consumed from prepaid balance | Airline deposits, vouchers |
| `hybrid` | Combination of direct + allocated costs | Travel packages, rentals |

`product.kind` defines *what the product is*; `cost_model` defines *how margin is calculated*.

---

## 3. Core Cost Tables (Conceptual)

### cost_entry
Represents a real economic cost.

Examples:
- Fuel purchase
- Organizer ticket cost
- Asset maintenance bill
- Software subscription

Key characteristics:
- Created from AP invoices, expense claims, payroll, journals
- Never implicitly generated

### invoice_detail_cost
Links costs to revenue lines.

- Multiple costs can be attached to one invoice line
- A single cost_entry can be split across multiple invoice lines

This table is the **bridge between accounting and gross margin**.

---

## 4. Cost Objects (Where Costs Accumulate)

Not all costs can be attached to an invoice immediately.

Costs may be temporarily attached to a **cost object**, such as:
- `invoice_detail`
- `booking`
- `work_order`
- `job` / `project`
- `asset_instance`
- `cost_center`

Later, these costs can be allocated to invoice lines.

---

## 5. Direct Cost Attribution

### Definition
Costs that are **clearly and exclusively caused by a specific sale**.

### Examples
- Ticket cost payable to event organizer
- Airline per‑ticket fee
- Purchased design template for one client
- Fuel charged specifically for one rental invoice

### Behavior
- Cost is recorded as `cost_entry`
- Immediately linked to `invoice_detail` via `invoice_detail_cost`
- Recognized as COGS when the invoice is posted

This is the **preferred costing method** whenever possible.

---

## 6. Indirect Cost Allocation

### Definition
Costs that support multiple sales and cannot be directly attributed.

### Examples
- Vehicle depreciation
- Asset maintenance
- Software subscriptions
- Office rent

### Cost Pools
Indirect costs accumulate in **cost pools**:
- Asset pool (per vehicle / per class)
- Service overhead pool
- Branch overhead pool

### Allocation Rules
Costs are distributed based on rules such as:
- Rental days
- Usage hours / km
- Revenue proportion
- Quantity sold

Allocation creates `invoice_detail_cost` records without changing the original expense posting.

---

## 7. Asset‑Backed Costing (Rental)

### Revenue
- Rental invoice lines (time‑based)

### Costs
- Direct: fuel, driver, tolls (direct attribution)
- Indirect: depreciation, maintenance, insurance

### Flow
1. Asset expenses recorded normally (AP / journals)
2. Expenses linked to asset or asset class pool
3. Periodic allocation distributes cost to rental invoice lines

This enables accurate **per‑vehicle profitability**.

---

## 8. Resale / Agency Costing (Tickets & Travel)

### Ticket Sales

- Revenue: ticket invoice line
- Cost: organizer fee per ticket

Cost handling options:
- Known upfront rate (contract) → auto cost on sale
- Unknown until bill → later matching & adjustment

### Airline Deposits / Top‑ups

- Payment creates prepaid asset
- Ticket sale consumes prepaid balance
- Consumption creates `invoice_detail_cost`

This avoids inflating expenses before revenue occurs.

---

## 9. Professional Services & Job Costing

### Revenue
- Service invoice lines

### Costs
- Direct: templates, plugins, subcontractors
- Indirect: subscriptions, tools, salaries

### Flow
1. Costs recorded against a job/project
2. Job linked to invoice lines
3. Costs allocated at invoicing or period close

This enables **project margin** and **client profitability**.

---

## 10. Accounting Treatment (High Level)

### Direct Costs
- Dr COGS  
- Cr AP / Cash / Accrual

### Indirect Costs
- Initial: Dr Expense / Prepaid
- Allocation: Dr COGS / Cr Expense (or clearing account)

Inventory‑based COGS remains unchanged.

---

## 11. Reporting Outcomes

With this model, the system can report:
- Gross margin per invoice line
- Profitability per product / kind
- Profitability per asset
- Profitability per customer / project
- Revenue vs allocated overhead

---

## 12. Non‑Negotiable Rules

1. Inventory COGS and non‑inventory COGS are never mixed
2. Every non‑inventory cost must be explicit
3. Allocations must be auditable and repeatable
4. Revenue recognition and cost recognition must align
5. Product `cost_model` must be respected everywhere

---

## 13. Mental Model

- **Inventory** answers: *What do we physically have?*
- **Documents** answer: *What do we intend to do?*
- **Invoices** answer: *What did we sell?*
- **Cost entries** answer: *What did it cost us?*
- **Allocations** answer: *Who should bear that cost?*

---

This document defines the **COGS engine** for the ERP. Any feature that affects margin must align with this model.

