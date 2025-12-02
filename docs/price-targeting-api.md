# Price Targeting API

These endpoints manage `price_list_targets`, which bind a price list to specific companies, partners, partner groups, or sales channels. The routes live under the authenticated tenant scope (`routes/tenant.php`) and reuse standard Laravel session auth. All responses are JSON when requested via XHR; when hit from the browser they render the Inertia pages.

Base path: `/catalog/price-list-targets`

## List Targets

**GET** `/catalog/price-list-targets`

Returns a paginated collection ordered by `priority` (ascending) then `id` (desc).

```json
{
  "data": [
    {
      "id": 12,
      "price_list_id": 4,
      "price_list": { "code": "PL-VIP", "name": "VIP (IDR)", "currency": { "code": "IDR" } },
      "company": { "id": 1, "name": "Acme Retail" },
      "partner": { "id": 99, "name": "John Wick" },
      "partner_group": null,
      "channel": "web",
      "priority": 0,
      "is_active": true,
      "valid_from": "2025-01-01",
      "valid_to": null
    }
  ],
  "links": { "...": "..." }
}
```

### Query Parameters

Currently the page only supports pagination via the default Laravel `page` parameter. Extend the controller if extra filters are needed.

## Create Target

**POST** `/catalog/price-list-targets`

```json
{
  "price_list_id": 4,              // required; must exist in price_lists
  "company_id": 1,                 // nullable
  "partner_id": 99,                // nullable
  "partner_group_id": null,        // nullable
  "channel": "web",                // nullable string <= 50 chars
  "priority": 0,                   // optional int (default 0)
  "is_active": true,               // optional bool
  "valid_from": "2025-01-01",      // nullable ISO date
  "valid_to": null                 // nullable ISO date, must be >= valid_from when supplied
}
```

Validation rules (from `PriceListTargetController@validatePayload`):

| Field | Rules |
|-------|-------|
| `price_list_id` | required, exists |
| `company_id` | nullable, exists |
| `partner_id` | nullable, exists |
| `partner_group_id` | nullable, exists |
| `channel` | nullable, max 50 chars |
| `priority` | integer, min 0, max 100000 (defaults to 0) |
| `is_active` | boolean (defaults to true) |
| `valid_from` | nullable date |
| `valid_to` | nullable date, `>= valid_from` |

> **Conflict rule:** you cannot submit both `partner_id` **and** `partner_group_id`. The controller aborts with HTTP 422 and message `Cannot target both a partner and partner group simultaneously.`  
> **Company guard:** when `company_id` is provided it must match the company attached to `price_list_id`, otherwise a 422 error is returned (`Price list and target company must match.`).

Response: `302` redirect for browser, or JSON (status `200`) with validation errors for failed requests.

## Retrieve / Edit Target

**GET** `/catalog/price-list-targets/{id}/edit`

Returns the target data, plus collections for price lists, companies, and partner groups to populate the form. Shape mirrors the listing output.

## Update Target

**PUT** `/catalog/price-list-targets/{id}`

Payload and validation are the same as **Create Target**. Only fields present in the request are updated.

## Delete Target

**DELETE** `/catalog/price-list-targets/{id}`

Soft delete is not used; the row is removed permanently. Response is a redirect/flash message in browser context or `204 No Content` for XHR.

## Resolver Context (Read-Only)

The `PricingService@quote` method now resolves price lists via `PriceListResolver`. When calling that service (internal or via a dedicated API), you can influence the targeting outcome by including the following context keys:

| Key | Type | Description |
|-----|------|-------------|
| `price_list_id` | int | Force a specific price list and bypass targeting. |
| `company_id` | int | Company scope for the actor/request. |
| `partner_id` | int | Primary partner/customer making the request. |
| `partner_group_id` / `partner_group_ids` | int / int[] | Explicit partner groups (otherwise inferred from memberships). |
| `channel` | string | Channel flag that must match the target `channel` (or null). |
| `date` | datetime string | Date used for validity checks; defaults to `now()`. |

Precedence order implemented by the resolver:

1. Partner + Company  
2. Partner  
3. Partner Group + Company (for each applicable group)  
4. Partner Group (per group)  
5. Company  
6. Global default

If no target matches, the resolver falls back to the first active price list scoped to the company (or global) whose validity range covers the requested date.

