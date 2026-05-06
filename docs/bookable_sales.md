# BOOKABLE_SALES.md

## Bookable Sales (Flights / Hotels / Car Rentals)

Companion to `booking.md`. That doc covers the underlying booking engine
(resource pools, availability, double-booking prevention). This one
covers the **commercial** layer: how bookings turn into revenue and
COGS, with mode-specific accounting treatment.

This document is the source of truth when revising any of:

- Fulfillment mode logic and validation
- Booking → Sales Order conversion
- Per-mode GL posting (revenue / COGS / passthrough)
- Cost-pool allocation orchestration for self-operated bookings
- The booking line-level metadata (flight / hotel / car-rental detail)

---

## 1. Three Fulfillment Modes

Each Booking carries `fulfillment_mode` (default: `self_operated`).
This single field drives every downstream accounting decision.

| Mode | Who owns the asset? | Revenue at sale | COGS at sale | COGS recognised when… |
|---|---|---|---|---|
| `self_operated` | The business (own hotel, own plane, own fleet) | Yes (gross) | No | Period close, via cost-pool allocation (operating costs → COGS) |
| `reseller` | A supplier; the business is principal (e.g. tour operator buying room blocks) | Yes (gross) | Yes — supplier cost into a clearing liability | Posted at SI post time alongside AR |
| `agent` | A third party; the business is intermediary (travel agent net method) | Commission only | None | Never — there is no COGS, only commission revenue |

**Decision rule.** Whether the business *takes inventory risk* and
*controls the service before transfer* (IFRS 15 / ASC 606 principal vs
agent test):

- Owns and operates the asset → self-operated.
- Buys wholesale at supplier rate, resells at retail, takes risk → reseller (gross).
- No risk, just brokerage → agent (net).

Encoded in `App\Enums\FulfillmentMode` with helpers:
`requiresSupplierCost()`, `requiresSupplierPartner()`,
`usesNetMethod()`, `usesPoolAllocation()`, `options()`.

---

## 2. Booking-Level Schema

### `bookings` table — added columns

| Column | Type | Purpose |
|---|---|---|
| `fulfillment_mode` | string(30), default `self_operated` | Mode discriminator (cast to `FulfillmentMode` enum) |
| `booking_subtype` | string(40) nullable | UI/metadata router: `flight` \| `hotel` \| `car_rental` \| `accommodation` \| `rental` \| `other` |
| `converted_sales_order_id` | foreignId nullable | Idempotency guard for booking → SO conversion |

Existing columns (`booking_type`, `status`, `partner_id`,
`deposit_amount`, etc.) are unchanged.

`booking_subtype` is a UI/validation routing key, distinct from
`booking_type` (which is the legacy `accommodation|rental` axis used by
the booking engine for amount math). On migration, `booking_subtype` is
backfilled from `booking_type`.

### `booking_lines` table — added columns

| Column | Type | Purpose |
|---|---|---|
| `supplier_partner_id` | foreignId nullable → partners | Vendor for reseller/agent modes |
| `supplier_cost` | decimal(18,2) nullable | Reseller wholesale cost in booking currency |
| `supplier_cost_base` | decimal(18,4) nullable | Reseller cost in base currency (computed) |
| `commission_amount` | decimal(18,2) nullable | Agent: commission portion of customer charge |
| `passthrough_amount` | decimal(18,2) nullable | Agent: passthrough portion (must sum with commission to amount) |
| `supplier_invoice_ref` | string(120) nullable | PNR / voucher / hotel reservation number |
| `meta` | jsonb default `{}` | Subtype-specific detail (see §3); GIN-indexed |

### Mode invariants (enforced in BookingController + BookingConversionService)

| Mode | supplier_partner_id | supplier_cost | commission_amount | passthrough_amount |
|---|---|---|---|---|
| `self_operated` | NULL | NULL | NULL | NULL |
| `reseller` | required | > 0 | NULL | NULL |
| `agent` | required | NULL | required | required (commission + passthrough = amount, ±0.01) |

---

## 3. Subtype Metadata (`booking_lines.meta`)

JSONB column holding subtype-specific fields. JSON over polymorphic
detail tables was chosen because none of these fields drive money math
or aggregation; they're metadata. GIN index on `meta` for future search.

### `flight`
```json
{
  "flight_number": "GA204",
  "carrier_code": "GA",
  "fare_class": "Y",
  "origin": "CGK",
  "destination": "DPS",
  "departure_datetime": "2026-06-01T07:30",
  "arrival_datetime": "2026-06-01T10:15",
  "pnr": "ABCDEF",
  "passenger_name": "John Doe",
  "passenger_doc_number": "X1234567"
}
```

