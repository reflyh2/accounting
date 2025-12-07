# Database Documentation

This document provides a comprehensive overview of the accounting system's database schema, including all tables, their purposes, fields, and relationships.

## Overview

This is a multi-tenant accounting system built with Laravel that handles comprehensive business operations including sales, purchases, inventory, manufacturing, assets, and financial accounting.

## Core Tables

### Companies & Branches
- **companies**: Stores company information and settings
- **branches**: Company branches/locations
- **companies**: Operational settings per company

### Users & Partners
- **partners**: Business partners (customers, suppliers, vendors)
- **partner_groups**: Grouping of partners for pricing/discounts
- **partner_bank_accounts**: Banking information for partners

### Products & Inventory
- **products**: Product catalog with categories and attributes
- **product_variants**: Product variations (SKU, barcode, attributes)
- **product_capabilities**: Product features (inventory_tracked, variantable, etc.)
- **product_suppliers**: Supplier information per product
- **company_product**: Company-specific product assignments

### Units of Measure
- **uoms**: Units of measure definitions
- **uom_conversions**: Conversion rates between units
- **product_uoms**: Product-specific unit relationships
- **uom_conversion_rules**: Layered conversion rules

### Pricing
- **price_lists**: Pricing structures for different customer segments
- **price_list_items**: Individual prices for products/variants
- **price_list_targets**: Price list assignments to partners

### Inventory Management
- **locations**: Warehouse/storage locations
- **lots**: Lot tracking for inventory
- **serials**: Serial number tracking
- **inventory_items**: Current inventory quantities
- **inventory_transactions**: Inventory movements
- **inventory_transaction_lines**: Transaction details
- **cost_layers**: Inventory costing layers
- **inventory_cost_consumptions**: Cost consumption tracking

### Sales Module
- **sales_orders**: Customer sales orders
- **sales_order_lines**: Order line items
- **sales_deliveries**: Delivery notes/shipments
- **sales_delivery_lines**: Delivery line items
- **sales_invoices**: Customer invoices
- **sales_invoice_lines**: Invoice line items
- **sales_returns**: Sales return documents
- **sales_return_lines**: Return line items

### Purchase Module
- **purchase_orders**: Supplier purchase orders
- **purchase_order_lines**: PO line items
- **goods_receipts**: Goods receipt notes
- **goods_receipt_lines**: Receipt line items
- **purchase_invoices**: Supplier invoices
- **purchase_invoice_lines**: Invoice line items
- **purchase_returns**: Purchase return documents
- **purchase_return_lines**: Return line items

### Manufacturing
- **bill_of_materials**: BOM definitions for manufactured products
- **bill_of_material_lines**: BOM components and quantities
- **work_orders**: Manufacturing work orders
- **work_order_issues**: Material issues to production
- **work_order_receipts**: Finished goods receipts

### Assets & Fixed Assets
- **asset_categories**: Asset classification
- **assets**: Fixed asset records
- **asset_category_company**: Company-specific asset category settings
- **asset_depreciation_schedules**: Depreciation calculations
- **asset_transfers**: Asset location transfers
- **asset_disposal_details**: Asset disposal information
- **asset_disposals**: Asset disposal records
- **asset_invoices**: Asset purchase invoices
- **asset_invoice_details**: Invoice line items
- **asset_invoice_payments**: Invoice payment records
- **asset_invoice_payment_allocations**: Payment allocations
- **asset_financing_agreements**: Asset financing contracts
- **asset_financing_payments**: Financing payment records
- **asset_financing_payment_allocations**: Payment allocations
- **asset_financing_schedules**: Payment schedules

### Accounting & Finance
- **accounts**: Chart of accounts
- **journals**: Accounting journals
- **journal_entries**: Individual journal entries
- **currencies**: Currency definitions
- **accounting_event_logs**: Audit trail for accounting events

### Debt Management
- **internal_debts**: Inter-company debts
- **external_debts**: Third-party debts
- **internal_debt_payments**: Internal debt settlements
- **internal_debt_payment_details**: Payment details
- **external_debt_payments**: External debt settlements
- **external_debt_payment_details**: Payment details

