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
- Every class is `final` unless it is one of the four abstract
  `DomainException` bases, the abstract `Uuid` value object, or a framework
  base class Laravel itself generated non-final (e.g. `AppServiceProvider`).
- `declare(strict_types=1);` at the top of every PHP file, no exceptions.
- A module that has no failure mode of its own contributes **no** error
  codes. `Content` has none today — that is correct, not something to
  "complete."

## Errors

- Every domain exception extends one of `UnauthenticatedException` (401),
  `NotFoundException` (404), `ConflictException` (409),
  `BusinessRuleException` (422). The base fixes the status; a concrete
  exception never picks its own, so it cannot claim one status and return
  another.
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

## Writes and retries

- Any state-changing endpoint a client may retry must accept a
  client-generated identifier and return the **original** result on replay
  instead of duplicating the write. This is not optional per-endpoint
  polish — it exists because a student's phone will resend a quiz-attempt
  submission the moment the network comes back (the offline requirements
  RNF03/RNF05), and a duplicate submission is a wrong score, not a cosmetic
  bug.

## Language

- All code, identifiers, comments, error codes, and documentation in
  **English**.
- Portuguese appears only as seeded content data — the chemistry topic
  names and descriptions a teacher actually sees, e.g.
  `ChemistryTopicsSeeder`.

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
