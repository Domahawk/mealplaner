# Product data model

TASK-13 introduces the Product model, migration, factories and ownership Policy.
TASK-14 adds the [Product REST API](product-api.md), and TASK-15 adds the
[management interface](product-ui.md). The migration has been
verified on isolated PostgreSQL databases; read-only TASK-16 inspection now records
it as applied in development.

## Schema and units

Every nutritional value describes **100 grams of the product**, regardless of package
size. All values are required unless marked nullable; unknown nutrition is not
silently recorded as zero.

| Column                     | PostgreSQL type        | Rules                                                                       |
| -------------------------- | ---------------------- | --------------------------------------------------------------------------- |
| `id`                       | bigint primary key     | Stable identifier for future references.                                    |
| `user_id`                  | bigint foreign key     | Required existing User; owner deletion is restricted.                       |
| `name`                     | varchar(255)           | Required, with at least one non-whitespace character.                       |
| `brand`                    | nullable varchar(255)  | Optional; when supplied, must contain a non-whitespace character.           |
| `package_weight_g`         | nullable numeric(10,3) | Grams, greater than zero and at most 9,999,999.999. Null means unspecified. |
| `calories_per_100g`        | numeric(8,2)           | kcal, from 0 through 999,999.99.                                            |
| `protein_per_100g`         | numeric(5,2)           | Grams, from 0 through 100.                                                  |
| `carbohydrates_per_100g`   | numeric(5,2)           | Grams, from 0 through 100.                                                  |
| `fat_per_100g`             | numeric(5,2)           | Grams, from 0 through 100.                                                  |
| `created_at`, `updated_at` | nullable timestamp     | Managed by Eloquent.                                                        |

The calorie maximum expresses column capacity, not a recommended dietary limit.
Each macronutrient is bounded independently; no combined-total constraint or
calorie-conversion formula is imposed on reported nutritional information.
PostgreSQL CHECK constraints also reject non-finite values. Name/brand uniqueness
is deliberately not required, even for a single owner.

Exact `numeric` columns avoid binary floating-point storage errors. Eloquent's
`decimal:2` and `decimal:3` casts return fixed-scale **strings**, including during
serialization. Pass decimal strings when writing values; future calculation code
must preserve decimal precision rather than implicitly converting to PHP floats.
PostgreSQL rounds excess fractional digits to the declared scale (for example,
`1.235` grams of protein becomes `1.24`). TASK-14 Form Requests reject excess
fractional digits before persistence; model casts alone are not input validation.
See [PostgreSQL numeric types](https://www.postgresql.org/docs/18/datatype-numeric.html).

## Ownership and future references

`Product::user()` is a `BelongsTo` relationship; `User::products()` is `HasMany`.
The non-unique `(user_id, name)` index supports owner-scoped lists and foreign-key
lookups without a redundant separate owner index. `user_id` is excluded from
mass assignment: create through the authenticated user's relationship using only
validated business fields.

Laravel automatically discovers `ProductPolicy`. Authenticated users can create
and list their own products; view/update/delete checks allow the owner and deny
foreign private records as 404. Guest checks are denied. A Policy does not filter
collections or automatically authorize Eloquent queries. The TASK-14 CRUD layer
applies authentication, owner-scoped queries/binding, explicit action authorization
and CSRF protection as described in [authorization.md](authorization.md).

The owner foreign key uses `ON DELETE RESTRICT` to prevent implicit product loss
when deleting an account. A future account deletion workflow must handle owned data
deliberately. TASK-18 now references Products through the [recipe ingredient pivot](recipes.md).
Composite ownership FKs restrict referenced product deletion and prevent cross-user
links. Product DELETE returns 409 while referenced; detaching its recipe uses allows
deletion again. Food-log references, nutrition snapshots and historical deletion
rules remain future work. No soft-delete or historical-data scheme is
introduced here.

## Factories, verification and migration safety

`Product::factory()->create()` creates a product and owner in the selected testing
database. Use `Product::factory()->for($user)` to reuse an existing owner. Native
Product/User factories use deterministic nutritional fixtures and unique synthetic
user emails. The installed Laravel factory supports this without Faker; no dependency
was added. Factory autoloading is registered in the root Composer manifest.

`tests/Feature/ProductTest.php` covers relationships, optional fields, serialization,
owner query boundaries, mass-assignment protection and real Policy discovery.
`tests/Postgres/ProductTest.php` verifies raw exact decimals, rounding, numeric
metadata, the owner index and database rejection of invalid direct SQL inserts,
including missing/foreign owners, invalid text, null nutrition, out-of-range and
non-finite values. It also verifies restricted owner deletion without losing records.
The existing guarded test runner applies ordinary migrations only to its generated
PostgreSQL database; test writes roll back. See [testing.md](testing.md).

SQLite retains optional migration/model smoke coverage. Its numeric affinity and
missing PostgreSQL-specific CHECK constraints cannot establish PostgreSQL precision
or constraint correctness; the canonical PostgreSQL suite is authoritative.

At TASK-13 the new migration remained pending in development. Read-only TASK-16
inspection now confirms it is applied; no migration was run in TASK-16. Continue
to use the reviewed [migration workflow](database.md), after confirming the target.
TASK-13 did not run development migrations, change existing records, reset a database,
or introduce recipe/food-log tables.
