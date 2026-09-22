# Backend — working agreements

## Non-negotiable

- **Never run `git commit`** on the user's own branches. Leave changes
  staged or in the working tree; the user commits. (The one exception is
  inside a `.worktrees/*` process branch, where the root `CLAUDE.md`
  explicitly says commits are the process — that exception does not apply
  once the work lands back in the user's `backend` repository.)
- **Everything runs in Docker:** `docker compose exec -u www-data app <cmd>`.
  Never run `php` or `composer` on the host — nothing is installed there.
  The `-u www-data` is required, not cosmetic: the image declares no `USER`,
  so a bare `exec` runs as root and writes root-owned files into the bind
  mount, which then breaks the next edit or `git add` until someone `chown`s
  the tree back.
- **`docker compose exec -u www-data app composer quality` must pass before
  any work is called done.** It runs, in order: the error-code freshness
  check (`error-codes:dump --check`), Pint in check mode, PHPStan level 9
  with no baseline, deptrac with no violations, and the three PHPUnit
  suites (`Unit`, `Integration`, `Feature`).
- **Never run `php artisan migrate --env=testing`.** `--env=testing` loads
  `.env.testing`, which does not exist in this project, so Laravel silently
  falls back to `.env` and migrates the **development** database while
  reporting success — there is no error to notice. The test schema comes
  from `RefreshDatabase`, driven by the connection settings in `phpunit.xml`,
  not from a manual migrate call.
- **After `php artisan key:generate` (or any edit to `.env`), run
  `docker compose up -d` again before trusting the container.** `env_file:`
  fixes the `app` container's environment at creation time; editing `.env`
  afterward does not reach an already-running container, even through
  `docker compose exec`. A second `up -d` (no `--build` needed) makes
  Compose notice the changed value and recreate just the `app` service.
  Skipping this is silent: health checks, migrations, and the API's
  Sanctum-only JSON routes all keep working with a stale/empty `APP_KEY`,
  and only something that touches the encrypter — the `web` middleware
  group's `EncryptCookies`, which `tests/Feature/ExampleTest.php` exercises
  — fails, taking `composer quality` down with `MissingAppKeyException`.

## Architecture

- Module-first: `app/Modules/<Module>/{Domain,Application,Infrastructure,Database}`.
  The top-level folder is the bounded context (`Identity`, `Content`, later
  `Quiz`/`Scoring`); the four layers live inside it, the same shape in every
  module.
- `Domain/` and `Application/` contain **no** `use Illuminate\...`. deptrac
  fails the build otherwise — this was verified to actually fire during
  development, not just configured and trusted.
- A module never imports another module's internals. Cross-module contracts
  go in `app/Shared/`.
- Writes go through the aggregate and its repository interface. **List
  reads bypass the aggregate** via a `*ListReader` port returning DTOs
  directly off a narrow Eloquent `select()` — hydrating aggregates just to
  render a list is the cost this read model exists to avoid. See
  `TopicListReader` / `EloquentTopicListReader` for the pattern.
- Every class is `final` unless it is one of the five abstract
  `DomainException` bases, the abstract `Uuid` value object, or a framework
  base class Laravel itself generated non-final (e.g. `AppServiceProvider`).
- `declare(strict_types=1);` at the top of every PHP file, no exceptions.
- A module that has no failure mode of its own contributes **no** error
  codes — that is correct, not something to "complete." `Content` gained
  its first ones (`ContentErrorCode`) once subjects introduced real
  failure modes (not found, name taken).

## Errors

- Every domain exception extends one of **five** bases: `UnauthenticatedException`
  (401), `ForbiddenException` (403), `NotFoundException` (404),
  `ConflictException` (409), `BusinessRuleException` (422). The base fixes the
  status; a concrete exception never picks its own, so it cannot claim one
  status and return another. 401 and 403 are kept deliberately distinct: the
  frontend clears the session on 401 and never on 403, so a permission denial
  must never be a 401 — the account is still authenticated, it just isn't
  allowed to do that one thing.
- Every error code is a case on a module's `ErrorCode` enum, registered in
  `config/error_codes.php`. After adding or changing one, run
  `docker compose exec -u www-data app php artisan error-codes:dump` (this
  rewrites `docs/error-codes.json`, which is committed) and tell whoever
  owns the frontend to run `npm run sync:error-codes` there — a code with no
  translation is a broken frontend build by design.
- **The API never returns user-facing text.** Only codes and params under
  `error.code`/`error.params`. The `error.message` field exists for logs;
  no client may display it.
- `App\Shared\Infrastructure\Http\ApiExceptionRenderer` is the only place
  that formats an error response. Any unrecognized `Throwable` becomes
  `system.unexpected_error` with a fresh `trace_id`; its real message and
  stack trace go to the log only, regardless of `APP_DEBUG`.
- `bootstrap/app.php`'s `withMiddleware()` closure calls
  `$middleware->redirectGuestsTo(fn ($request) => $request->is('api/*') ?
  null : '/login')`. Do not remove this. Without it, `Authenticate` tries to
  resolve a named `login` route (which this API-only app does not have) for
  any guest request that doesn't send `Accept: application/json`, throwing
  `RouteNotFoundException` *before* an `AuthenticationException` exists —
  which turns a 401 into a 500. Pinned by
  `ListTopicsTest::test_it_rejects_an_anonymous_request_with_no_accept_header`.

## Authorization

Every protected request goes through the same pipeline, in this order:

1. `auth:sanctum` — valid token, or 401 `identity.account_deactivated`/
   `auth.unauthenticated`.
2. `account.active` (`EnsureAccountActive`) — a deactivated user is rejected
   and their token revoked on the spot, even if it slipped through a race
   window before revocation.
3. `password.changed` (`EnsurePasswordChanged`) — while `must_change_password`
   is true, only `GET /auth/me`, `POST /auth/logout` and `PUT /auth/password`
   pass; everything else is 403 `identity.password_change_required`.
4. `role:admin` / `role:staff` route middleware — the coarse gate.
5. The handler's own policy check.

**`role:*` is the early reject, the handler policy is the rule — every
handler checks, even behind `role:admin`.** The route middleware only proves
the actor has the right shape of account (an admin, someone on staff); it
says nothing about whether *this* admin or *this* teacher may act on *this*
classroom or *this* student. That finer-grained question is answered by a
pure domain policy — `RosterPolicy` in `Identity`, `SubjectPolicy` in
`Content` — which the handler calls directly. A handler that trusted
`role:admin` alone and skipped its own policy would be correct today and
wrong the day someone adds a second admin-adjacent role.

`Actor` (`App\Shared\Domain\Auth\Actor`, a `UserId` plus a `Role`) is built
per request by `App\Shared\Infrastructure\Http\ActorFactory` and passed into
every command/query as a constructor argument — never resolved from or bound
into the container, which is what keeps it Octane-safe.

`tests/Feature/AuthorizationMatrixTest.php` is the executable form of the
spec's permission matrix (design doc §4): one row per route, one status per
actor shape (guest, student, unrelated teacher, assigned teacher, admin).
**Adding a route that isn't one of the three `/auth/*` routes without adding
a row there is an authorization gap, not a documentation nicety** — the
matrix is what a reviewer runs, not what they read. `/auth/*` is exempt: it
has no per-actor row because it isn't gated by role or ownership — `login`
is open to anyone, and `me`/`logout`/`password` behave the same for every
authenticated role. `tests/Feature/Modules/Identity/LoginTest.php` and
`ChangePasswordTest.php` cover those.

## Cross-module contracts

`Identity` and `Content` never import each other's internals (module axis,
enforced by deptrac — see Architecture above). Where one module's policy
needs a fact that only the other owns, the contract lives in
`app/Shared/Domain/Contract/` and the owning module binds an implementation:

- `SubjectCatalog` — "is this subject id active?" `Content` owns subjects and
  implements it (`EloquentSubjectCatalog`); bound in `ContentServiceProvider`.
  `Identity` depends on it to validate a classroom's `subject_id`.
- `TeachingAssignments` — "which subject ids does this user teach/is enrolled
  in through an active classroom?" `Identity` owns classrooms and
  implements it (`EloquentTeachingAssignments`); bound in
  `IdentityServiceProvider`. `Content`'s `SubjectPolicy` depends on it to
  decide who may view or author a subject's topics.

Both interfaces live in `Shared`, not in the module that happens to consume
them, because a future module (`Quiz`, `Scoring`) will need the same facts
without either existing module importing it.

## Writes and retries

- Any state-changing endpoint a client may retry must accept a
  client-generated identifier and return the **original** result on replay
  instead of duplicating the write. This is not optional per-endpoint
  polish — it exists because a student's phone will resend a quiz-attempt
  submission the moment the network comes back (the offline requirements
  RNF03/RNF05), and a duplicate submission is a wrong score, not a cosmetic
  bug.
- As implemented for every create endpoint under this task (subjects,
  teachers, classrooms, bulk students): the client sends the id it wants
  (`POST` body's `id`, a UUID). The same id with an equivalent payload
  replays the original response (200, not 201, on the replay). The same id
  with a *different* payload is a 409 `system.idempotency_conflict` — the
  client asked to create two different things under one identifier, and
  that is a bug in the client, not a case to silently resolve. Bulk student
  creation applies the same rule per row inside one all-or-nothing
  transaction. Deactivate/reactivate/enrol/unenrol/assign-teachers/`PATCH`
  need no id trick: they are naturally idempotent by what they do.
- Reset-password is idempotent through `pending_credentials`, not through a
  client id: if a pending credential already exists, a reset returns it
  instead of minting a new one (and does **not** revoke existing tokens
  again); an explicit reset after the user has chosen their own password
  always mints a fresh one.
  `App\Modules\Identity\Application\Service\PasswordIssuer` is the **only**
  producer of temporary passwords — every handler that issues or resets a
  password goes through it, so "8 chars, excludes `0 O o 1 l I`,
  `random_int`" is defined exactly once.

## Language

- All code, identifiers, comments, error codes, and documentation in
  **English**.
- Portuguese appears only as seeded content data — the chemistry topic
  names and descriptions a teacher actually sees, e.g.
  `ChemistryTopicsSeeder`. The seeded dataset now spans several subjects, not
  just chemistry: `SubjectsSeeder` creates the subject rows (Química,
  Biologia, …) and `DevelopmentAccountsSeeder` creates the accounts, a
  classroom and enrolments the frontend e2e suite logs into — see that
  class's docblock for the exact ids/logins it is a contract with.

## Operations

- Production bootstrap has no seeded admin: run
  `docker compose exec -u www-data app php artisan identity:create-admin
  {name} {email}` once, against the real database. It refuses to run if any
  admin already exists, so it cannot mint a second one by accident — every
  other account is created by an existing admin through the API from then on.

## Octane-safe by default

Octane is deliberately not enabled (nginx + PHP-FPM instead — see the root
`CLAUDE.md` for why), but the code is written so enabling it later is a
`composer require` and a compose change, not a rewrite:

- No mutable state in singletons.
- No static property holding request-scoped data.
- Dependencies injected through the constructor or method signature, never
  resolved from the container mid-method.

## Known deployment gap — read before going live

`TrustProxies` is not configured anywhere in this repository. Without it,
`$request->ip()` returns the raw socket peer, not the real client. Under the
planned Cloudflare → nginx → Laravel topology this collapses every
unauthenticated caller behind the same edge node onto one rate-limit bucket.
Before deploying: configure nginx to forward the real client IP and register
a `TrustProxies` middleware (or `Request::setTrustedProxies`) in
`bootstrap/app.php` that trusts it. See `README.md`'s "Errors" section for
the full mechanism.
