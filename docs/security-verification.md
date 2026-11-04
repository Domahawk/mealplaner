# Authentication and authorization security verification

TASK-12 reviewed the existing Laravel 13.39.0 / PHP 8.5.12 session authentication
and integrated Vue application on 2026-11-04. Work began from clean local `main`
on `feature/task-12-auth-security`. Existing tests were reused and extended; no
authentication package, dependency, model, migration or domain feature was added.

## Coverage and evidence

| Requirement                       | Verification                                                                                                                                                                                                                                       |
| --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Registration and password storage | Real account creation, normalized email uniqueness, independent password-policy failures, malformed inputs, byte/null limits, hash-shaped password round-trip and safe response fields.                                                            |
| Login and invalid credentials     | Successful login, generic errors for wrong/unknown credentials, validation, session/CSRF rotation and no identity selection through client IDs.                                                                                                    |
| Logout and persistence            | File-backed sessions across fresh guard/store instances, browser reloads, invalidation, old-cookie replay denial, expiry and independent two-user sessions.                                                                                        |
| Concurrent session requests       | Canonical HTTP feature tests enforce the native session lock with file and array lock stores. An isolated four-worker HTTP probe verifies delayed reads cannot undo logout with locking enabled.                                                   |
| CSRF and cookies                  | Native web checks exercised without PHPUnit's CSRF shortcut: missing/invalid credentials, encrypted XSRF headers, same-origin Fetch Metadata and stale tokens after login/logout. HttpOnly/Lax and configured Secure-cookie attributes are tested. |
| Login throttling                  | Five normalized email/IP attempts, cookie-session changes, expiry/recovery, 30-per-IP breadth limits and distinct-IP independence. A multi-request HTTP probe verifies file-cache persistence across fresh PHP requests/browser sessions.          |
| Web/API authorization             | Guest web redirects, API/JSON 401 responses, public pages/health, unsupported private/API 404s, forged IDs and two-user account isolation. Existing unsigned storage access/upload tests verify its native 403 contract.                           |
| Vue security boundaries           | Server-owned identity, guest/expired-session denial, forged browser state, invalid response identities, malformed 401 bodies, safe 403/404/429/500 errors and verified recovery from another tab's login conflict.                                 |
| Database safety                   | Existing bootstrap and actual role/database guards precede writes; generated PostgreSQL databases and transactions, or optional SQLite memory storage, are used. Test file sessions/locks use private temporary directories.                       |

Primary automated coverage remains in
[`AuthenticationTest.php`](../tests/Feature/AuthenticationTest.php),
[`WebSecurityTest.php`](../tests/Feature/WebSecurityTest.php),
[`AuthorizationTest.php`](../tests/Feature/AuthorizationTest.php),
[`SpaTest.php`](../tests/Feature/SpaTest.php) and
[`authentication.test.ts`](../tests/frontend/authentication.test.ts).

## Findings and fixes

### Password input interpreted as a stored hash

Registration previously relied solely on Eloquent's `hashed` cast. That cast
intentionally preserves an already-encoded hash. A submitted password that happened
to be a valid bcrypt string was therefore stored unchanged: login with the submitted
string failed while its underlying plaintext could verify.

Registration now explicitly calls Laravel `Hash::make` on validated plaintext before
creating the User. The model cast remains a safeguard for internal model writes.
The regression test verifies the submitted string succeeds, its underlying plaintext
fails, and the submitted value is not the stored hash. This changes no existing
passwords, hashing algorithm or configuration.

An account created with such an input before this fix may need explicit password
recovery: the intended literal cannot be inferred safely from a stored hash. Existing
credentials were not inspected or rewritten, and login never accepts a raw stored
hash as an alternative credential.

### Overlapping requests could restore a logged-out session

Without session blocking, a request that already loaded an authenticated file session
could save it after another request had invalidated it. A controlled HTTP negative
test reproduced authentication returning after logout when blocking was disabled.

`config/session.php` now enables Laravel's native per-session blocking for the
entire web-session lifecycle, including reads. Requests use the existing atomic-lock
cache store, with a 120-second lease and five-second acquisition wait. The fixed
HTTP probe held a read inside that lifecycle, verified logout waited for it, then
confirmed 204 logout and 401 for both the current and replayed old cookie.
Health requests and requests for a different session remain independent.

