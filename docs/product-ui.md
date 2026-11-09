# Product management interface

TASK-15 adds the authenticated `/products` page to the existing Laravel-hosted Vue
application. Use **Products** in the signed-in navigation or **Manage your food
products** on the Account page. No separate application, product store, UI library,
API endpoint or database schema was introduced.

## User workflow

- Product cards show name, brand, optional package grams and all nutritional values
  **per 100 g**. Calories are labeled kcal; protein, carbohydrates and fat are grams.
  Missing brand information is stated explicitly. Exact API decimal strings are
  displayed without floating-point conversion.
- **Add product** opens an inline form. **Edit** prefills that same form with the
  selected product. Name and all nutrition fields are required. Brand and package
  weight are optional; clear them to send null. Inputs accept a decimal point, with
  up to two fractional digits for nutrition and three for package grams. Client
  checks give immediate feedback; Laravel remains authoritative for validation.
- Successful creation opens the current last page, where new ascending-ID records
  appear. Editing refreshes the current page. Deletion refreshes and returns to the
  last available page if the previous page became empty. None reloads the document.
- **Delete** opens an application-styled HTML dialog naming the product and warning
  that deletion cannot be undone. **Keep product** or Escape cancels; **Delete product**
  confirms. The dialog initially focuses cancellation, prevents background interaction
  and restores focus on cancellation. Closing is blocked while deletion is in progress.
- Previous/Next controls expose the current page, page count and owned-product total.
  Boundary controls and controls that could conflict with an open form or pending
  operation are disabled. Search and filtering remain TASK-16 work.

Loading, empty, success and error states are visible. Forms prevent duplicate saves,
and deletion cannot be submitted twice while pending. Laravel 422 errors appear
beside their matching fields with `aria-invalid` and `aria-describedby`; the error
summary receives focus. Inputs remain available for correction after a failed save.
Notifications use status/alert regions, with no browser alert/confirm/prompt calls.
Mobile layouts stack the form and cards; desktop uses two columns. Existing light,
dark, focus and reduced-motion conventions are reused.

## Integration and boundaries

`ProductsView.vue` owns page-local lists, pagination and operation state.
`ProductForm.vue` owns editable strings and client feedback; `DeleteProductDialog.vue`
owns the native modal. `lib/products.ts` types and validates the existing
[Product API](product-api.md) response contract. It uses the shared `jsonRequest`
transport in `lib/api.ts`: same-origin cookies, encrypted XSRF headers for mutations,
no-store requests and the existing eight-second timeout. Authentication keeps its
existing response/error contract; no replacement authentication system was added.

Product data is not persisted in browser storage or a global store. Stale list
responses are ignored after navigation or an account change. A 401 clears private
content/session state and navigates to login. A 419 refreshes the existing session
bootstrap and asks the user to review/retry; mutations are never automatically
replayed. HTTP failures preserve safe status-based messages without exposing server
diagnostics. Network/time-out or malformed-response errors may occur after a server
has saved a change, so the UI advises refreshing before retrying. A successful save
followed by a failed list refresh closes the form and keeps its success notification
alongside a reload error, preventing it from appearing to be an unsaved form.

Laravel also protects the `/products` HTML entry with native `auth` and private/
no-store headers. Unknown `/products/...` paths return 404 instead of the SPA shell.
The PWA navigation fallback excludes this namespace, as it already excludes `/api`
and `/account`. Vue's guard supplements Laravel/API ownership enforcement.

## Verification and prerequisites

`tests/frontend/products.test.ts` mounts the real application/router/Pinia with
controlled HTTP responses to verify rendering, navigation, field validation, Laravel
errors, exact payloads, CSRF headers, CRUD actions, pagination, duplicate prevention,
dialog cancellation, failures and expired-session behavior. The JSDOM dialog method
shim is test-only; actual modal focus, Escape and background inertness were verified
in Chrome. Existing authentication and connectivity tests remain in the full suite.
Backend authorization tests cover the new private HTML route and fallback boundary.

```bash
docker compose exec -T --user node frontend npm run test:unit
docker compose exec -T --user node frontend npm run test:coverage
docker compose exec -T --user node frontend npm run quality
docker compose exec -T --user node frontend npm run build
docker compose exec -T --user node frontend npm run verify:build
docker compose exec -T --user app backend composer quality
docker compose exec -T --user app backend composer test
```

The existing development products migration remains pending. Follow the reviewed
[database migration workflow](database.md) before using CRUD against that database.
TASK-15 browser verification used a generated restricted PostgreSQL database, existing
migrations and temporary file sessions, then removed only its own test resources.
No development records or schema were changed. See [VERIFICATION.md](VERIFICATION.md)
for executed checks, responsive/browser results and limits.
