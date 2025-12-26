# DOCUMENTS.md

## Business Documents & Workflows

This document defines how purchase, sales, and manufacturing documents are structured.

---

## 1. Document Architecture

### document (base)
- doc_type, status, numbering
- partner_id (supplier/customer)

### document_line
- product/variant, qty, uom, price

---

## 2. Subtype Tables

| Process | Table |
|------|------|
| Purchase | purchase_order |
| Sales | sales_order |
| Manufacturing | work_order |

Each subtype adds only process‑specific fields.

---

## 3. Purchase Flow

```
PO → GRN → AP Invoice
```

- GRN posts inventory receipt
- Invoice reconciles pricing

---

## 4. Sales Flow

```
SO → Delivery → AR Invoice
```

- Delivery posts inventory issue & COGS

---

## 5. Manufacturing Flow

```
BOM → Work Order → Issue → Receipt
```

- Issue moves inventory to WIP
- Receipt moves FG to stock

---

## 6. Status Enforcement

- Status transitions are validated in services.
- Closed documents are immutable.

---

## 7. Accounting Hooks

- Each posting emits an accounting event.
- GL mapping is external to document logic.

---