Production must share the lock/session stores across instances and bound request
lifetimes below the lock lease. Array cache is a test-only lock store. A sustained
lock conflict can time out; native exception handling and safe client failure handling
are retained. This is a tested bounded-overlap scenario, not an unrestricted load test.

### Non-JSON authentication errors retained stale Vue state

The API helper previously parsed JSON before preserving an error status. An empty
or HTML 401 body raised a parse error instead of `AuthenticationError`, leaving
stale account state visible. Parsing now tolerates an absent error body while still
classifying the HTTP status; 401 clears state and returns to login. Malformed
successful identity responses still fail closed and grant no access.

### Another tab's login conflict did not recover

A 409 from an already-authenticated session previously cleared client identity
without refreshing it. Login/register now perform a safe session read on that
conflict and continue only if the server supplies a valid current user. They do
not blindly treat 409 as success or replay a mutation. Positive and guest-negative
tests cover this, and Chrome verifies the actual shared-cookie two-tab flow.

## Executed verification

The full PostgreSQL backend suite passes **88 tests / 1,481 assertions**. Optional
SQLite passes **83 tests / 820 assertions**. Frontend tests pass **32 cases**.
Pint, PHPStan/Larastan, ESLint, Prettier, strict Vue TSC, the production build and
asset checks pass. PHP app/routes line coverage is **98.81%**; frontend line coverage
is **89.09%**, preserving existing coverage scope. Commands and exact results are
in [VERIFICATION.md](VERIFICATION.md); reusable commands remain in [testing.md](testing.md).

Chrome and HTTP probes use generated restricted PostgreSQL credentials, synthetic
users/key, private session/cache/storage directories and a temporary multi-worker
server. Pause/negative-control hooks exist only in its temporary router, never in
application routes. Servers, worker groups and their newly generated resources are
removed afterward. Development data, keys, environment values and volumes are preserved.

`composer audit --locked` reports no advisories. `npm audit --omit=dev` reports zero
production vulnerabilities. Full `npm audit` returns nonzero for **26 existing
development-graph findings: 2 low and 24 moderate, no high/critical**. Those require
scoped tool maintenance; no forced incompatible upgrades or audit suppressions were used.

## Remaining limits and MVP readiness

No unresolved critical authentication/authorization finding was identified in the
reviewed current flows after these fixes. BL-02's implemented account/access-control
foundation is ready for MVP feature development within this verified scope.

- Products, recipes and food logs do not exist. Their actual Policies, owner-scoped
  queries, reference validation and cross-user tests remain mandatory with each
  feature; the account tests cannot prove future resource authorization. See
  [authorization.md](authorization.md).
- There is no domain action requiring a policy-driven 403 yet. Existing storage
  403s are signed-URL capability checks, not user ownership. Future file access must
  authorize the owner before issuing a signed URL.
- This is local verification, not a production deployment or full penetration test.
  Local HTTP uses non-Secure cookies; production needs HTTPS, Secure cookies, debug
  disabled, correct trusted proxies and appropriately durable/shared session/cache
  storage. Secure-cookie behavior was tested with an isolated HTTPS-request fixture.
- Existing PHP session serialization is preserved. A planned move to JSON needs
  explicit session-compatibility handling; silently changing it could log out active
  users. No application key or existing session format was changed.
- Password reset, email verification, remember-me, MFA and account editing remain
  outside the implemented registration/login/logout scope. Duplicate-email validation
  reveals whether an email is registered, as before; login failures remain generic
  and registration/login are throttled.
- Dependency audits cover known registry advisories, not every possible defect. The
  development-tool findings above remain open. Production TLS/proxy configuration,
  distributed deployment and arbitrary long-running request behavior were not certified.

The native mechanisms were checked against installed framework source and official
[Laravel session blocking](https://laravel.com/framework/docs/session),
[hashing](https://laravel.com/framework/docs/hashing) and
[authentication](https://github.com/laravel/docs/blob/13.x/authentication.md) documentation.
