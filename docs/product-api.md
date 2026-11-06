# Product REST API

TASK-14 exposes the existing [Product model](products.md) through same-origin JSON
endpoints. Laravel's existing `web` session guard is authoritative; no bearer tokens,
Sanctum, frontend components, search or filtering were introduced.

## Endpoints and responses

| Method | URL                       | Successful response                                          |
| ------ | ------------------------- | ------------------------------------------------------------ |
| GET    | `/api/products`           | 200; paginated `data` array with Laravel `links` and `meta`. |
| POST   | `/api/products`           | 201; created product under `data`.                           |
| GET    | `/api/products/{product}` | 200; one product under `data`.                               |
| PUT    | `/api/products/{product}` | 200; updated product under `data`; requires all core fields. |
| PATCH  | `/api/products/{product}` | 200; updated product under `data`; accepts partial changes.  |
| DELETE | `/api/products/{product}` | 204; empty body.                                             |

`ProductResource` explicitly returns `id`, `name`, `brand`, `package_weight_g`,
`calories_per_100g`, `protein_per_100g`, `carbohydrates_per_100g`, `fat_per_100g`,
`created_at` and `updated_at`. Owner/account data is not serialized. Decimal values
are fixed-scale strings; dates are UTC ISO 8601 strings. Successful responses are
private/no-store. Individual resources use Laravel's normal `data` wrapper; paginated
collections retain its standard metadata. See [Laravel API Resources](https://laravel.com/framework/docs/eloquent-resources).

Example POST/PUT body:

```json
{
    "name": "Rolled oats",
    "brand": "Example brand",
    "package_weight_g": "500.125",
    "calories_per_100g": "367.10",
    "protein_per_100g": "13.20",
    "carbohydrates_per_100g": "60.00",
    "fat_per_100g": "6.50"
}
```

All nutrition describes 100 g, not the supplied package. POST and PUT require name
and all four nutrition fields. Brand and package grams can be omitted or null;
on updates, omitted optional fields are preserved. PATCH preserves every omitted
field; an empty PATCH is a valid no-op. Send null to clear optional information.

## Validation and pagination

Form Requests enforce:

- Name: a nonblank string, at most 255 characters. Brand: nullable string, at most
  255 characters. Embedded null bytes are rejected. Laravel trims strings and
  converts empty optional fields to null.
- Calories: 0 through 999,999.99; each macro: 0 through 100 g. Values may have zero,
  one or two decimal places. Package grams: greater than zero, at most 9,999,999.999,
  with at most three decimal places.
- JSON numbers or ordinary numeric strings are accepted; use strings to preserve
  decimal precision. Excess fractional digits are rejected rather than silently
  rounded. Non-numeric values, arrays, booleans, non-finite values and scientific
  notation strings are rejected.
- Client-supplied `user_id`, `owner_id`, `id`, `created_at` and `updated_at` are
  rejected even when null. Other unknown input is ignored and never persisted;
  only validated fields are passed to Eloquent.

Laravel [validation rules](https://laravel.com/framework/docs/validation) return
HTTP 422 with a `message` and field-keyed `errors` arrays. JSON errors are retained
even when the request accepts HTML.

Lists use ascending primary-key order, independent of duplicate names or timestamps.
`page` defaults to 1 and must be an integer from 1 through 2,147,483,647;
`per_page` defaults to 20 and accepts 1 through 100. Pagination links retain the
validated page size. Totals, data and page counts include only the current owner's
products. An out-of-range page returns an empty `data` array with the owner's totals.
Example: `/api/products?page=2&per_page=20`. Sorting overrides, search and filtering
are not implemented.

## Authentication and ownership

Routes intentionally live in `routes/web.php`, alongside the existing account JSON
routes. They inherit cookies, session locking and native CSRF protection, then require
`auth`. Health/readiness remain public in `routes/api.php`. The existing Laravel and
PWA fallbacks already exclude `/api`, so no frontend routing change is needed.

First bootstrap `/api/auth/session` and use the existing login flow. The browser
sends its session cookie and the encrypted `XSRF-TOKEN` cookie as the `X-XSRF-TOKEN`
header on mutations, through the existing same-origin request conventions. Do not
disable CSRF or use a separately trusted client identity.

The `product` route binding resolves IDs through the authenticated user's
`products()` relationship before Form Request validation. Invalid IDs, missing
products and another owner's products return JSON 404, including for mutation
requests with invalid payloads. Listing and creation also use that relationship;
the client cannot select or transfer ownership. Every action invokes the existing
ProductPolicy through native Gate authorization. A forbidden action on an otherwise
visible resource returns JSON 403. See [authorization.md](authorization.md).

Unauthenticated requests return JSON 401. As with the existing authentication routes,
an unsafe request with invalid CSRF credentials may be rejected first with HTTP 419;
preserving that middleware behavior is intentional. Native session locking can
return 423 if a concurrent session request exceeds its lock wait.

## Verification and local prerequisites

`tests/Feature/ProductApiTest.php` covers CRUD, resources, pagination, authentication,
policy invocation, cross-user access, invalid IDs, input validation, guarded fields
and enforced CSRF. `AuthenticationTest` additionally verifies product access through
actual login cookies, fresh guard/session instances, encrypted XSRF headers and
logout invalidation. All records remain in the existing guarded testing environment.

Run the canonical suite with:

```bash
docker compose exec -T --user app backend composer test
```

The read-only TASK-14 development status check still shows the existing products
migration pending. It must be applied through the reviewed [migration workflow](database.md)
before using authenticated CRUD against that development database. No development
migration, reset, record write or schema change was performed in TASK-14. The isolated
PostgreSQL runner applies existing migrations to its new test database and verifies
the complete API there. No new migration or dependency is required by this task.
