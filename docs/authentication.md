# Session authentication

TASK-10 completes registration, login and logout in the existing Laravel/Vue
application. It extends Laravel's native `web` session guard and Eloquent provider;
no authentication package, JWT, OAuth or Sanctum dependency was added.
The [TASK-09 audit](authentication-audit.md) records the historical starting point.

## Routes and responsibilities

| Route                         | Behavior                                                                                                                                    |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /login`, `GET /register` | Laravel serves the existing Blade/Vue entry; Vue renders the account forms.                                                                 |
| `GET /account`                | Native Laravel `auth` protects direct navigation; guests redirect to the named login route. The Vue router also checks the server session.  |
| `GET /api/auth/session`       | Public session bootstrap; returns `{"user":null}` for a guest or the current user's ID, name and email. Issues native session/XSRF cookies. |
| `GET /api/auth/user`          | Same user response, protected by native `auth`; guests receive JSON 401.                                                                    |
| `POST /api/auth/register`     | Validates and creates a user, hashes the password, starts a session and returns the user with HTTP 201.                                     |
| `POST /api/auth/login`        | Native credential verification, session regeneration and user response with HTTP 200.                                                       |
| `POST /api/auth/logout`       | Requires authentication; logs out, invalidates session data, regenerates the CSRF token and returns HTTP 204.                               |

The JSON endpoints are deliberately declared in `routes/web.php` before the SPA
fallback, under `/api/auth`. They inherit Laravel's **web session, encrypted-cookie
and CSRF middleware**, unlike the stateless health routes in `routes/api.php`.
The `/api` path still receives native JSON exception responses. Private successful
responses use `Cache-Control: no-store, private`; password hashes and remember tokens
are never serialized. The account response also disables caching.

`AuthenticationController` handles HTTP/session actions; Form Requests validate
input. `App\Models\User` uses the framework's `hashed` cast and native provider
defaults. The users migration adds only account persistence, with a unique email
constraint. No domain schema or password-reset infrastructure was introduced.

## Validation and abuse protection

- Registration requires a name, valid email and confirmed password. Emails are
  trimmed and lowercased on registration and login.
- Passwords require at least 12 characters with letters and numbers. Bcrypt's
  72-byte limit and null-byte restriction are validated explicitly, including
  multibyte input, rather than allowing truncation or a hashing error.
- Incorrect credentials and unknown emails produce the same JSON 422 field error.
  Other field errors use Laravel's normal 422 response structure.
- Login allows five attempts per normalized email/IP per minute and 30 per IP;
  registration allows five per IP per minute. These limits count all requests,
  including validation failures. Native throttling returns HTTP 429/Retry-After.
  Limit keys hash the email/IP combination rather than retaining raw email in a key.
- Already-authenticated account switching/registration is rejected; successful
  logout must occur before using another account.

The existing configured cache stores the rate limits. Testing uses an isolated
array cache; local development uses the existing file cache. Production must choose
appropriate shared cache/session storage when running multiple instances.

## Vue integration and CSRF

`resources/js/stores/auth.ts` holds account state in memory. The initial router
navigation restores the session from the server; entering private or guest-only
pages rechecks it. No password, token or account record is persisted in localStorage.
Native cookies retain the session across page reloads.

`resources/js/lib/api.ts` extends the existing same-origin HTTP integration for
account actions. Requests use same-origin credentials, no-store and a timeout.
Mutations send Laravel's encrypted `XSRF-TOKEN` cookie as `X-XSRF-TOKEN`; the HttpOnly
session ID cookie is never read by application JavaScript. Laravel 13's native
same-origin Fetch Metadata handling and token fallback remain enabled.

The shared login/register view has labeled fields, password-manager autocomplete,
validation feedback, an error summary with keyboard focus, submission state and
responsive styling. Password fields clear after submission. Logout clears the store
and returns to login. Session/CSRF failures invalidate stale client state; the next
explicit submission bootstraps a fresh session rather than replaying a mutation
automatically. Network/server errors receive safe messages. Server authorization
remains authoritative; Vue navigation checks are only a UI convenience.

The PWA never caches API responses; `/account` is excluded from offline navigation
fallback. Its generated public shell contains no account data. Offline session
verification cannot grant access to the account page.

## Setup and verification

Use the existing Docker environment and preserve local environment values and the
application key. Confirm the target database and inspect migration status before
applying pending migrations. On a confirmed development database, the ordinary
Laravel migration workflow described in [database.md](database.md) applies the new
users migration. Never reset an existing database.

For TASK-10, the existing dedicated local Mealplaner database was verified empty,
then only `2026_11_04_000000_create_users_table.php` was applied with Laravel's
`migrate --path` option. This also created migration history. The unrelated cache
migration remains pending; existing file-cache/file-session settings work without
its tables. No development users were created for verification.

Run from the repository root:

```bash
docker compose exec -T --user app backend composer quality
docker compose exec -T --user app backend composer test
docker compose exec -T --user node frontend npm run quality
docker compose exec -T --user node frontend npm run test:unit
docker compose exec -T --user node frontend npm run build
docker compose exec -T --user node frontend npm run verify:build
```

Backend account tests use the existing generated, restricted PostgreSQL database
and transactional isolation. They exercise real CSRF validation on account endpoints,
file-backed sessions in private temporary directories, fresh guard/session instances,
login/registration rotation, and rejection of the old cookie after logout. Optional
SQLite memory tests remain supported. Frontend tests use real Vue Router/Pinia and
mock only HTTP responses. Coverage commands remain in [testing.md](testing.md).
Executed results, including the isolated Chrome smoke test, are recorded in
[VERIFICATION.md](VERIFICATION.md).

## Boundaries and deployment prerequisites

Password reset, email verification, remember-me login, two-factor authentication,
account editing and cross-user domain authorization are outside TASK-10. No domain
resources exist yet. File sessions retain the configured idle lifetime; they do not
promise sessions across storage loss or multiple application instances.

Production requires HTTPS, Secure cookies, debug disabled, correctly trusted proxies
and deliberate shared/durable session and cache storage. Existing local configuration
was not replaced. These flows are verified locally, not deployed or security-certified.