### `hotel`
```json
{
  "room_type": "Deluxe",
  "check_in": "2026-06-01T14:00",
  "check_out": "2026-06-04T12:00",
  "guest_count": 2,
  "board_basis": "BB",
  "guest_name": "John Doe"
}
```

### `car_rental`
```json
{
  "vehicle_class": "SUV",
  "plate_number": "B 1234 XX",
  "driver_name": "John Doe",
  "driver_license_number": "D123456",
  "pickup_datetime": "2026-06-10T09:00",
  "return_datetime": "2026-06-12T09:00",
  "odo_start": 12345,
  "odo_end": 12678
}
```

UI components: `resources/js/Pages/Bookings/Partials/Subtypes/{Flight,Hotel,CarRental}Details.vue`.

---

## 4. Booking → Sales Order Conversion

`App\Services\Booking\BookingConversionService::convertToSalesOrder(Booking $booking, array $overrides = [], ?Authenticatable $actor = null): SalesOrder`

### Preconditions
- Booking status ∈ {`confirmed`, `checked_in`, `checked_out`, `completed`}
- All lines satisfy mode invariants (§2)
- Booking has at least one line

### Idempotency
If `bookings.converted_sales_order_id` is set, returns the existing SO
without re-creating.

### Per-mode line splitting

The conversion turns each `BookingLine` into one or more
`SalesOrderLine`s, tagged with a new `revenue_role` discriminator that
later drives GL routing.

| Mode | SO lines per BookingLine | revenue_role values | Notes |
|---|---|---|---|
| `self_operated` | 1 | `gross_revenue` | One SO line at booking unit_price × qty |
| `reseller` | 1 | `gross_revenue` | One SO line; **plus** a `SalesOrderCost` row carrying total `supplier_cost` |
| `agent` | up to 2 | `commission_revenue`, `passthrough_supplier` | Split based on `commission_amount` and `passthrough_amount`; lines with zero values are skipped |

### Description templating
Subtype-aware line descriptions:
- flight: `"<product> <origin>→<destination> <date> — <passenger> (PNR <pnr>)"`
- hotel: `"<product> <check_in> s/d <check_out>"`
- car_rental: `"<product> <pickup_datetime> s/d <return_datetime>"`
- other: just product name

### Fields on resulting SalesOrder
- `customer_reference` = booking_number
- `sales_channel` = booking.source_channel or `'booking'`
- `reserve_stock` = false (bookings allocate time, not stock — see booking.md §1)
- `notes` includes booking number for traceability

---

## 5. GL Routing

### `SalesInvoiceLine.revenue_role` (new column)

Mirrors the value set on `SalesOrderLine.revenue_role` during
SO→SI prepare. Three values:

- `gross_revenue` (or NULL for legacy lines): line_total → `revenue` role
- `commission_revenue`: line_total → `commission_revenue` role
- `passthrough_supplier`: **excluded** from `SALES_AR_POSTED` entirely; handled by `BOOKING_AGENT_PASSTHROUGH_POSTED`

Backward compatible: pre-bookable invoices have NULL revenue_role and
keep behaving exactly as before (everything credits `revenue`).

### Posted events per mode

#### `self_operated`
At invoice post: standard `SALES_AR_POSTED`
- Dr `receivable`, Cr `revenue`, Cr `tax_payable`

At period close: `BOOKING_POOL_COGS_POSTED` (orchestrator-driven, see §6)
- Dr `cogs_booking`, Cr `cost_pool_clearing`

No COGS at sale time. The credit on the cost side is to a **clearing
liability**, not inventory — costs flowed in earlier as operating
expenses to the cost pool.

#### `reseller`
At invoice post: TWO events fire, in order:
1. `SALES_AR_POSTED` (standard): Dr `receivable`, Cr `revenue`, Cr `tax_payable`
2. `BOOKING_PRINCIPAL_COGS_POSTED`: Dr `cogs_booking`, Cr `supplier_clearing`

`supplier_clearing` is an "AP-not-yet-invoiced" liability. Mirrors the
`grn_clearing` pattern used for goods. When the actual purchase invoice
from the supplier arrives, AP is recognised against `supplier_clearing`.

#### `agent`
At invoice post: TWO events fire:
1. `SALES_AR_POSTED` over the gross+commission portion only:
   - Dr `receivable` (commission + tax)
   - Cr `commission_revenue`
   - Cr `tax_payable` (if any)
