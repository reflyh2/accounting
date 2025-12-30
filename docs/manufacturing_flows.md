# MANUFACTURING_FLOWS.md

## Manufacturing Flow – End-to-End

This document defines the **manufacturing lifecycle**, from planning to finished goods, and how it integrates with inventory, costing, and accounting.

---

## 1. Design Principles

- Manufacturing is **inventory-transforming**, not revenue-generating
- Costs accumulate before goods exist
- One flow supports make-to-stock and make-to-order

---

## 2. Bill of Materials (BOM)

### Purpose
Define how products are built.

### Core Tables
- bom
- bom_component

### Fields
- finished_product_id
- component_product_id
- qty_per_unit
- uom_id

---

## 3. Manufacturing Planning

### Purpose
Plan production quantities.

### Sources
- Sales Orders
- Inventory replenishment
- Manual planning

### Core Tables
- manufacturing_plan
- manufacturing_plan_line

---

## 4. Work Order (WO)

### Purpose
Authorize production.

### Core Tables
- work_order
- work_order_line

### Fields
- product_id
- planned_qty
- scheduled_start
- scheduled_end
- status (planned, released, completed)

---

## 5. Material Issue

### Purpose
Consume raw materials.

### Core Tables
- material_issue
- material_issue_line

### Accounting
- Dr WIP
- Cr Inventory

---

## 6. Production Execution

### Activities
- Labor tracking
- Machine usage
- Overhead accumulation

Costs accumulate in WIP.

---

## 7. Production Receipt

### Purpose
Receive finished goods.

### Core Tables
- production_receipt
- production_receipt_line

### Accounting
- Dr Finished Goods Inventory
- Cr WIP

---

## 8. Costing

### Cost Sources
- Raw materials
- Labor
- Overhead allocations

Supports standard or actual costing.

---

## 9. Summary Flow

BOM → Manufacturing Plan → Work Order → Material Issue → Production Receipt

Manufacturing integrates tightly with inventory and costing modules.

