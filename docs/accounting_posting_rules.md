# ACCOUNTING_POSTING_RULES.md

## Accounting Posting Rules (Event-Driven)

This document defines **when and how accounting journals are generated** from operational documents.

Accounting is **event-driven**, not CRUD-driven.

---

## 1. Design Principles

1. Journals are posted only on **state transitions**
2. Operational data remains the source of truth
3. Accounting is deterministic and reversible

---

## 2. Core Posting Events

| Event | Trigger |
|---|---|
| Inventory Receipt | Goods Receipt COMPLETED |
| Inventory Issue | Delivery / Material Issue COMPLETED |
| Revenue Recognition | Sales Invoice CONFIRMED |
| Expense Recognition | Purchase Invoice CONFIRMED |
| Cash Movement | Payment CONFIRMED |

---

## 3. Purchase Flow Posting

### Goods Receipt (Accrual)

Dr Inventory / Asset / WIP
Cr GRNI (Goods Received Not Invoiced)

---

### Purchase Invoice

Dr Inventory / Asset / Expense / GRNI
Cr Accounts Payable

---

### Supplier Payment

Dr Accounts Payable
Cr Cash / Bank

---

## 4. Sales Flow Posting

### Sales Invoice

Dr Accounts Receivable
Cr Revenue
Cr Tax Payable

---

### COGS Posting

Triggered at:
- Delivery COMPLETED (inventory goods)
- Invoice CONFIRMED (non-inventory)

Dr COGS
Cr Inventory / Cost Pool / Prepaid

---

### Customer Payment

Dr Cash / Bank
Cr Accounts Receivable

---

## 5. Manufacturing Posting

### Material Issue

Dr WIP
Cr Raw Material Inventory

---

### Production Receipt

Dr Finished Goods Inventory
Cr WIP

---

## 6. Asset-Related Posting

### Asset Acquisition

Dr Asset
Cr AP / Cash

---

### Depreciation

Dr Depreciation Expense
Cr Accumulated Depreciation

---

## 7. Reversals & Adjustments

- Cancelled documents reverse journals
- Credit notes post inverse entries

---

## 8. Controls

- Journals must reference source document + line
- Period locking prevents late posting

---

This document defines the **financial truth layer** of the ERP.

