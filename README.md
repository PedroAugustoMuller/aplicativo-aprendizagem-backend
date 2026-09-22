# Química Quiz — Backend

The API for a gamified chemistry quiz for 9th-grade students at E.M.E.F. Dom
Pedro II (Venâncio Aires/RS). Teachers register content and questions; students
answer quizzes with randomized questions, get immediate feedback, and progress
on a per-topic ranking.

The frontend (Vue 3 PWA) lives in a **separate repository, on a separate
domain**. It talks to this API over HTTPS with a Sanctum bearer token — never
a cookie session. This repository contains only the backend.

## Requirements

**Docker and Docker Compose. Nothing else.** PHP, Composer, and every PHP
extension live inside the `app` container. Do not install PHP or Composer on
the host — every command below runs through `docker compose exec`.

## Getting started

```bash
cp .env.example .env
UID=$(id -u) GID=$(id -g) docker compose build app
docker compose run --rm --no-deps app php artisan key:generate
UID=$(id -u) GID=$(id -g) docker compose up -d
docker compose exec -u www-data app php artisan migrate --force
docker compose exec -u www-data app php artisan db:seed --force
curl http://localhost:8080/up
```

The last command should return HTTP 200. The key is generated **before** the
`app` container is created, through a throwaway `run` container, so the
long-lived container starts with it already in its environment. That `run`
has no `-u www-data` on purpose: on a fresh clone the entrypoint installs
Composer dependencies and `chown`s them, which needs root; `key:generate`
rewrites the existing `.env` in place, so the file keeps your ownership.

