# Accounting & ERP Application Documentation

## 1. Overview

This is a comprehensive **Enterprise Resource Planning (ERP)** and **Accounting** application built with modern web technologies. It is designed to handle complex business operations including Multi-entity Accounting, Inventory Management, Manufacturing, Sales, Purchasing, and Asset Management.

The system is built with a **Multi-tenant Architecture**, allowing multiple distinct organizations (tenants) to use the application with complete data isolation.

## 2. Technology Stack

### Backend

- **Framework**: [Laravel 11](https://laravel.com/) (PHP 8.2+)
- **Database**: MySQL (Per-tenant databases)
- **Multi-tenancy**: [Stancl Tenancy](https://tenancyforlaravel.com/)
- **Authentication**: Laravel Breeze & Sanctum
- **Authorization**: Spatie Laravel Permission
- **Reporting**: DomPDF, Maatwebsite Excel

### Frontend

- **Framework**: [Vue.js 3](https://vuejs.org/) (Composition API)
- **Glue**: [Inertia.js](https://inertiajs.com/) (Server-driven SPA)
- **Styling**: [Tailwind CSS](https://tailwindcss.com/)
- **State Management**: Vue Reactivity / Inertia Props
- **Components**: Headless UI, Heroicons

## 3. System Architecture

### 3.1 Multi-Tenancy

The application uses a **Database-per-Tenant** approach.

- **Central Domain**: Handles landing pages, tenant registration, and central administration.
- **Tenant Domain**: The core application lives here. Each tenant is identified by a subdomain (e.g., `company.app.com`).
- **Middleware**: `InitializeTenancyByDomainOrSubdomain` ensures the correct database connection is established based on the request hostname.

### 3.2 Key Concepts

- **Company**: The root entity of a tenant. A tenant can technically manage multiple companies (though usually 1:1 in practice).
- **Branch**: Operational units within a company. Most financial and inventory transactions are scoped to a Branch.
- **Fiscal Year**: defined per company to control accounting periods.
- **Base Currency**: defined per company. Multi-currency transactions are converted to this base for reporting.

## 4. Business Modules

### 4.1 Finance & Accounting

The core of the system, fully integrated with all other modules.

- **Chart of Accounts (COA)**: Hierarchical account structure.
- **General Ledger**: Automatic posting from all operational transactions.
- **Journals**: Manual and automated journal entries.
- **Multi-Currency**: Automatic exchange rate handling and reporting.
- **Reports**: Balance Sheet, Income Statement, Cash Bank Book, Trial Balance.
- **Debt Management**: Tracks External Payables/Receivables and Internal (Inter-company) Debts.

### 4.2 Supply Chain Management

- **Sales**:
    - **Sales Orders**: Quotes, Confirmations, Reservations.
    - **Deliveries**: Shipment tracking.
    - **Invoices**: integrated with Accounts Receivable.
    - **Returns**: RMA handling.
- **Purchasing**:
    - **Purchase Plans**: Planning and approval workflows.
    - **Purchase Orders**: Vendor management.
    - **Goods Receipts**: Stock intake.
    - **Invoices**: integrated with Accounts Payable.

### 4.3 Inventory Management

- **Warehousing**: Multiple locations (Physical, Transit, Virtual).
- **Tracking**: Logic for **Lots** and **Serial Numbers**.
- **Movements**: Receive, Ship, Transfer, Adjustments.
- **Valuation**: Real-time inventory valuation (FIFO/Avg Cost - determined by implementation).

### 4.4 Manufacturing

- **Bill of Materials (BOM)**: Recipe/Component definitions.
- **Work Orders**: Production planning and execution.
- **Components**: Issue raw materials to WIP.
- **Finished Goods**: Receipt from production to inventory.

### 4.5 Asset Management

Complete lifecycle management for fixed assets.

- **Acquisition**: Purchasing or leasing assets.
- **Depreciation**: Automated depreciation schedules and posting.
- **Maintenance**: Asset upkeep tracking.
- **Disposal**: Selling or scrapping assets.
- **Financing**: Tracking loans/leases against assets.

### 4.6 Booking & Rental

Specialized module for service/rental businesses.

- **Resource Pools**: Available inventory for rent (e.g., Rooms, Equipment).
- **Bookings**: Reservation system with Check-in/Check-out.
- **Availability**: Real-time conflict checking.

## 5. Directory Structure

```
/
├── app/
│   ├── Http/Controllers/   # Logic for Central and Tenant routes
│   ├── Models/             # Eloquent Models (Central & Tenant)
│   ├── Services/           # Business Logic Layer
│   └── Providers/          # Service Binding (Tenancy, Routes)
├── database/
│   ├── migrations/         # Global migrations
│   └── migrations/tenant/  # Tenant-specific migrations
├── resources/
│   ├── js/
│   │   ├── Pages/          # Inertia View Components
│   │   ├── Components/     # Reusable UI Components
│   │   └── Layouts/        # Page Wrappers (Auth/Guest)
│   └── views/              # Blade templates (Emails, PDF, Landing)
└── routes/
    ├── web.php             # Central domain routes
    └── tenant.php          # Core application routes
```

## 6. Developer Guide

### Setup

The project uses **Laravel Sail** (Docker) for local development.

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

### Key Commands

- **Run Migrations (Tenant)**:
  `./vendor/bin/sail artisan tenants:migrate`
- **Create Tenant**:
  `./vendor/bin/sail artisan tinker` -> `\App\Models\Tenant::create(...)`
- **Lint Code**:
  `./vendor/bin/sail npm run lint` (if configured)

### Adding a New Feature

1. **Model**: Create migration in `database/migrations/tenant`.
2. **Backend**: Add Controller in `app/Http/Controllers` and route in `routes/tenant.php`.
3. **Frontend**: Create Page in `resources/js/Pages` and link in Navigation.