### Tax System
- **tax_categories**: Tax category definitions
- **tax_jurisdictions**: Tax jurisdiction definitions
- **tax_components**: Individual tax components
- **tax_rules**: Tax calculation rules

### Booking & Rental System
- **resource_pools**: Resource groupings
- **resource_instances**: Individual resources
- **availability_rules**: Resource availability rules
- **occurrences**: Time-based availability
- **bookings**: Reservation records
- **booking_lines**: Booking details
- **booking_line_resources**: Resource assignments
- **rental_policies**: Rental terms and conditions

## Detailed Table Descriptions

### Companies & Organization

#### companies
**Purpose**: Stores company information and multi-tenant separation.

**Fields**:
- `id` (PK): Auto-increment ID
- `name`: Company name
- `code`: Unique company code
- `address`: Company address
- `phone`: Contact phone
- `email`: Contact email
- `tax_id`: Tax identification number
- `registration_number`: Legal registration number
- `fiscal_year_start`: Fiscal year start date
- `default_currency_id` (FK): Default currency
- `operational_settings`: JSON operational configuration

**Relationships**:
- Referenced by: Most tables via `company_id`
- References: `currencies`

#### branches
**Purpose**: Company branch/location management.

**Fields**:
- `id` (PK): Auto-increment ID
- `company_id` (FK): Parent company
- `name`: Branch name
- `code`: Unique branch code
- `address`: Branch address
- `phone`: Branch phone
- `manager_user_global_id`: Branch manager

**Relationships**:
- References: `companies`, `users`
- Referenced by: Sales/purchase/inventory tables

### Partner Management

#### partners
**Purpose**: Business partners (customers, suppliers, vendors).

**Fields**:
- `id` (PK): Auto-increment ID
- `code`: Unique partner code
- `name`: Partner name
- `phone`: Contact phone
- `email`: Contact email
- `address`: Full address
- `city`, `region`, `country`, `postal_code`: Address components
- `tax_id`: Tax identification
- `registration_number`: Legal registration
- `industry`: Business industry
- `website`: Company website
- `notes`: Additional notes
- `status`: active/inactive

**Relationships**:
- Referenced by: Sales orders, purchase orders, invoices
- References: `users` (created_by, updated_by)

#### partner_groups
**Purpose**: Partner categorization for pricing and segmentation.

**Fields**:
- `id` (PK): Auto-increment ID
- `namespace_id` (FK): Group namespace
- `name`: Group name
- `code`: Unique group code
- `description`: Group description

**Relationships**:
- References: `partner_group_namespaces`
- Referenced by: `partner_group_members`, `price_lists`

### Product Management

#### products
**Purpose**: Product catalog and definitions.

**Fields**:
- `id` (PK): Auto-increment ID
- `code`: Unique product code
- `name`: Product name
- `kind`: goods/service/rental/accommodation/package/digital
- `product_category_id` (FK): Product category
- `attribute_set_id` (FK): Attribute set for variants
- `attrs_json`: JSON attributes
- `default_uom_id` (FK): Default unit of measure
- `tax_category_id` (FK): Tax category
- `revenue_account_id` (FK): Revenue account
- `cogs_account_id` (FK): Cost of goods sold account
- `inventory_account_id` (FK): Inventory account
- `is_active`: Active status

**Relationships**:
- References: `product_categories`, `attribute_sets`, `uoms`, `tax_categories`, `accounts`
- Referenced by: `product_variants`, `product_suppliers`, `sales_order_lines`, etc.

#### product_variants
**Purpose**: Product variations with specific attributes.

**Fields**:
- `id` (PK): Auto-increment ID
- `product_id` (FK): Parent product
- `sku`: Stock keeping unit
- `barcode`: Product barcode
- `attrs_json`: Variant attributes
- `track_inventory`: Inventory tracking flag
- `uom_id` (FK): Unit of measure
- `weight_grams`, `length_cm`, `width_cm`, `height_cm`: Physical dimensions
- `is_active`: Active status

**Relationships**:
- References: `products`, `uoms`
- Referenced by: Order lines, inventory items, BOMs

### Sales Module

#### sales_orders
**Purpose**: Customer sales orders.

