# PRICING.md

## Pricing & Tax Resolution

This document explains how prices and taxes are resolved.

---

## 1. Pricing Model

### price_list / price_list_item
- Prices defined per product or variant.
- Validity dates supported.

### Targeting
Prices can target:
- partner
- partner_group
- company

Precedence:
```
Partner+Company → Partner → Group+Company → Group → Company → Default
```

---

## 2. Price Resolution Flow

1. Identify applicable price lists by date.
2. Filter by partner / group / company.
3. Select highest precedence rule.
4. Convert price using UOM rules.

---

## 3. Tax Resolution

### tax_category
- Semantic classification (VATable, Exempt, etc.)

### tax_rule
- Jurisdiction + date + B2B/B2C

Tax is resolved at invoice time.

---

## 4. Non‑Negotiable Rules

1. No price math in controllers.
2. Price is snapshotted on document lines.
3. Tax rules must be auditable.

---