2. `BOOKING_AGENT_PASSTHROUGH_POSTED` over the passthrough portion:
   - Dr `receivable_passthrough`
   - Cr `supplier_payable_passthrough`

Tax on passthrough defaults to zero (set per-line if required).

### New GL roles introduced
- `commission_revenue` — added to existing SALES_AR_POSTED config
- `cogs_booking` — distinct from regular `cogs` so reports can split
- `supplier_clearing` — reseller AP-not-yet-invoiced
- `supplier_payable_passthrough` — agent passthrough liability
- `receivable_passthrough` — agent passthrough AR (typically same account as `receivable`)
- `cost_pool_clearing` — drained-down balance from operating expense allocation
- `customer_deposit` — reserved for future deposit Payment flow

---

## 6. Cost-Pool Allocation (Self-Operated COGS)

The orchestrator that closes the COGS-timing gap for self-operated
bookings. Without it, self-operated SI lines have revenue but no
cost_total, so margin reports show 100%.

### `App\Services\Booking\Allocation\BookingAllocationOrchestrator`

#### `run(int $companyId, int $costPoolId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?Authenticatable $actor = null): BookingAllocationRun`

1. Lock `BookingAllocationRun` row for `(company, pool, period)`.
   Refuse if already posted (idempotency: must reverse first).
2. Lock the CostPool. Snapshot `unallocated_amount`.
3. Find SI lines satisfying ALL:
   - Booking is `fulfillment_mode = self_operated` and same company
   - BookingLine service period overlaps `[periodStart, periodEnd)`
   - Backing SalesInvoice is `posted`
   - Not already allocated against this pool
   - Backing booking's resource targets the pool's `asset_id` (when pinned)
4. Compute per-line numerators via `NumeratorResolver` (see below). Sum → denominator.
5. If denominator > 0 and pool has unallocated balance:
   - Call `CostingService::allocateFromPool(pool, allocations, denominator, periodStart, periodEnd)` — this creates `CostAllocation` rows, attaches `InvoiceDetailCost`, updates SI line `unit_cost` / `cost_total` / `gross_margin`.
   - Tag created allocations with `booking_allocation_run_id`.
   - Dispatch `BOOKING_POOL_COGS_POSTED` with the total.
6. Mark run posted; if denominator or balance is zero, mark posted with an explanatory `notes`.

#### `reverse(BookingAllocationRun $run, ?Authenticatable $actor = null): BookingAllocationRun`

- Detach `InvoiceDetailCost` rows for the run's allocations.
- Delete `CostAllocation` rows tagged with this run.
- Roll back SI line cost_total / unit_cost / gross_margin.
- Decrement pool's `total_allocated`.
- Dispatch `BOOKING_POOL_COGS_REVERSED`.
- Mark run as `reversed`.

### Numerator basis (`App\Services\Booking\Allocation\NumeratorResolver`)

| Subtype | Basis | Formula |
|---|---|---|
| `hotel` | `room_nights` | qty × ceil(end − start in days) |
| `flight` | `seat_nights` | qty (one segment = one unit; multi-leg deferred) |
| `car_rental` | `rental_days` | qty × ceil((end − start) / 24h) |
| (other) | `revenue` | `line_total_base` (always allocatable fallback) |

### Triggers
- **Manual UI** — `Bookings/Allocation/Index.vue` modal → POST to `BookingAllocationController@store`.
- **Artisan** — `php artisan booking:run-allocations [--company=] [--pool=] [--period=YYYY-MM] [--dry-run]`. Defaults to last calendar month.
- **Scheduler** — wire the Artisan command in `routes/console.php` for daily/monthly runs (not pre-wired; depends on tenant policy).

### `booking_allocation_runs` table
Authoritative ledger for runs. Unique `(company, pool, period_start, period_end)`. Tracks: status (`draft`/`posted`/`reversed`), denominator, pool_amount, allocation_basis, posted_at/by, reversed_at/by, notes.

---

## 7. Files & Modules

### Enums
- `app/Enums/FulfillmentMode.php` — modes + helpers
- `app/Enums/AccountingEventCode.php` — adds 8 cases (DEPOSIT/REVERSED, PRINCIPAL_COGS_POSTED/REVERSED, AGENT_PASSTHROUGH_POSTED/REVERSED, POOL_COGS_POSTED/REVERSED)