**Fields**:
- `id` (PK): Auto-increment ID
- `company_id` (FK): Company
- `branch_id` (FK): Branch
- `partner_id` (FK): Customer
- `price_list_id` (FK): Pricing structure
- `currency_id` (FK): Transaction currency
- `order_number`: Unique order number
- `status`: Order status
- `order_date`: Order date
- `expected_delivery_date`: Expected delivery
- `quote_valid_until`: Quote validity
- `customer_reference`: Customer PO number
- `sales_channel`: Sales channel
- `payment_terms`: Payment terms
- `exchange_rate`: Currency conversion rate
- `reserve_stock`: Stock reservation flag
- `subtotal`, `tax_total`, `total_amount`: Order totals
- `notes`: Order notes

**Relationships**:
- References: `companies`, `branches`, `partners`, `price_lists`, `currencies`
- Referenced by: `sales_order_lines`, `sales_deliveries`, `sales_invoices`

#### sales_order_lines
**Purpose**: Individual line items in sales orders.

**Fields**:
- `id` (PK): Auto-increment ID
- `sales_order_id` (FK): Parent order
- `line_number`: Line sequence
- `product_id` (FK): Product
- `product_variant_id` (FK): Product variant
- `description`: Line description
- `uom_id`, `base_uom_id` (FK): Units of measure
- `quantity`, `quantity_base`: Ordered quantities
- `unit_price`: Unit price
- `tax_rate`: Tax percentage
- `tax_amount`, `line_total`: Calculated amounts
- `requested_delivery_date`: Requested delivery date
- `reservation_location_id` (FK): Stock reservation location
- `quantity_reserved`, `quantity_reserved_base`: Reserved quantities
- `quantity_delivered`, `quantity_delivered_base`: Delivered quantities
- `quantity_invoiced`, `quantity_invoiced_base`: Invoiced quantities

**Relationships**:
- References: `sales_orders`, `products`, `product_variants`, `uoms`, `locations`
- Referenced by: `sales_delivery_lines`, `sales_invoice_lines`

#### sales_deliveries
**Purpose**: Delivery notes and shipments.

**Fields**:
- `id` (PK): Auto-increment ID
- `sales_order_id` (FK): Related sales order
- `company_id`, `branch_id` (FK): Company/branch
- `partner_id` (FK): Customer
- `currency_id` (FK): Currency
- `location_id` (FK): Shipping location
- `inventory_transaction_id` (FK): Related inventory transaction
- `delivery_number`: Unique delivery number
- `status`: Delivery status
- `delivery_date`: Delivery date
- `total_quantity`, `total_amount`, `total_cogs`: Delivery totals
- `exchange_rate`: Currency conversion

**Relationships**:
- References: `sales_orders`, `companies`, `branches`, `partners`, `currencies`, `locations`, `inventory_transactions`
- Referenced by: `sales_delivery_lines`

#### sales_invoices
**Purpose**: Customer invoices.

**Fields**:
- `id` (PK): Auto-increment ID
- `sales_order_id` (FK): Related sales order
- `company_id`, `branch_id` (FK): Company/branch
- `partner_id` (FK): Customer
- `currency_id` (FK): Currency
- `invoice_number`: Unique invoice number
- `status`: Invoice status
- `invoice_date`: Invoice date
- `due_date`: Payment due date
- `customer_invoice_number`: Customer reference
- `exchange_rate`: Currency conversion
- `subtotal`, `tax_total`, `total_amount`: Invoice totals
- `delivery_value_base`: Delivery value in base currency
- `revenue_variance`: Revenue variance

**Relationships**:
- References: `sales_orders`, `companies`, `branches`, `partners`, `currencies`
- Referenced by: `sales_invoice_lines`

### Purchase Module

#### purchase_orders
**Purpose**: Supplier purchase orders.

