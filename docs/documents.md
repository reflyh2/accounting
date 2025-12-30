# DOCUMENTS.md

## Unified Document State Machine

This document defines a **single, unified document model** used across purchasing, sales, manufacturing, inventory, booking, and accounting.

The goal is to:
- standardize document lifecycle behavior
- reduce duplicated logic
- enable consistent automation, auditability, and integrations

---

## 1. Core Principles

1. **All business processes are documents**
2. Documents express *intent*, *execution*, or *financial fact*
3. State transitions are explicit and auditable
4. Accounting is triggered by state transitions, not by CRUD

---

## 2. Core Document Types

| Category | Examples |
|---|---|
| Planning | purchase_plan, manufacturing_plan |
| Commitment | purchase_order, sales_order, work_order |
| Execution | goods_receipt, delivery, booking |
| Financial | invoice, payment |
| Adjustment | credit_note, debit_note |

---

## 3. Universal Document States

All documents use the same **state machine**.

```text
DRAFT → CONFIRMED → IN_PROGRESS → COMPLETED → CLOSED
           ↓
        CANCELLED
```

### State Definitions

| State | Meaning |
|---|---|
| DRAFT | Editable, no impact |
| CONFIRMED | Business commitment exists |
| IN_PROGRESS | Execution underway |
| COMPLETED | Execution finished |
| CLOSED | Financially settled |
| CANCELLED | Void, no further action |

---

## 4. State Transition Rules

- Only forward transitions allowed (except CANCELLED)
- Transitions may trigger:
  - inventory movements
  - booking allocations
  - accounting journals

---

## 5. Line-Level States

Line items may have **independent progress**.

Example:
- PO partially received
- SO partially delivered

Line states mirror document states.

---

## 6. Cross-Document Links

Documents link explicitly:

- purchase_plan → purchase_order
- purchase_order → goods_receipt → invoice
- sales_order → delivery → invoice
- work_order → material_issue → production_receipt

No implicit inference is allowed.

---

## 7. Accounting Triggers

| Document | Trigger State | Action |
|---|---|---|
| Goods Receipt | COMPLETED | Accrual / GRNI |
| Invoice | CONFIRMED | AR/AP posted |
| Payment | CONFIRMED | Cash movement |

---

## 8. Benefits

- One state machine for entire ERP
- Predictable automation
- Easy reporting and reconciliation

---

This document is the **backbone of all flows**.