### Models
- `app/Models/Booking.php` — adds casts and `convertedSalesOrder` relation
- `app/Models/BookingLine.php` — adds casts (incl. `meta` → array) and `supplier()` relation
- `app/Models/Product.php` — adds `fulfillment_mode` cast
- `app/Models/CostAllocation.php` — adds `bookingAllocationRun()` relation
- `app/Models/BookingAllocationRun.php` — new

### Services
- `app/Services/Booking/BookingConversionService.php` — booking → SO orchestration
- `app/Services/Booking/Allocation/BookingAllocationOrchestrator.php` — period-close COGS allocation
- `app/Services/Booking/Allocation/NumeratorResolver.php` — per-line numerator math
- `app/Services/Sales/SalesInvoiceService.php` — modified: `dispatchArPostedEvent` walks lines per `revenue_role`; new `dispatchBookingPrincipalCogsEvent` and `dispatchBookingAgentPassthroughEvent` fire from `postSoInvoice`; `prepareLines`/`prepareDirectLines`/`persistInvoiceLines` plumb `revenue_role`
- `app/Services/Sales/SalesService.php` — modified: `persistLines` plumbs `booking_line_id` and `revenue_role`; `createBookingsForOrder` skips lines already linked (prevents duplicate booking creation on booking-derived SOs)

### Controllers / Requests / Routes
- `app/Http/Controllers/BookingController.php` — extended `validateBooking` with mode/subtype/supplier rules; new `convert(Booking)` action; `formOptions` returns suppliers, modes, subtypes; `applyExtendedFields` post-processes after `BookingService::hold`
- `app/Http/Controllers/BookingAllocationController.php` — new (index/store/show/reverse)
- `app/Console/Commands/RunBookingAllocations.php` — new artisan command
- `routes/tenant.php` — adds `bookings/{booking}/convert` and `booking-allocations` resource + `reverse`

### Migrations (under `database/migrations/tenant/`, all timestamped 2026_05_05_11000X)
1. `add_fulfillment_mode_to_products_and_bookings`
2. `add_subtype_and_meta_to_bookings`
3. `add_supplier_costing_to_booking_lines`
4. `create_booking_allocation_runs_table`
5. `add_converted_sales_order_id_to_bookings`
6. `add_revenue_role_to_sales_lines` (both SO lines and SI lines)
7. `add_run_id_to_cost_allocations`

### Seeders
- `database/seeders/AccountSeeder.php` — adds 6 accounts (Pendapatan Komisi, Harga Pokok Penjualan Booking, Uang Muka Pelanggan, Hutang Pemasok Booking Belum Difakturkan, Hutang Pemasok Pass-through, Akumulasi Biaya Operasional Dialokasikan); idempotent for re-runs (matches by code/name+parent and `syncWithoutDetaching` for company/currency)
- `database/seeders/GlEventConfigurationSeeder.php` — adds `commission_revenue` role to existing SALES_AR_POSTED; adds 8 new event configurations covering deposit, principal COGS, agent passthrough, and pool COGS plus reversals

### UI (Vue / Inertia)
- `resources/js/Pages/Bookings/Partials/BookingForm.vue` — adds mode + subtype selectors; conditional supplier / commission / passthrough columns; expandable subtype detail row
- `resources/js/Pages/Bookings/Partials/Subtypes/{Flight,Hotel,CarRental}Details.vue` — subtype metadata forms
- `resources/js/Pages/Bookings/Show.vue` — Convert button (when status ∈ confirmed+ and not yet converted) + linked SO link
- `resources/js/Pages/Bookings/Allocation/Index.vue` — list and create allocation runs
- `resources/js/Pages/Bookings/Allocation/Show.vue` — run detail + reverse
- `resources/js/Layouts/AuthenticatedLayout.vue` — adds Allocation Runs entry under Booking section (desktop expanded, desktop collapsed, and mobile)

### Tests (`tests/Feature/Booking/`)
- `NumeratorResolverTest.php` — room_nights / rental_days / revenue fallback
- `BookingConversionInvariantsTest.php` — rejects on hold, missing supplier, zero supplier_cost, mismatched commission+passthrough

---

## 8. Decisions & Tradeoffs

These are intentional choices; revisiting them is the most common
reason to re-read this doc.