**Fields**:
- `id` (PK): Auto-increment ID
- `company_id`, `branch_id` (FK): Company/branch
- `partner_id` (FK): Supplier
- `currency_id` (FK): Currency
- `order_number`: Unique PO number
- `status`: Order status
- `order_date`: Order date
- `expected_date`: Expected delivery
- `supplier_reference`: Supplier reference
- `payment_terms`: Payment terms
- `exchange_rate`: Currency conversion
- `subtotal`, `tax_total`, `total_amount`: Order totals
- `approved_at`, `approved_by`: Approval tracking
- `sent_at`, `sent_by`: Sending tracking
- `canceled_at`, `canceled_by`, `canceled_reason`: Cancellation tracking

**Relationships**:
- References: `companies`, `branches`, `partners`, `currencies`, `users`
- Referenced by: `purchase_order_lines`, `goods_receipts`, `purchase_invoices`

#### goods_receipts
**Purpose**: Goods receipt notes for received purchases.

**Fields**:
- `id` (PK): Auto-increment ID
- `purchase_order_id` (FK): Related PO
- `company_id`, `branch_id` (FK): Company/branch
- `partner_id` (FK): Supplier
- `currency_id` (FK): Currency
- `location_id` (FK): Receiving location
- `inventory_transaction_id` (FK): Related inventory transaction
- `receipt_number`: Unique receipt number
- `status`: Receipt status
- `receipt_date`: Receipt date
- `total_quantity`, `total_amount`, `total_cogs`: Receipt totals
- `exchange_rate`: Currency conversion

**Relationships**:
- References: `purchase_orders`, `companies`, `branches`, `partners`, `currencies`, `locations`, `inventory_transactions`
- Referenced by: `goods_receipt_lines`

#### purchase_invoices
**Purpose**: Supplier invoices.

**Fields**:
- `id` (PK): Auto-increment ID
- `purchase_order_id` (FK): Related PO
- `company_id`, `branch_id` (FK): Company/branch
- `partner_id` (FK): Supplier
- `currency_id` (FK): Currency
- `invoice_number`: Unique invoice number
- `status`: Invoice status
- `invoice_date`: Invoice date
- `due_date`: Payment due date
- `supplier_invoice_number`: Supplier reference
- `exchange_rate`: Currency conversion
- `subtotal`, `tax_total`, `total_amount`: Invoice totals
- `goods_receipt_value_base`: Receipt value in base currency
- `expense_variance`: Expense variance

**Relationships**:
- References: `purchase_orders`, `companies`, `branches`, `partners`, `currencies`
- Referenced by: `purchase_invoice_lines`

### Manufacturing Module

#### bill_of_materials
**Purpose**: Bill of materials for manufactured products.

**Fields**:
- `id` (PK): Auto-increment ID
- `bom_number`: Unique BOM number
- `company_id` (FK): Company
- `user_global_id`: BOM owner
- `finished_product_id` (FK): Finished product
- `finished_product_variant_id` (FK): Product variant
- `finished_quantity`: Quantity produced
- `finished_uom_id` (FK): Unit of measure
- `name`: BOM name
- `description`: BOM description
- `version`: BOM version
- `status`: draft/active/inactive
- `is_default`: Default BOM flag
- `effective_date`, `expiration_date`: Validity dates

**Relationships**:
- References: `companies`, `users`, `products`, `product_variants`, `uoms`
- Referenced by: `bill_of_material_lines`, `work_orders`

#### work_orders
**Purpose**: Manufacturing work orders.

**Fields**:
- `id` (PK): Auto-increment ID
- `wo_number`: Unique work order number
- `company_id`, `branch_id` (FK): Company/branch
- `user_global_id`: Responsible user
- `bom_id` (FK): Bill of materials
- `wip_location_id` (FK): Work-in-progress location
- `quantity_planned`: Planned production quantity
- `quantity_produced`: Actual production quantity
- `quantity_scrap`: Scrap quantity
- `status`: draft/released/in_progress/completed/cancelled
- `scheduled_start_date`, `actual_start_date`: Start dates
- `scheduled_end_date`, `actual_end_date`: End dates
- `notes`: Work order notes

**Relationships**:
- References: `companies`, `branches`, `users`, `bill_of_materials`, `locations`
- Referenced by: `work_order_issues`, `work_order_receipts`

### Accounting Module

#### accounts
**Purpose**: Chart of accounts for financial accounting.

