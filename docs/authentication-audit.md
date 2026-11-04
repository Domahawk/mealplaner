# Authentication audit — TASK-09

Audited on 2026-11-04 against the existing Laravel 13.39.0 / PHP 8.5.12
application and integrated Vue 3.5.44 frontend. This task adds tests and
documentation only; it does not implement authentication.

## Findings and requirement status

**Application authentication is not implemented.** Laravel's native authentication,
hashing, encrypted-cookie, session, and request-forgery services are available.
Their availability does not establish working account flows.

| Requirement                  | Verified state                                                                                                   | Remaining implementation                                                                                               |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| Registration                 | No handler, form, User model, user factory, or users migration.                                                  | User persistence, validation, password policy, hashing on account creation, registration UI and tests.                 |
| Login / logout               | No endpoints, forms, or frontend actions.                                                                        | Native session login, throttling, session regeneration, logout invalidation and CSRF token regeneration; UI and tests. |
| Password hashing             | Native bcrypt driver, configured cost 12; synthetic-password hash/check tests pass.                              | Verify stored account passwords and any password update flows once implemented.                                        |
| Session persistence          | Local file sessions; an anonymous session survives a fresh application instance with its encrypted cookie.       | Verify an actual authenticated identity survives reload, and becomes inaccessible after logout/expiry.                 |
| Protected Laravel routes     | No application routes apply `auth` or `guest`. A test-only route with native `auth` returns JSON 401 to guests.  | Protect user resources and implement server-side authorization/ownership checks.                                       |
| Protected Vue routes / state | No auth guards, account store, login pages, or current-user request. Existing public routing tests pass.         | Session bootstrap, loading/error handling and navigation behavior, with tests.                                         |
| CSRF protection              | Native web middleware rejects invalid credentials and accepts valid token headers or same-origin Fetch Metadata. | Use the framework's protections on future session-backed mutations; verify the real account flows.                     |

There are no working account flows to verify or preserve. Authenticated persistence,
successful login/logout, and protected Vue navigation cannot be reported as passing.
No JWT, OAuth, Sanctum, Fortify, Breeze, Jetstream, Inertia, or Livewire package is
installed, and none was added.

## Existing backend boundaries

`bootstrap/app.php` registers web routes, standard `/api` routes and `/up`, and
retains Laravel's middleware defaults. API exceptions and JSON requests receive
Laravel JSON error responses.

The registered routes remain public liveness/readiness (`/up`, `/api/health`,
`/api/ready`), worker delivery (`/sw.js`), the web SPA fallback, and the framework's
signed GET/PUT `/storage/{path}` routes. The storage signatures grant access through
a signed URL; they are not logged-in user authorization. Tests verify unsigned GET
and PUT requests are rejected, with the upload test using a fake disk and asserting
that no file is written. No user or nutrition data endpoint currently exists.

Root `config/auth.php` and `config/hashing.php` are absent. Laravel 13 still loads
its framework configuration defaults: a `web` session guard, an Eloquent `users`
provider pointing to the **absent** `App\Models\User`, and bcrypt hashing. These
defaults are not a complete user system. Native guest redirection also expects a
named `login` route, which does not exist; an eventual HTML login flow must supply
that route or deliberately configure redirection.

The effective `web` middleware includes encrypted cookies, queued cookies,
session startup, session-backed validation errors, `PreventRequestForgery`, and
route bindings. The effective `api` group contains route bindings only: no session,
authentication, CSRF middleware, or rate limiter is configured there. Existing API
endpoints only read public health information. Adding a session-backed account
endpoint under `/api` will require an explicit middleware decision; the prefix
alone does not provide those protections.

## Sessions, cookies and CSRF

The inspected local session configuration uses file storage, a 120-minute lifetime,
HttpOnly cookies and SameSite=Lax. Secure cookies are disabled for local HTTP.
Server-side session payload encryption is disabled; the web middleware still
encrypts the session ID cookie. File sessions do not require a sessions table.
The real local environment and application key were preserved, without displaying
credentials, cookie values or keys.

Laravel 13's `PreventRequestForgery` accepts same-origin Fetch Metadata, with
session-token validation as the normal fallback. Tests cover rejection with no
token/metadata, cross-site requests without a token, and cross-site requests with
an invalid token. They also cover acceptance of `X-CSRF-TOKEN`, the encrypted
`XSRF-TOKEN` cookie supplied as `X-XSRF-TOKEN`, and same-origin metadata.
This is the framework's intended behavior, not disabled CSRF protection.

Laravel normally skips request-forgery validation in feature tests. The audit uses
a test-only subclass that disables **only that test shortcut**, preserving testing
mode and database safety guards. Test-only routes are registered in the test app;
they are not reachable from the running application. Session files use a dedicated
temporary directory per test and are removed afterward. They never use development
session storage. Only synthetic test values are hashed or submitted.

## Frontend integration and reloads

`resources/js/app.ts` registers the existing router and Pinia. Routes are Home,
About, and Not Found; stores manage theme and connectivity only. There is no
authentication state in Pinia or browser storage. The relative `/api/ready` request
is a public GET, not a current-user lookup. The Blade entry has no auth form or
CSRF token meta element; no account mutation client currently needs one.

Unknown paths such as `/login` can return the Laravel SPA shell and then the Vue
Not Found page. A successful HTML response at that path is not a login page.
The production PWA can also serve its public offline shell. Future account routes
must coordinate Laravel routing, Vue routing, and the Workbox fallback exclusions.
Private/authentication responses must not be cached as offline public content.

## Security concerns and subsequent work

- Missing registration/login/logout and ownership enforcement block the authenticated
  MVP. No existing private account endpoint was found leaking user data.
- Implement native Laravel account persistence and session flows within this same
  application. Add validation, an appropriate password policy, rate limiting,
  session ID regeneration on login, and session invalidation/token regeneration on
  logout. Do not infer a need for a separate token system from the missing UI.
- Add real guest/authenticated integration tests, account creation/hash storage,
  invalid credentials and throttling, CSRF rejection on actual mutations, reload
  persistence, post-logout denial, and cross-user authorization before shipping.
- Production requires HTTPS, Secure cookies, debug disabled, and correctly trusted
  proxies. Decide session storage/lifecycle for the deployment; local file sessions
  alone do not establish shared or durable sessions across multiple instances.
- Signed storage access is a separate capability contract. Review authorization
  before issuing URLs for future user-owned files.

These are implementation and deployment prerequisites, not authorization to add
features or change settings during this audit. No production security assessment,
authenticated browser test, password reset, or email verification flow was performed.

## Verification and references

New coverage is in `tests/Feature/WebSecurityTest.php`,
`tests/Feature/PasswordHashingTest.php` and the unsigned upload case in `SpaTest.php`.
Existing backend route/database tests and Vue public routing/component tests remain
in place. Commands and executed results are recorded in
[VERIFICATION.md](VERIFICATION.md); isolated database conventions are in
[testing.md](testing.md).

The installed framework source was inspected alongside Laravel's official
[authentication documentation](https://github.com/laravel/docs/blob/13.x/authentication.md),
[request-forgery documentation](https://laravel.com/framework/docs/csrf),
[hashing documentation](https://laravel.com/framework/docs/hashing) and
[session documentation](https://laravel.com/framework/docs/session).
