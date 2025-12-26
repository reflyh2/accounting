# BOOKING.md

## Booking & Rental Engine

This document explains accommodation and rental booking behavior.

---

## 1. Core Concepts

- Booking allocates **time**, not quantity.
- Inventory quantities are **never** used for booking.
- Double booking is prevented at database level.

---

## 2. Core Tables

### resource_pool
- Groups similar resources (room type, car class).

### resource_instance
- Physical units (room, car).
- Can link to asset management.

### availability_rule
- Opens, closes, or blackouts time ranges.

### occurrence
- Time buckets (hotel nights, events).

### booking / booking_line
- booking = reservation header
- booking_line = what is booked, when, and price

---

## 3. Booking Lifecycle

```
hold → confirmed → checked_in → checked_out → completed
            ↘ canceled / no_show
```

- `held_until` auto‑expires holds.

---

## 4. Availability Resolution

### Accommodation
- Use `occurrence.capacity` minus confirmed bookings.

### Rental
- Find free `resource_instance` with no overlapping bookings.

---

## 5. Assignment Rules

- Instance assignment can be:
  - Immediate (rental)
  - Deferred (hotel check‑in)

- Enforced by Postgres EXCLUDE constraint.

---

## 6. Pricing & Invoicing

- Prices written into booking_line at confirmation.
- Invoice lines reference booking_line_id.
- Deposits handled as liabilities until completion.

---

## 7. Non‑Negotiable Rules

1. No booking without availability check.
2. No overlapping instance bookings.
3. No stock mutation from booking.

---