**Fields**:
- `id` (PK): Auto-increment ID
- `name`: Account name
- `code`: Unique account code
- `type`: Account type (Asset, Liability, Equity, Revenue, Expense)
- `level`: Account hierarchy level
- `balance_type`: debit/credit balance type
- `parent_id` (FK): Parent account

**Relationships**:
- Self-referencing: `parent_id`
- Referenced by: `journal_entries`, products (revenue/cogs/inventory accounts)

#### journals
**Purpose**: Accounting journals for recording transactions.

**Fields**:
- `id` (PK): Auto-increment ID
- `branch_id` (FK): Branch
- `user_global_id`: Journal creator
- `journal_number`: Unique journal number
- `date`: Journal date
- `journal_type`: Journal type
- `reference_number`: External reference
- `description`: Journal description

**Relationships**:
- References: `branches`, `users`
- Referenced by: `journal_entries`

#### journal_entries
**Purpose**: Individual debit/credit entries in journals.

**Fields**:
- `id` (PK): Auto-increment ID
- `journal_id` (FK): Parent journal
- `account_id` (FK): Account
- `debit`, `credit`: Transaction amounts
- `currency_id` (FK): Transaction currency
- `exchange_rate`: Conversion rate
- `primary_currency_debit`, `primary_currency_credit`: Base currency amounts

**Relationships**:
- References: `journals`, `accounts`, `currencies`

### Inventory Management

#### locations
**Purpose**: Warehouse and storage locations.

**Fields**:
- `id` (PK): Auto-increment ID
- `company_id` (FK): Company
- `branch_id` (FK): Branch
- `code`: Location code
- `name`: Location name
- `type`: internal/customer/supplier/transit
- `is_scrap_location`: Scrap location flag
- `parent_id` (FK): Parent location
- `address`: Location address

**Relationships**:
- References: `companies`, `branches`, locations (self)
- Referenced by: Most inventory-related tables

#### inventory_items
**Purpose**: Current inventory quantities by location.

**Fields**:
- `id` (PK): Auto-increment ID
- `product_variant_id` (FK): Product variant
- `location_id` (FK): Storage location
- `lot_id` (FK): Lot number
- `serial_id` (FK): Serial number
- `quantity_available`: Available quantity
- `quantity_reserved`: Reserved quantity
- `unit_cost`: Average unit cost
- `last_movement_date`: Last movement date

**Relationships**:
- References: `product_variants`, `locations`, `lots`, `serials`

#### inventory_transactions
**Purpose**: Inventory movement transactions.

**Fields**:
- `id` (PK): Auto-increment ID
- `company_id` (FK): Company
- `branch_id` (FK): Branch
- `source_location_id`, `destination_location_id` (FK): Movement locations
- `product_variant_id` (FK): Product variant
- `transaction_type`: Movement type
- `reference_document_type`, `reference_document_id`: Source document
- `quantity`: Movement quantity
- `unit_cost`: Unit cost
- `total_cost`: Total cost
- `transaction_date`: Movement date

**Relationships**:
- References: `companies`, `branches`, `locations`, `product_variants`
- Referenced by: Delivery/receipt documents

### Assets & Fixed Assets

#### assets
**Purpose**: Fixed asset records and depreciation tracking.

**Fields**:
- `id` (PK): Auto-increment ID
- `company_id`, `branch_id` (FK): Company/branch
- `asset_category_id` (FK): Asset category
- `code`: Unique asset code
- `name`: Asset name
- `type`: tangible/intangible
- `acquisition_type`: outright_purchase/financed_purchase/leased/rented
- `acquisition_date`: Purchase date
- `cost_basis`: Original cost
- `salvage_value`: Salvage value
- `is_depreciable`, `is_amortizable`: Depreciation flags
- `depreciation_method`: Depreciation method
- `useful_life_months`: Useful life
- `depreciation_start_date`: Depreciation start
- `accumulated_depreciation`: Accumulated depreciation
- `net_book_value`: Current book value
- `status`: active/inactive/disposed/sold/scrapped/written_off
- `notes`: Asset notes
- `warranty_expiry`: Warranty expiration

**Relationships**:
- References: `companies`, `branches`, `asset_categories`
- Referenced by: Asset transfers, disposals, invoices

