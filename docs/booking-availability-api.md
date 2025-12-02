# Booking & Availability API

All endpoints below live under the tenant web app and share the `/api/...` prefix that is already protected by the authenticated middleware stack from `routes/tenant.php`. Dates must be supplied in ISO‑8601 (e.g. `2025-05-01T14:00:00+07:00`). Monetary amounts are decimal strings (Laravel will cast to `decimal:2` on the backend).

## Summary

| Capability | Method & Path | Purpose |
|------------|---------------|---------|
| Search pool capacity | `GET /api/availability/pool/{pool}` | Returns remaining capacity, blocking rules and overlapping bookings. |
| List free instances | `GET /api/availability/pool/{pool}/free-instances` | Provides concrete resource instances that can be assigned for the supplied window. |
| Create/hold booking | `POST /api/bookings` | Creates a booking in `hold` status (accommodation or rental). |
| Confirm booking | `POST /api/bookings/{booking}/confirm` | Moves a hold into `confirmed`. |
| Check-in booking | `POST /api/bookings/{booking}/check-in` | Marks a confirmed booking as `checked_in`. |
| Check-out booking | `POST /api/bookings/{booking}/check-out` | Marks a checked-in booking as `checked_out`/`completed`. |
| Cancel booking | `POST /api/bookings/{booking}/cancel` | Cancels any booking that is not already canceled/completed. |
| Assign instance | `POST /api/booking-lines/{bookingLine}/assign-instance` | Links a concrete resource instance to a booking line. |

---

## GET `/api/availability/pool/{pool}`

### Query Parameters

| Name | Type | Required | Notes |
|------|------|----------|-------|
| `start` | string (ISO datetime) | Yes | Window start. |
| `end` | string (ISO datetime) | Yes | Must be after `start`. |
| `qty` | integer | No | Defaults to `1`. Used for requested quantity in the computed result. |

### Response

```json
{
  "data": {
    "pool_id": 12,
    "pool_name": "Tower A",
    "start": "2025-05-01T14:00:00+07:00",
    "end": "2025-05-02T12:00:00+07:00",
    "requested_qty": 2,
    "capacity": 5,
    "booked_qty": 3,
    "available_qty": 2,
    "blocking_rules": [
      { "type": "blackout", "start": "...", "end": "...", "notes": "Maintenance" }
    ],
    "blocked": false,
    "conflicts": [
      {
        "booking_id": 77,
        "booking_number": "BK-250501-0001",
        "status": "confirmed",
        "partner": "Acme Travel",
        "start": "2025-05-01T10:00:00+07:00",
        "end": "2025-05-03T12:00:00+07:00",
        "qty": 3
      }
    ]
  }
}
```

### Errors

- `422` with `{ "message": "End date must be after start date." }` when the range is invalid.

---

## GET `/api/availability/pool/{pool}/free-instances`

### Query Parameters

Same as the pool availability endpoint, plus optional `qty` limit (0 = no limit).

### Response

```json
{
  "data": [
    { "id": 501, "code": "ROOM-1201", "status": "active" },
    { "id": 502, "code": "ROOM-1202", "status": "active" }
  ]
}
```

If the window overlaps existing bookings or blackout rules, the list will be empty. Validation errors mirror the pool endpoint (`422`).

---

## POST `/api/bookings`

Creates a booking in `hold` status. The final status transitions must use the dedicated endpoints below.

### Payload

```json
{
  "partner_id": 10,
  "currency_id": 1,
  "booking_type": "accommodation", // or "rental"
  "held_until": "2025-05-01T16:00:00+07:00", // optional
  "deposit_amount": "500000.00",              // optional, auto-summed from lines if omitted
  "source_channel": "web",                    // optional
  "notes": "VIP guest",                       // optional
  "lines": [
    {
      "product_id": 44,
      "resource_pool_id": 12,
      "product_variant_id": null,
      "start_datetime": "2025-05-05T14:00:00+07:00",
      "end_datetime": "2025-05-07T12:00:00+07:00",
      "qty": 2,
      "unit_price": "750000.00",
      "tax_amount": "0.00",
      "deposit_required": "250000.00",
      "occurrence_id": null,
      "resource_instance_id": null // required for rental when pre-assigning
    }
  ]
}
```

### Validation

- Each `lines.*` entry follows the Laravel rules declared in `BookingController@validateStore`.
- `booking_type` must be `accommodation` or `rental`.
- The service also enforces availability before insertion; a violation returns `422` with `{ "message": "Availability tidak mencukupi ..." }`.

### Response

`201 Created` with the persisted booking (including eager-loaded `lines`, `pool`, and `partner`).

---

## POST `/api/bookings/{booking}/confirm`

Transitions a hold into `confirmed`. Returns the updated booking. Errors:

- `422` if the booking is no longer in `hold`.
- `404` if the booking ID is invalid/unauthorized.

---

## POST `/api/bookings/{booking}/check-in`

Allowed only when the booking status is `confirmed`. Response: updated booking. Violations return `422` with `"Transisi status tidak valid."`.

---

## POST `/api/bookings/{booking}/check-out`

Requires status `checked_in`. The service auto-sets:

- `completed` when every line already ended.
- Otherwise `checked_out` (e.g. partial day hand-off).

---

## POST `/api/bookings/{booking}/cancel`

### Payload

```json
{ "reason": "Customer request" }
```

Cannot cancel a booking that is already `completed` or `canceled`. Returns the updated booking with appended note describing the cancellation reason.

---

## POST `/api/booking-lines/{bookingLine}/assign-instance`

Assigns (or supplements) resource instances on a specific line.

### Payload

```json
{ "resource_instance_id": 501 }
```

Rules enforced by `BookingService`:

- Booking must be `confirmed` or `checked_in`.
- Instance must belong to the same pool, have `status = active`, and be free for the line window.
- Line cannot exceed its ordered `qty` in assigned instances.

Response: the refreshed line with `resources.resourceInstance` relation. Errors return `422` with a descriptive message (`"Instance sudah dibooking."`, `"Jumlah instance melebihi qty pemesanan."`, etc.).

---

## Notes & Conventions

- All responses are JSON and follow Laravel’s default snake_case casting for database columns.
- API routes inherit the tenant middleware stack, so you must include the same headers/cookies used for the rest of the Inertia SPA.
- When integrating from Vue, prefer `route('api.bookings.store')` helpers to keep URL generation centralized.