`db:seed` now populates three things, in order: the subject catalogue
(`SubjectsSeeder` — Química, Biologia, …), the chemistry topics
(`ChemistryTopicsSeeder`), and a development accounts table
(`DevelopmentAccountsSeeder`) — an admin, a teacher, and two students already
enrolled in a classroom. That last seeder is a fixed-id contract with the
frontend e2e suite; see its docblock before changing any of its logins.
`DevelopmentAccountsSeeder` only runs in the `local` and `testing`
environments and no-ops elsewhere, so this step is local-only — production
bootstraps its first admin with `identity:create-admin` instead (see the
"Operations" section of this project's `CLAUDE.md`).

**`php-fpm` refuses to start with an empty `APP_KEY`.** `docker-compose.yml`
loads `.env` into the `app` container's process environment via `env_file:`,
and Docker fixes that environment when the container is *created*, not when
the file on disk later changes. Before this check existed, a container
created with the blank `APP_KEY=` from `.env.example` ran fine: the health
check, migrate, seed, login and topics all work, because Sanctum's stateless
API routes never touch the encrypter. Only what does touch it failed, with a
`500 system.unexpected_error` (`MissingAppKeyException` in the log) — bulk
student creation, which encrypts the temporary passwords in
`pending_credentials`, and the `EncryptCookies` canary in
`tests/Feature/ExampleTest.php`. So `docker/entrypoint.sh` now exits with an
explanation instead of starting the server, and `restart: unless-stopped`
keeps it visibly crash-looping (`docker compose logs app`). One-off
commands (`docker compose run --rm app php artisan ...`) are not blocked,
since that is how the key gets created. If `.env` already has a key but the
container predates it, `UID=$(id -u) GID=$(id -g) docker compose up -d`
recreates just `app` — Compose recomputes `env_file` values on every `up`.

`UID`/`GID` are passed as Docker build args so the container's `www-data`
user is remapped to match your host user (see the Dockerfile). Skip them and
the bind mount fills with `www-data`-owned (not your host user's) files —
usually root-equivalent, since the default UID/GID is 33.

Seeded admin login: `ana@escola.br` / `password` (`login` is an email or a
username — the field is always called `login`). Try it:

```bash
curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"login":"ana@escola.br","password":"password"}'
```

Copy the `data.token` from the response and use it to list the seeded
subjects, then a subject's chemistry topics:

```bash
curl -s http://localhost:8080/api/v1/subjects \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"

curl -s "http://localhost:8080/api/v1/subjects/<subject-id>/topics" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

The seeded student `diego.souza` / `Temp2345` still has `must_change_password:
true` on every fresh seed — useful for exercising that pipeline stage by hand:

```bash
curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"login":"diego.souza","password":"Temp2345"}'
```

You do not need to send `Accept: application/json` for this to work — a bare
`curl` or a browser opening the URL directly gets the same 401 envelope as a
real client. See "Errors" below for why that needed a deliberate fix rather
than coming for free.

## Everyday commands

Every command below is a `docker compose exec` because the toolchain — PHP,
Composer, PHPUnit, PHPStan, Pint, deptrac — is installed only inside the
`app` container, never on the host.

```bash
# The full quality gate. Must pass before any change is considered done.
docker compose exec -u www-data app composer quality

# Run one suite at a time.
docker compose exec -u www-data app php vendor/bin/phpunit --testsuite=Unit
docker compose exec -u www-data app php vendor/bin/phpunit --testsuite=Integration
docker compose exec -u www-data app php vendor/bin/phpunit --testsuite=Feature

# Auto-fix formatting (Pint) instead of just checking it.
docker compose exec -u www-data app composer fix

# Regenerate docs/error-codes.json after adding or changing an error code.
docker compose exec -u www-data app php artisan error-codes:dump
```

**Every single one of these carries `-u www-data`, and that is not
cosmetic.** The `app` image declares no `USER`, so a bare `docker compose exec
app <cmd>` runs as **root**. Root writes files into the bind-mounted working
tree as root, and root-owned files in a directory your host user does not
own break the next `git add`, the next edit, the next anything, until you
`sudo chown` your way out. The `UID`/`GID` build args in "Getting started"
exist specifically so that `-u www-data` maps onto your host user instead of
some arbitrary container UID — the two only work together.

`composer quality` runs, in order: an error-code freshness check
(`error-codes:dump --check`), Pint in check mode, PHPStan level 9 with no
baseline, deptrac with no violations, and all three PHPUnit suites
(`Unit`, `Integration`, `Feature`).

**Never run `php artisan migrate --env=testing`.** It looks like it prepares
the test database; it does not. `--env=testing` tells Laravel to load
`.env.testing`, which this project does not have, so Laravel silently falls
back to `.env` and migrates the **development** database while printing a
success message — nothing tells you it did the wrong thing. The test suite
does not need this: `RefreshDatabase` migrates the `quimica_test` database
itself, from `phpunit.xml`'s connection settings, for every test class that
uses it.

## Architecture

Every module follows the same four-layer shape:

```
app/
├── Shared/                              cross-module contracts and infrastructure
│   ├── Domain/                          ErrorCode, the five DomainException bases, Uuid
│   └── Infrastructure/
│       ├── Http/                        ApiExceptionRenderer
│       ├── Persistence/                 EloquentAttribute and other repository helpers
│       └── Error/                       ErrorCodeRegistry
└── Modules/
    ├── Identity/
    │   ├── Domain/                      User, value objects, UserRepository interface
    │   ├── Application/                 AuthenticateUser command + handler, ports
    │   ├── Infrastructure/
    │   │   ├── Persistence/             UserModel, EloquentUserRepository (Eloquent → domain)
    │   │   ├── Auth/                    SanctumTokenIssuer, BcryptPasswordHasher
    │   │   └── Http/                    AuthController, LoginRequest, routes.php
    │   ├── Database/{Migrations,Seeders}/
    │   └── IdentityServiceProvider.php
    └── Content/
        ├── Domain/                      Topic, value objects, TopicRepository interface
        ├── Application/                 ListTopics query, TopicListReader port + DTO
        ├── Infrastructure/
        │   ├── Persistence/             TopicModel, EloquentTopicRepository (domain → Eloquent),
        │   │                            EloquentTopicListReader
        │   └── Http/                    TopicController, routes.php
        ├── Database/{Migrations,Seeders}/
        └── ContentServiceProvider.php
```

`Infrastructure/` splits into `Persistence/`, `Http/`, and — once a module
needs to call out to another system — `Client/`. A controller is a delivery
mechanism in exactly the sense that Eloquent is a persistence mechanism;
neither belongs in `Domain/` or `Application/`.

### Why module-first, and why the layers are enforced by machine

The top-level folder is the bounded context (`Identity`, `Content`, and later
`Quiz`, `Scoring`); the layers live inside it. This is deliberate: it keeps
each context's domain, application, and infrastructure code physically next
to each other and every other context physically apart. Nothing stops two
contexts from being logically related — but the folder layout is not where
that relationship gets to live.

Two rules, both machine-checked by **deptrac** (`deptrac.yaml`), not left to
review discipline:

- **Layer axis** — `Domain` may import only its own module's `Domain` plus
  `Shared/Domain`. `Application` adds its own `Domain` and `Shared`.
  `Infrastructure` may additionally import the framework. Nothing may import
  `Infrastructure`. Concretely: a `use Illuminate\...` statement inside any
  `Domain/` folder fails the build.
- **Module axis** — a module may reference only itself and `Shared`. Cross-
  module contracts go in `app/Shared/`, never by reaching into another
  module's internals.

This gate was proven to fire during development, not merely configured and
trusted: `docker compose exec -u www-data app php vendor/bin/deptrac analyse`
catches an errant `Illuminate` import in `Domain/` as a build failure, the
same way PHPStan or a failing test would.

The payoff for keeping `Domain/` and `Application/` framework-free: the
`Unit` test suite runs against plain `PHPUnit\Framework\TestCase` — **no
database, no service container, no Laravel bootstrap at all.** Entities and
value objects are tested as plain PHP objects.

Deptrac only sees a file at all if some collector's pattern matches its path
or class name; a folder or class no collector names is invisible to it, not
merely violation-free. `Uncovered 0` in the report means every file deptrac's
collectors currently claim was checked — it is not evidence that deptrac
examined every PHP file in the module, so widening a collector (as happened
for `Database/` and the module service providers, see the module-first
section above) can surface real, previously-invisible edges even when the
report was already "clean."

### Writes go through the aggregate; list reads bypass it

A write (`AuthenticateUser`, or a future `CreateTopic`) loads and saves a
domain entity through its repository interface — `UserRepository`,
`TopicRepository`. A list endpoint (`GET /api/v1/topics`) does not: it goes
through a `*ListReader` port (`TopicListReader`) whose Eloquent
implementation (`EloquentTopicListReader`) selects only the columns the
response needs and maps them straight into a DTO (`TopicListItem`),
skipping the aggregate entirely. Hydrating a full `Topic` entity — and every
entity in a list of twenty — just to read two fields back off it is exactly
the cost this read model exists to avoid.

The two modules deliberately demonstrate the two mapping directions, and
neither carries the unused half:

- **`Identity`** maps Eloquent → domain: `EloquentUserRepository` reads a
  `UserModel` row and reconstructs a `User` entity for `AuthenticateUser` to
  operate on.
- **`Content`** maps domain → Eloquent: `ChemistryTopicsSeeder` builds a
  `Topic` entity and hands it to `EloquentTopicRepository::save()`, which
  persists it as a `TopicModel` row.

If a later module needs both directions, write both — but do not add the
unused half to `Identity` or `Content` just to make them "symmetric." A
module with no failure mode of its own — `Content` has none today — declares
zero error codes for exactly the same reason: an empty case is correct, not
an omission waiting to be filled in.

## How to add a module

1. Create `app/Modules/<Name>/{Domain,Application,Infrastructure,Database}`
   (`Infrastructure` splits further into `Persistence/`, `Http/`, and
   `Client/` if the module calls another system).
2. Write `<Name>ServiceProvider` (see `IdentityServiceProvider` /
   `ContentServiceProvider` for the pattern): bind the module's repository
   and port interfaces in `register()`; call `loadMigrationsFrom(...)` and
   register the module's `routes.php` under `Route::prefix('api/v1')
   ->middleware('api')` in `boot()`.
3. Register the provider in `bootstrap/providers.php`.
4. If the module has a failure mode, add its `ErrorCode` enum to
   `config/error_codes.php`, then run
   `docker compose exec -u www-data app php artisan error-codes:dump` and
   tell the frontend team to run `npm run sync:error-codes`. If it has no
   failure mode, add nothing — see above.
5. Add the module's three layers to `deptrac.yaml`: a collector per layer,
   and ruleset entries mirroring the existing modules (`Domain` depends on
   nothing but `SharedDomain`; `Application` adds its own `Domain`;
   `Infrastructure` adds its own `Domain`+`Application`, `Shared`, and
   `Vendor`).

## Endpoints

All under `/api/v1`. Everything except `POST /auth/login` sits behind the
pipeline described in the backend `CLAUDE.md`'s "Authorization" section
(`auth:sanctum` → `account.active` → `password.changed` → route-level
`role:*` → the handler's own policy). "Who" below is the *effective* rule
after that policy runs, not just the route-level gate —
`tests/Feature/AuthorizationMatrixTest.php` is the executable version of it.

**Auth**

| Method | Path | Who |
|---|---|---|
| POST | `/auth/login` | anyone (`login` is an email or a username) |
| GET | `/auth/me` | any authenticated user |
| POST | `/auth/logout` | any authenticated user |
| PUT | `/auth/password` | any authenticated user |

**Subjects** (Content)

| Method | Path | Who |
|---|---|---|
| GET | `/subjects` | staff: all · student: their enrolled subjects |
| POST | `/subjects` | admin |
| PATCH | `/subjects/{id}` | admin |
| POST | `/subjects/{id}/deactivate` | admin |
| GET | `/subjects/{id}/topics` | staff: all · student: if enrolled in that subject |

**Teachers** (Identity, admin only)

| Method | Path |
|---|---|
| GET | `/teachers` |
| POST | `/teachers` |
| POST | `/teachers/{id}/reset-password` |
| POST | `/teachers/{id}/deactivate` · `/reactivate` |

**Classrooms** (Identity)

| Method | Path | Who |
|---|---|---|
| GET | `/classrooms` | admin: all · teacher: assigned · student: enrolled |
| POST | `/classrooms` | admin |
| PATCH | `/classrooms/{id}` | admin |
| PUT | `/classrooms/{id}/teachers` | admin |
| POST | `/classrooms/{id}/deactivate` | admin |

**Students** (Identity, admin or a teacher of the classroom)

| Method | Path | Notes |
|---|---|---|
| GET | `/classrooms/{id}/students` | |
| POST | `/classrooms/{id}/students` | 1–50 rows, one all-or-nothing transaction |
| PUT | `/classrooms/{id}/students/{studentId}` | enrol an existing student |
| DELETE | `/classrooms/{id}/students/{studentId}` | unenrol |
| POST | `/students/{id}/reset-password` | |
| POST | `/students/{id}/deactivate` · `/reactivate` | |
| GET | `/classrooms/{id}/credentials` | pending slips: name, username, temporary password |

## Errors

The API never returns human-readable text to a client. Every error is a
stable code plus interpolation params; the frontend's `vue-i18n` owns every
string a user sees, which is what lets the app phrase an error while
offline. Response shape:

```json
{
  "error": {
    "code": "content.topic.name_already_taken",
    "params": { "name": "Ligações Químicas" },
    "message": "for logs only, never display this",
    "trace_id": "01JQ8X4M2N7P"
  },
  "errors": {
    "name": [{ "code": "validation.required", "params": {} }]
  }
}
```

`message` exists for logs and debugging; clients must not render it.
`errors` (plural) is present only for field-level validation failures.
`trace_id` is what correlates a report from a user with a line in the
server log.

Every domain exception extends one of five abstract bases, and the base —
not the concrete exception — fixes the HTTP status, so a concrete exception
can never promise one status and return another:

| Base | Status |
|---|---|
| `UnauthenticatedException` | 401 |
| `ForbiddenException` | 403 |
| `NotFoundException` | 404 |
| `ConflictException` | 409 |
| `BusinessRuleException` | 422 |

401 and 403 are kept distinct on purpose: the frontend clears the local
session on 401 (the token is no good any more) and must never do so on 403
(the account is still valid, it just isn't allowed to do that one thing —
e.g. a teacher hitting another teacher's classroom, or anyone but an admin
hitting an admin-only route).

`App\Shared\Infrastructure\Http\ApiExceptionRenderer` is the **only** place
that formats an error response. Any `Throwable` it doesn't otherwise
recognize — a bare `\RuntimeException`, a bug — becomes
`system.unexpected_error` with a fresh `trace_id`; the exception's message
and stack trace go to the log, never to the response, regardless of
`APP_DEBUG`.

**Known gap:** `TrustProxies` is not configured anywhere in this repository,
and that is a deployment requirement, not an oversight to fix here. With no
trusted proxy, `$request->ip()` returns the raw `REMOTE_ADDR` — the socket
peer. The planned production topology is Cloudflare → nginx → Laravel, so in
production `REMOTE_ADDR` on every request is an nginx-local address or, if
nginx doesn't rewrite it, a Cloudflare edge IP shared by many unrelated
visitors. The unauthenticated rate limiter (`AppServiceProvider::boot()`,
`RateLimiter::for('api', ...)`) keys by `$request->ip()` when there is no
authenticated user, so every anonymous caller behind the same edge node
would share one bucket — one student refreshing quickly could throttle the
rest of the class. Before this goes live: nginx must set
`X-Forwarded-For`/`X-Forwarded-Proto` from the real client, and Laravel's
`bootstrap/app.php` must register a `TrustProxies` middleware (or
`Request::setTrustedProxies`) that trusts nginx and reads those headers.

**Fixed gap, worth knowing the mechanism of:** by default, Laravel's
`Authenticate` middleware — when it decides a guest's request doesn't
expect JSON — redirects to a named `login` route. This API has no web
routes and no such route, so resolving it threw `RouteNotFoundException`
*inside the middleware*, before an `AuthenticationException` ever existed
for `ApiExceptionRenderer` to catch — surfacing as `system.unexpected_error`
(500) instead of `auth.unauthenticated` (401) for any caller that omitted
`Accept: application/json` (a bare `curl`, a browser address bar, a health
probe). The full test suite stayed green throughout because `getJson()` always
sends that header, so it never hit this path. `bootstrap/app.php` now calls
`$middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*')
? null : '/login')`, which tells the middleware there is nothing to redirect
to for `api/*` requests; `AuthenticationException` then reaches
`ApiExceptionRenderer` normally. Pinned by
`tests/Feature/Modules/Content/ListTopicsTest.php::test_it_rejects_an_anonymous_request_with_no_accept_header`,
which uses `get()` rather than `getJson()` specifically to omit the header.

## Ports

| Port | Service |
|---|---|
| `8080` | API (nginx → PHP-FPM) |
| `5433` | PostgreSQL (host-mapped; the container-internal port is the standard 5432) |
| `8026` | Mailpit web UI |

All three are overridable via `.env` (`NGINX_PORT`, `DOCKER_DB_PORT`,
`DOCKER_MAILPIT_UI_PORT`) — see `docker-compose.yml`.