#### asset_depreciation_schedules
**Purpose**: Depreciation schedule calculations.

**Fields**:
- `id` (PK): Auto-increment ID
- `asset_id` (FK): Related asset
- `period_start_date`, `period_end_date`: Depreciation period
- `depreciation_amount`: Period depreciation
- `accumulated_depreciation`: Running total
- `net_book_value`: Period end book value
- `journal_entry_id` (FK): Related journal entry

**Relationships**:
- References: `assets`, `journal_entries`

### Tax System

#### tax_categories
**Purpose**: Tax category definitions for products.

**Fields**:
- `id` (PK): Auto-increment ID
- `name`: Tax category name
- `code`: Unique tax code
- `description`: Category description

**Relationships**:
- Referenced by: `products`, `tax_rules`

#### tax_rules
**Purpose**: Tax calculation rules.

**Fields**:
- `id` (PK): Auto-increment ID
- `tax_category_id` (FK): Tax category
- `tax_jurisdiction_id` (FK): Tax jurisdiction
- `name`: Rule name
- `priority`: Application priority
- `is_active`: Active status

**Relationships**:
- References: `tax_categories`, `tax_jurisdictions`
- Referenced by: `tax_components`

#### tax_components
**Purpose**: Individual tax components within rules.

**Fields**:
- `id` (PK): Auto-increment ID
- `tax_rule_id` (FK): Parent tax rule
- `name`: Component name
- `type`: percentage/fixed_amount
- `amount`: Tax amount/rate
- `base_amount_type`: gross/net
- `is_compound`: Compound tax flag

**Relationships**:
- References: `tax_rules`

### Booking & Rental System

#### bookings
**Purpose**: Reservation and booking records.

**Fields**:
- `id` (PK): Auto-increment ID
- `company_id` (FK): Company
- `partner_id` (FK): Customer
- `booking_number`: Unique booking number
- `status`: Booking status
- `start_date`, `end_date`: Booking period
- `total_amount`: Booking total
- `currency_id` (FK): Currency
- `notes`: Booking notes

**Relationships**:
- References: `companies`, `partners`, `currencies`
- Referenced by: `booking_lines`

#### booking_lines
**Purpose**: Individual booking line items.

**Fields**:
- `id` (PK): Auto-increment ID
- `booking_id` (FK): Parent booking
- `line_number`: Line sequence
- `resource_pool_id` (FK): Resource pool
- `start_date`, `end_date`: Line period
- `quantity`: Quantity booked
- `unit_price`: Unit price
- `line_total`: Line total

**Relationships**:
- References: `bookings`, `resource_pools`
- Referenced by: `booking_line_resources`

## Key Relationships Summary

### Core Business Flow
1. **Sales**: `partners` → `sales_orders` → `sales_order_lines` → `sales_deliveries` → `sales_delivery_lines` → `sales_invoices` → `sales_invoice_lines`
2. **Purchases**: `partners` → `purchase_orders` → `purchase_order_lines` → `goods_receipts` → `goods_receipt_lines` → `purchase_invoices` → `purchase_invoice_lines`
3. **Manufacturing**: `products` → `bill_of_materials` → `bill_of_material_lines` → `work_orders` → `work_order_issues`/`work_order_receipts`
4. **Inventory**: `product_variants` → `inventory_items` → `inventory_transactions` → `inventory_transaction_lines`
5. **Accounting**: `accounts` → `journals` → `journal_entries`

### Multi-tenancy
- `companies` is the root tenant entity
- Most tables reference `company_id` for tenant isolation
- `branches` provide sub-organization within companies

### Financial Integration
- Revenue accounts linked from `products`
- COGS and inventory accounts tracked per product
- All transactions flow to `journal_entries` for financial reporting
- Multi-currency support with `exchange_rate` fields

### Document Flow
- Orders → Deliveries/Receipts → Invoices
- Each step maintains referential integrity
- Quantities tracked: ordered → delivered/received → invoiced
- Status fields control document workflow

This database schema supports a comprehensive ERP system with integrated accounting, inventory, sales, purchasing, manufacturing, and asset management capabilities.