1. **JSONB `meta` over polymorphic detail tables** — flexibility, no joins, jsonb already used elsewhere. Cost: no FK validation, harder reporting on metadata fields. Reconsider when a real reporting requirement appears (e.g. flight manifest, occupancy by room type).
2. **`fulfillment_mode` on `bookings` (header), not lines** — one accounting decision per booking, matches IFRS 15 principal/agent which is determined per arrangement. Default-sourced from `products.fulfillment_mode`.
3. **No new product `kind`** — bookable is already signalled via `ProductCapability`. Adding a `transport_ticket` kind is left as a flagged optional.
4. **Agent split into TWO SI lines** — chosen over single-line-with-attribute so per-line GL routing rides naturally on the existing event-bus pattern. Alternative was uglier.
5. **Reseller credits a clearing liability, not AP directly** — mirrors the existing `grn_clearing` pattern. Real AP recognition happens when the supplier invoice arrives, separately.
6. **Self-operated has no per-sale COGS** — operating costs are period expenses that flow into asset-bound CostPools and are allocated at period close. This matches activity-based costing and matches how P&L for a hotel actually works.
7. **Pool granularity = per (company, asset)** — not per-flight or per-stay. Granularity comes from numerators (room-nights, seat-nights) not from pool proliferation. Per-flight pools are possible if needed later (just create one with the aircraft as `asset_id`).
8. **Conversion gate = confirmed or later** — not strictly checked-out. For self-operated rentals where revenue should be recognised on use, this is loose; tighten with a per-tenant policy if needed.
9. **Deposits informational only (for now)** — `Booking.deposit_amount` is captured but no Payment record is created at confirm. The `BOOKING_DEPOSIT_RECEIVED` event code and `customer_deposit` role are seeded in advance for the future formal flow.
10. **Backwards compatibility** — `revenue_role` is nullable; legacy SI lines and pre-bookable invoices route to `revenue` exactly as before. `createBookingsForOrder` skips SO lines that already have `booking_line_id` set, preventing duplicate booking creation when an SO came from a converted Booking.

---

## 9. Open Items / Future Work

- **Deposit Payment flow** — wire `BOOKING_DEPOSIT_RECEIVED` to a real Payment record at confirm; apply `customer_deposit` credit at SI post.
- **Multi-leg flights** — `NumeratorResolver` treats one flight as one unit. For airlines with leg-aware routing, extend to seat-segments.
- **Per-flight cost pools** — current design supports asset-level pools only. For airlines wanting fuel + crew per flight, allow nested pools or per-flight CostPool creation tied to the aircraft asset.
- **AR reversal events for booking flows** — `BOOKING_PRINCIPAL_COGS_REVERSED` and `BOOKING_AGENT_PASSTHROUGH_REVERSED` are seeded but not yet wired into `SalesInvoiceService::unpostSoInvoice`. Add when the unpost flow needs to undo these.
- **Pool COGS event scoped to allocation run** — orchestrator's `dispatchPoolCogsEvent` uses `event(...)` directly with a `'booking_allocation_run'` document type; verify the `AccountingEventBus` listener resolves the GL Event Configuration for this document context as expected.
- **Standard-cost variant for self-operated** — alternative to pool allocation: book a standard COGS rate at sale time, then true-up via pool-allocation variance at period close. Cleaner for daily-margin reports.
- **Test infrastructure** — Pest tests under `tests/Feature/Booking/` follow the existing pattern but are blocked by the same pre-existing issue affecting `BookingServiceTest` and `AvailabilityServiceTest`: `RefreshDatabase` doesn't run tenant migrations. Once that's fixed, all tests should run.

---

## 10. Quick Reference: Where to Look

| If you want to change… | Look here |
|---|---|
| What modes exist or how they're labeled | `app/Enums/FulfillmentMode.php` |
| Mode invariants on save | `app/Http/Controllers/BookingController.php::validateBooking` |
| Conversion logic (line splitting, descriptions) | `app/Services/Booking/BookingConversionService.php` |
| AR posting per role | `app/Services/Sales/SalesInvoiceService.php::dispatchArPostedEvent` |
| Reseller COGS posting | `app/Services/Sales/SalesInvoiceService.php::dispatchBookingPrincipalCogsEvent` |
| Agent passthrough posting | `app/Services/Sales/SalesInvoiceService.php::dispatchBookingAgentPassthroughEvent` |
| Pool allocation | `app/Services/Booking/Allocation/BookingAllocationOrchestrator.php` |
| Numerator math | `app/Services/Booking/Allocation/NumeratorResolver.php` |
| Default account names per role | `database/seeders/GlEventConfigurationSeeder.php` |
| Subtype detail UI | `resources/js/Pages/Bookings/Partials/Subtypes/` |
| Booking form fields | `resources/js/Pages/Bookings/Partials/BookingForm.vue` |
