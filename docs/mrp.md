# MRP.md

## Material Requirements Planning (MRP)

This document defines how the system **automatically generates purchase and manufacturing demand** based on inventory levels, sales orders, and production plans.

---

## 1. Design Goals

- Prevent stockouts
- Minimize excess inventory
- Support make-to-stock and make-to-order

---

## 2. Demand Sources

| Source | Description |
|---|---|
| Inventory | Reorder point / safety stock |
| Sales Orders | Committed customer demand |
| Manufacturing | BOM component demand |
| Assets | Spare parts & consumables |

---

## 3. Supply Sources

| Source | Description |
|---|---|
| On-hand stock | Current inventory |
| Open Purchase Orders | Expected receipts |
| Open Work Orders | Expected production |

---

## 4. Net Requirements Calculation

For each product:

```text
Net Requirement = Demand − (On Hand + Incoming Supply)
```

Only positive net requirements generate actions.

---

## 5. Planning Outputs

### Purchase Demand

Generated when:
- product.kind = goods_stock or consumable
- Net requirement > 0

Creates:
- purchase_plan_line

---

### Manufacturing Demand

Generated when:
- product has BOM
- Net requirement > 0

Creates:
- manufacturing_plan_line

---

## 6. Planning Parameters

| Parameter | Description |
|---|---|
| reorder_point | Minimum stock |
| reorder_qty | Preferred lot size |
| lead_time_days | Supplier / production lead time |
| safety_stock | Buffer quantity |

---

## 7. Explosion Logic (BOM)

Manufacturing demand triggers **BOM explosion**:

- Finished good demand
- Component demand generated recursively

---

## 8. Planning Cycle

1. Collect demand
2. Collect supply
3. Calculate net requirements
4. Generate plans
5. Review & approve
6. Convert plans to PO / WO

---

## 9. Manual Overrides

- Planner can adjust quantities
- Plans can be split or merged

---

## 10. Integration Points

- Purchase Plans → Purchase Orders
- Manufacturing Plans → Work Orders
- Inventory → Real-time feedback

---

This document defines the **automation brain** of procurement and production.

