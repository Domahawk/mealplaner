# Authorization and ownership

TASK-11 audits and verifies access control in the existing single Laravel/Vue
application. Authentication remains Laravel's native `web` session guard; Vue
navigation checks supplement server enforcement. No new auth system, domain model,
migration, placeholder policy, global ownership scope or repository layer is added.

## Current access-control audit

| Surface                              | Server boundary                                                                                                                              | Browser behavior                                                                                         |
| ------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `/`, `/about`, `/login`, `/register` | Public Blade/Vue entry.                                                                                                                      | Guest account forms remain accessible; authenticated visitors to login/register return to their account. |
| `GET /api/auth/session`              | Public session bootstrap; identity comes only from the server session. Guests receive `user: null`.                                          | Restores in-memory Pinia state; no browser-stored identity is authoritative.                             |
| `GET /account`                       | Native `auth`; a guest HTML request redirects to named `/login`, a JSON request receives 401. Successful entry is private/no-store.          | `requiresAuth` rechecks the server session before navigation.                                            |
| `GET /api/auth/user`                 | Native `auth`; guests get JSON 401 even when requesting HTML. Only the session user's ID, name and email are returned.                       | No ID parameter selects another account.                                                                 |
| `POST /api/auth/logout`              | Native `auth` plus web session/CSRF middleware; only the requesting session is invalidated.                                                  | Clears client state and returns to login.                                                                |
| Login/registration mutations         | Public, with web session/CSRF protection, Form Requests and native throttling. Credentials/validated registration fields determine identity. | Existing field feedback, error handling and session regeneration are preserved.                          |
| `/api/health`, `/api/ready`, `/up`   | Public liveness/readiness; no user data.                                                                                                     | Existing checks remain public.                                                                           |
| `/sw.js` and public assets           | Public worker/build delivery.                                                                                                                | No runtime API caching. `/account` is excluded from the offline navigation fallback.                     |
| GET/PUT `/storage/{path}`            | Framework signed-URL capability checks; unsigned private requests are denied.                                                                | A signature is not account ownership. No user file model or URL-issuing endpoint exists.                 |

`bootstrap/app.php` preserves native middleware and JSON exception rendering for
`/api` paths or JSON requests. The account JSON routes deliberately inherit `web`
session/cookie/CSRF middleware from `routes/web.php`; health routes use the stateless
API group. Existing validation requests authorize public account submissions, not
domain resource actions. No application Policies, Gates, role bypasses, user listing,
or arbitrary-user lookup endpoints existed at audit time.

The SPA fallback previously served an HTML shell for unregistered `/account/...`
paths. TASK-11 reserves that namespace alongside `/api` and framework paths: unknown
account paths now return 404 instead of the SPA entry. The registered `/account`
route retains its native authentication middleware. The previous shell contained no
private records, so this is boundary hardening, not evidence of a prior data leak.

The User is the only application model. Current account operations use the session
user directly; they do not fetch a target User from a supplied ID. Tests use two
accounts and independent cookies to verify that forged query/body/header identifiers
cannot select another identity, overwrite another registration, or log out another
session. No redundant UserPolicy is needed for a resource lookup that does not exist.

## Required conventions for future features

Products, recipes and food logs do not exist yet. The following patterns are
requirements for their implementation, not working domain APIs.

1. **Authenticate at the server boundary.** Apply native `auth` to every private
   route, including listings, exports, aggregates and mutation endpoints. Preserve
   session/CSRF middleware on cookie-authenticated mutations. Register routes before
   the SPA fallback and reserve new backend/private namespaces in Laravel and Workbox.
2. **Derive ownership from the session.** Give each real user-owned model an enforced
   owner relationship/foreign key when its schema is introduced. Create through
   `$request->user()->products()` or the equivalent relationship. Explicitly allow
   validated business fields; reject client `user_id`/`owner_id` rather than assigning
   them. Never transfer ownership as an incidental edit or trust a URL's user ID.
3. **Scope every query before returning data.** Start lists, search, counts,
   pagination, daily totals and item resolution from the authenticated user's
   relationship. Missing and foreign private IDs must both resolve to 404. A policy's
   `viewAny` permission does not filter a collection. Group `orWhere` conditions so
   they cannot escape the owner predicate. Apply the same rules in queued jobs and
   services, passing explicit owner context rather than depending on HTTP globals.
