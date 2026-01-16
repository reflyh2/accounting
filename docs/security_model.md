# SECURITY_MODEL.md

## ERP Security & Access Control Model

This document defines the **security architecture** for the ERP, covering authentication, authorization, segregation of duties, approvals, auditability, and multi-entity isolation.

---

## 1. Core Principles

1. **Least Privilege** – users only get access they need
2. **Segregation of Duties (SoD)** – no single user can complete a full financial cycle alone
3. **Entity Isolation** – tenant, company, and branch data boundaries are enforced
4. **Auditability** – every critical action is traceable
5. **Policy-Driven, Not Hardcoded** – permissions are data, not code

---

## 2. Identity Model

### Entities

- tenant
- company
- branch
- user

### Relationships

- A user belongs to a tenant
- A user can belong to multiple companies
- A user can have roles per company and per branch

Tables (conceptual):
- users
- user_companies
- user_branches

---

## 3. Role-Based Access Control (RBAC)

### Core Tables

- roles
- permissions
- role_permissions
- user_roles

### Permission Granularity

Permissions are defined at:

- module (e.g. purchase, sales, inventory)
- document type (e.g. purchase_order)
- action (create, read, update, delete, approve, post, cancel)

Example:

```
purchase.purchase_order.approve
accounting.invoice.post
inventory.stock.adjust
```

---

## 4. Document-Level Authorization

Each document has:

- owner_user_id
- company_id
- branch_id
- current_state

Authorization checks:

1. Tenant isolation
2. Company membership
3. Branch access
4. Role permission
5. State-based permission

Example:
- Only users with `purchase.purchase_order.approve` can move PO from DRAFT → CONFIRMED

---

## 5. Segregation of Duties (SoD)

### Mandatory Separation

| Process | Initiator | Approver | Poster |
|---|---|---|---|
| Purchase | Requester | Manager | Finance |
| Sales Credit | Sales | Finance | Finance Lead |
| Payment | AP Clerk | Finance Manager | Treasury |

Rules:
- Creator cannot approve own document
- Approver cannot be payment executor

Enforced via:
- approval_workflow
- approval_step

---

## 6. Approval Workflows

### Workflow Definition

- workflow_id
- document_type
- company_id
- min_amount
- max_amount

### Steps

- step_order
- required_role_id
- min_approvers

Supports:
- multi-level approvals
- parallel approvals
- amount-based routing

---

## 7. Field-Level Security

Some fields are restricted:

Examples:
- unit_cost
- margin
- supplier_price
- salary

Mechanism:
- field_permissions
- role_field_permissions

---

## 8. Financial Controls

### Period Locking

- accounting_period
- period_status (open, soft_closed, closed)

Rules:
- No posting in closed period
- Adjustments require special role

### Dual Control

Critical actions require two roles:

- Payment release
- Journal posting
- Master data change

---

## 9. Audit Trail

### Audit Log

All critical actions generate immutable logs:

- user_id
- action
- entity_type
- entity_id
- before_state
- after_state
- timestamp
nStored in:
- audit_log

---

## 10. Data Visibility & Masking

### Row-Level Security

Filters by:
- tenant_id
- company_id
- branch_id

### Column Masking

Examples:
- bank_account_number
- national_id

---

## 11. API & Integration Security

- OAuth2 / JWT per tenant
- Scope-based API permissions
- Webhook signing & replay protection

---

## 12. Example Enforcement Flow

1. User attempts to approve PO
2. System checks:
   - role permission
   - approval workflow
   - SoD (not creator)
   - amount threshold
   - period open
3. If valid → state transition + audit log + possible accounting posting

---

This document defines the **governance backbone** of the ERP.