4. **Authorize actions with the real model's Policy.** Add `app/Policies/ProductPolicy.php`
   alongside the actual Product feature, using Laravel's standard model/policy
   discovery. Do the same for Recipe and FoodLog when introduced. Use non-nullable
   `User` parameters for private abilities, and cover the applicable `viewAny`,
   `view`, `create`, `update`, and `delete` actions. Invoke `Gate::authorize`, native
   `can` middleware or a Form Request authorization check; defining a policy alone
   does not enforce it. No global allow-all Gate or speculative admin bypass.
5. **Validate referenced ownership.** A recipe may reference only its owner's
   products; a food log may reference only allowed products/recipes. Scope existence
   validation and subsequent reads to the current owner, including every submitted
   ingredient/array item. Nested scoped bindings restrict children to their parent
   but do not establish that the authenticated user owns that parent. Authorize both
   levels and keep multi-record writes atomic; do not attach a foreign child by ID.
6. **Keep private files private.** When uploads are introduced, resolve the user's
   file and authorize access before issuing a signed URL. Do not expose guessed
   paths, credentials or an unrestricted file-signing endpoint. The current native
   storage route's signature checks alone are not a future ownership policy.

Illustrative future code, **not implemented in TASK-11**:

```php
use Illuminate\Support\Facades\Gate;

// Relationship scoping hides another owner's record before authorization/serialization.
$product = $request->user()->products()->findOrFail($id);
Gate::authorize('update', $product);
$product->update($request->safe()->only(['name', 'calories', 'protein', 'carbohydrates', 'fat']));
```

For that future ProductPolicy, an ownership check can use Laravel's native denial
response. Match the ownership key's cast/type to the User primary key:

```php
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

public function update(User $user, Product $product): Response
{
    return $product->user_id === $user->getKey()
        ? Response::allow()
        : Response::denyAsNotFound();
}
```

Scope queries even when a policy is present. Apply equivalent ownership rules to
other applicable policy actions; the sample method is not a complete policy.
Prefer explicit user relationships or a local owner scope when repetition warrants
it. Do not introduce implicit global scopes or a generic ownership framework now.

## HTTP errors and Vue behavior

| Condition                                                   | Response                                                                 |
| ----------------------------------------------------------- | ------------------------------------------------------------------------ |
| Guest requesting a registered protected web page            | Redirect to `/login`; a request expecting JSON gets 401.                 |
| Guest requesting a registered protected API endpoint        | JSON 401, never an HTML login redirect or SPA shell.                     |
| Missing record or another owner's private record            | 404, without disclosing whether the ID exists.                           |
| Forbidden action on a resource the user is permitted to see | 403 through native authorization.                                        |
| Invalid fields or an invalid referenced resource            | Laravel 422 validation response without exposing another user's record.  |
| Invalid request-forgery credentials on a mutation           | Native 419 may precede authentication; do not disable CSRF to force 401. |

The current app has no domain action that needs a policy-driven 403. Signed storage
denial already has its own native 403/404 contract. Future resource tasks must add
tests for their actual policy denials instead of relying on these account tests.

Vue's existing guard revalidates the session for private navigation and clears
unverified state on failure. Forging Pinia/localStorage or a `user_id` query does not
grant access. API callers must also treat a 401 as lost authentication, a 403 as a
forbidden action and a 404 as unavailable data, without displaying stale private
content or repeatedly redirecting a still-authenticated user to login for a 403.
Add handling and tests with each actual data client; no unused domain client or
interceptor is introduced here. Never use a UI guard, hidden button, UUID or private
URL as authorization.

## Verification and future regression requirements

`tests/Feature/AuthorizationTest.php` verifies public/protected boundaries, direct
requests, session-scoped identity with two users and 404 namespace isolation.
`AuthenticationTest.php` exercises independent real file sessions and actual CSRF
validation for forged identifiers on registration/login/logout. Vue tests verify
that private navigation uses the server identity rather than modified browser state.
All database writes stay in generated, restricted test databases and transactions;
no development data or schema changes are required by this task.

Every future owned-resource task must test owner success, guest denial, another
owner's list/detail/update/delete/aggregate access, missing IDs, forged ownership
fields and foreign referenced IDs. Assert denied writes leave both users' data
unchanged. Include policy discovery and real HTTP tests with direct requests;
frontend tests alone are insufficient. Use the existing [testing workflow](testing.md).

Executed results are in [VERIFICATION.md](VERIFICATION.md). Framework behavior was
checked against the installed source and Laravel 13's official
[authorization](https://laravel.com/framework/docs/13.x/authorization) and
[routing](https://laravel.com/framework/docs/routing) documentation.
