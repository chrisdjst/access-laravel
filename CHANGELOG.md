# Changelog

All notable changes to `modularize-rbac/laravel` are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

## [2.8.1] - 2026-05-25

Patch release: docs-only. The TypeScript/React companion packages were renamed from `@casamento/*` to the public `@modularize-rbac/*` npm org, alongside a brand new spec-derived SDK and Storybook-driven UI library.

### Added

- **README "Frontend & SDK" section** documenting the two new npm packages and the Postman collection.
  - `@modularize-rbac/sdk-ts` — typed REST SDK generated from `openapi.json` via `openapi-typescript` + `openapi-fetch`. Zero runtime when imported type-only.
  - `@modularize-rbac/admin-react` — drop-in React admin components built on Radix Themes + React Query: `<RolesPage />`, `<ModulesTreeEditor />`, `<LanguagesAdmin />`, `<AuditViewer />`, `<AccessGuard />`. Every component ships with a Storybook story driven by `msw` mock handlers.
  - `postman.json` — committed at repo root, regenerated from the same spec, kept in sync by the `sdk-ts-drift` CI gate.
- **Spec polish** to support the React surface:
  - `Language` schema and POST/PUT bodies gain `is_active` (the model already supported it).
  - `AuditEntry` schema added; `GET /audit` response body filled in with `{data: AuditEntry[], meta: PaginatedMeta}`.
  - `POST /roles`, `POST /roles/{role}/clone`, `DELETE /roles/{role}`, `POST /roles/{role}/restore`, `POST /modules`, `DELETE /modules/{id}`, `DELETE /modules/bulk` already implemented in v2.8.0 are now reflected in the typed paths.
- **`RELEASING.md`** documents the Composer + tag-driven npm publish flows.

### Changed

- **`@casamento/admin-rbac` is unmaintained**. Hosts that depend on it should follow the search-replace migration in [UPGRADING.md](./UPGRADING.md#v280--v281--frontend-package-rename). The legacy package will not receive further updates.

### Infrastructure

- New workflow `.github/workflows/npm-publish.yml` publishes both packages on tag push (`sdk-ts-v*` / `admin-react-v*`).

## [2.8.0] - 2026-05-25

Minor release: schema + extensibility. Soft-delete on roles and languages with a `restore` endpoint, an append-only binding history table with a read endpoint, and (via `modularize-rbac/core` v1.9) an injectable `PermissionActionRegistry` for custom actions beyond the 5 CRUD. Two new migrations to run. See [UPGRADING.md](./UPGRADING.md#v27--v28) for details.

### Added

- **Append-only binding history**:
  - New idempotent migration `2026_06_06_000000_create_role_module_permission_history.php` adds a `role_module_permission_history` table that captures every `create`, `update`, and `delete` on a role-module binding with the actor id, timestamp, and before/after `module_permission_id` values.
  - `EloquentRoleModulePermissionRepository::save()` / `delete()` write a history row inline; same-state idempotent saves are deduplicated to avoid noise.
  - New `GET /api/admin/roles/{role}/bindings/history` endpoint (`admin.roles.view`) returns the history with `{data, meta: {total, limit, offset}}` envelope + `?limit= &offset= &since= &module_id=` query params.
  - The history writer wraps in `try/catch` — hosts on older bridges that haven't run the v2.8 migration see no behavior change.
- **Soft-delete on roles and languages**:
  - New idempotent migration `2026_06_05_000000_add_soft_deletes_to_roles_and_languages.php` adds `deleted_at` to both tables.
  - `Role` and `Language` Eloquent models now use the `SoftDeletes` trait. List/find queries automatically exclude trashed rows.
  - `DELETE /api/admin/roles/{role}` switched from hard delete to soft delete. The row stays with `deleted_at` set; subsequent `GET` returns 404; listings exclude it.
  - New `POST /api/admin/roles/{role}/restore` endpoint reverses a soft delete (returns 200 with the restored role + 422 when the row isn't trashed + 404 when missing).
  - `EloquentRoleRepository` implements the new `modularize-rbac/core` ^1.9 port methods (`softDelete`, `restore`, `findIncludingTrashed`). The original `delete()` stays as a true hard delete via `forceDelete()` for hosts that need it.
  - `RoleMapper` round-trips `deleted_at` between the domain entity and the Eloquent row.
  - `ImportCommand` `--strategy=replace` updated to `forceDelete()` roles + languages so the wipe-and-insert flow doesn't trip on the new soft-delete semantic.

### Changed

- `composer.json` requires `modularize-rbac/core: ^1.9` (was `^1.8`). Additive bump — pulls in `Role`/`Language` soft-delete primitives + `RestoreRole` use-case + the new port methods + `PermissionActionRegistry`.

## [2.7.0] - 2026-05-25

Minor release: observability + security. Telemetry events for authz checks and cache lookups, configurable audit log levels, PII redaction in audit payloads, an opt-in tamper-evident hash chain on the audit log, and a `verify` command for integrity audits. Fully backwards compatible with v2.6.x — every new behavior is opt-in or preserves the v2.6 default. See [UPGRADING.md](./UPGRADING.md#v26--v27) for details.

### Added

- **Tamper-evident audit log via hash chain** (opt-in):
  - New migration `2026_06_04_000000_add_hash_chain_to_access_audit_log.php` adds nullable `entry_hash` (sha256 hex) + `previous_hash` columns. Idempotent.
  - When `access.audit.hash_chain.enabled = true` (default false), every persisted entry gets `entry_hash = sha256(previous_hash || canonical(this row))`. Partitioned by `(tenant_id, event_name)` so concurrent unrelated events don't fight for the same head.
  - New `php artisan access:audit:verify [--since= --event=]` command walks each partition by following `previous_hash → entry_hash` links and exits 1 on any break (tampered payload, missing head, orphan rows). Wire it into CI/cron for periodic integrity checks.
  - Default is OFF — hosts on v2.7 see no behavior change until they explicitly enable the chain.
  - 6 new tests cover off-by-default, first-row null prev, chained subsequent rows, verify happy path, verify-on-tamper, mixed-state silent skipping.
- **PII redaction in audit payloads**:
  - `AuditingListener::extractPayload()` now walks the payload tree and replaces values whose key matches any pattern in `access.audit.redact_fields` with the literal string `[REDACTED]`.
  - Matching is case-insensitive substring — `'email'` covers `email`, `EMAIL`, `user_email`, `customer_email`, etc. Recurses into nested arrays.
  - Default list covers common PII + secret field names: `password`, `api_token`, `remember_token`, `access_token`, `refresh_token`, `secret`, `email`, `cpf`, `ssn`. Hosts extend / replace via config; set to `[]` to disable.
  - 7 new tests cover top-level redaction, case insensitivity, substring matching, nested arrays, host overrides, the disable case, and regression check on real `ModuleCreated` events.
- **Telemetry events**:
  - `Access\Events\Telemetry\AbilityResolved` (ability, allowed, source: `direct|ancestor|inheritance|none|malformed`, durationMicros) fires at the end of every `canAccess()` call. Hosts listen to wire Sentry spans, Prometheus counters, or structured logs.
  - `Access\Events\Telemetry\CacheLookup` (namespace, key, hit, version) fires on every read through `CachedLanguageRepository` / `CachedModuleRepository`. Useful for tracking cache hit ratios + spotting invalidation thrash.
  - Listener exceptions never break the underlying call — every dispatch is wrapped in `try { } catch (Throwable) {}`.
- **`access.audit.log_failures` config** controls the log level at which the audit listener reports persistence failures. Defaults to `'warning'` (matches pre-v2.7 behavior). Accepts any Monolog level (`'error'`, `'critical'`, etc.) or `false` to swallow the failure silently. The main domain flow is unaffected either way — auditing stays best-effort.

## [2.6.0] - 2026-05-25

Minor release: DX polish. Eloquent factories with useful states for every package model, a publishable seeder demonstrating the canonical use-case flow, and `php artisan access:install` to scaffold a fresh host in one call. Fully backwards compatible with v2.5.x. See [UPGRADING.md](./UPGRADING.md#v25--v26) for details.

### Added

- **`php artisan access:install` scaffold command**:
  - Runs `vendor:publish --tag=access-config` + `migrate` in one call.
  - `--with-lang` adds `vendor:publish --tag=access-lang`; `--with-seeder` adds the seeder publish.
  - `--no-config` / `--no-migrate` skip individual steps so the command composes with custom workflows.
  - Prints the `HasAccessPermissions` trait snippet at the end. Does NOT auto-edit the User model (left to the host) — the snippet copies cleanly.
  - Matches Laravel's `sanctum:install` ergonomics.
- **Publishable `AccessSeeder` example**:
  - Stub at `database/seeders/AccessSeeder.stub`. Run `php artisan vendor:publish --tag=access-seeder` to copy it as `database/seeders/AccessSeeder.php` in the host.
  - Demonstrates the canonical flow: `CreateModule` → `CreateRole` → `SyncRoleModules` use-cases. Creates a default English language, three modules (events/billing/reports), an admin role with full matrix, and a viewer role with read-only access.
  - README quickstart now references the seeder as the recommended first step after `migrate`.
- **Eloquent factories for every package model** under `database/factories/`:
  - `ModuleFactory` — states: `inactive()`, `withParent($module)`, `trashed()`.
  - `RoleFactory` — states: `system()`, `withParent($role)`, `forGuard($name)`, `forTenant($uuid)`.
  - `PermissionFactory` — states: `forModule($slug)`, `action($name)`.
  - `LanguageFactory` — states: `isDefault()`, `inactive()`.
  - `ModulePermissionFactory` — states: `allowAll()`, `readOnly()`.
- All five package models gain `HasFactory` + a `newFactory()` static method that points at the factory class directly. Hosts don't need to register the factory namespace manually.
- `composer.json` autoload now exposes `ModularizeRbac\Laravel\Database\Factories\` so factories load in both dev and prod (host seeders can call `Module::factory()->create()` outside test contexts).

## [2.5.0] - 2026-05-25

Minor release: API polish. Pagination + filters on the list endpoints, a per-user throttle on the bulk endpoints, the new `Access-Api-Version` header on every response, and a generated `openapi.json` with a CI drift gate. Fully backwards compatible with v2.4.x — every existing client sees the same payload shape (with new optional fields added). See [UPGRADING.md](./UPGRADING.md#v24--v25) for opt-in details.

### Added

- **Pagination + filters on `GET /modules` and `GET /roles`** (opt-in via query params, default behavior preserved):
  - `GET /api/admin/modules?limit=&offset=&is_active=&root_module_id=&slug_like=` — windowed list with `{data, meta: {total, limit, offset}}` envelope.
  - `GET /api/admin/roles?limit=&offset=&guard=&organization_id=&is_system=&level_min=&level_max=&has_parent=` — same envelope.
  - When NO pagination/filter param is present, both endpoints keep the v2.4.x contract (`{data: [...]}` with the full list, no `meta`). Hosts that already paginate client-side see no change.
  - `limit` defaults to 50, max 1000. Out-of-range values return 422 via the use-case input validation.
  - `level_min` > `level_max` is rejected with 422 (inverted band).
- `RoleController::index()` and `ModuleController::index()` constructors widened to inject `ListRolesPaginated` / `ListModulesPaginated` from `modularize-rbac/core` ^1.8.
- `EloquentModuleRepository::searchPaginated()` + `EloquentRoleRepository::searchPaginated()` adapters implementing the new ports.
- `CachedModuleRepository::searchPaginated()` delegates to the inner repo (paginated/filtered results aren't cached — the combinatorial filter space makes invalidation impractical, but single-row + tree reads still benefit from the cache).

- **OpenAPI 3.1 spec generation**:
  - New `OpenApiDefinition` class in `src/Http/OpenApi/` holds every endpoint's `#[OA\*]` attributes in a single place — controllers stay clean.
  - `php artisan access:openapi [--output= | --update | --check]` produces / verifies the spec. `--check` exits 1 when the on-disk spec drifts from the source attributes (CI gate).
  - `openapi.json` shipped in the repo root. Hosts can copy it into their own API docs site or run their SDK generator against it.
  - New CI workflow `.github/workflows/openapi-drift.yml` runs on PRs that touch controllers, OpenApi attributes, or the spec itself; fails if `openapi.json` is out of date with the source.
  - `composer openapi:generate` script alias for one-shot regeneration.
- **`Access-Api-Version` response header** stamped on every package response by the new `AddApiVersionHeader` middleware. SDK consumers can detect contract drift across host upgrades. Constant lives in `AddApiVersionHeader::API_VERSION` (currently `"1"`); will bump only on breaking response shape changes.
- **`meta.count` on non-paginated list responses**. `GET /modules` and `GET /roles` without query params now return `{data: [...], meta: {count: N}}` instead of `{data: [...]}`. The paginated path keeps its `meta.{total, limit, offset}` envelope. Additive — clients reading `data` are unaffected.
- **Rate limiting on bulk endpoints** via a new `access-bulk` named limiter:
  - Applied to `POST/DELETE /api/admin/modules/bulk`, `POST /api/admin/roles/{role}/clone`, `POST /api/admin/roles/{role}/users/bulk`.
  - Configurable via `access.rate_limit.bulk` (default `"10,1"` = 10 attempts/minute per user). Setting it to `null` disables the cap entirely.
  - Keyed per authenticated user (falls back to IP for unauthenticated calls). The 11th request in the window returns 429.
  - Registered by `AccessServiceProvider::registerBulkLimiter()`. Standard CRUD routes are unaffected.

### Changed

- `composer.json` requires `modularize-rbac/core: ^1.8` (was `^1.7`). Additive bump — picks up the `Pagination` / `PaginatedResult` / `ModuleFilter` / `RoleFilter` value objects + `searchPaginated()` port methods.

## [2.4.0] - 2026-05-25

Minor release: PHPBench suite + measurable perf wins on the hot read paths. Fully backwards compatible with v2.3.x — no schema changes that affect existing data, no API changes. The new migration is idempotent and additive. See [UPGRADING.md](./UPGRADING.md#v23--v24) for details.

### Added

- **PHPBench scaffold + `BENCHMARK.md`** baseline document covering six subjects: `CanAccessBench`, `CanAccessWithHierarchyBench`, `CanAccessWithInheritanceBench`, `ModuleTreeBench`, `RoleEnrichBench`, `BulkCreateModulesBench`. Run via `composer bench`.
- **`ModuleHierarchyIndex`** — new per-request scoped service that memoizes the module slug-by-id + parent-by-slug maps used by inheritance resolution. Bound via `app->scoped()` in the ServiceProvider so a single instance covers every `canAccess()` call within a request.

### Changed

- **`HasAccessPermissions::expandRoleIdsWithAncestors()`** now does a batched `whereIn` per hierarchy level instead of one query per role. Bench: `CanAccessWithHierarchyBench@depth_10` improves -24% (1,405μs → 1,062μs).
- **`HasAccessPermissions::resolveWithInheritance()`** delegates parent lookup to the scoped `ModuleHierarchyIndex` and pulls module data via `ModuleRepository::allActiveTree()` (cache-fronted by v2.3.0). Bench: `CanAccessWithInheritanceBench@modules_500` improves -88% (5,716μs → 672μs).
- **`EloquentRoleRepository::resolveAncestors()`** uses the same batched walk pattern. Cycle and orphan-pointer guards preserved.

### Database

- New idempotent migration `2026_06_03_000000_add_role_id_index_to_role_module_permission.php` — adds a standalone index on `role_module_permission.role_id`. Wrapped in `try/catch` so re-running on hosts that already added the index is a no-op. Run `php artisan migrate` to apply.

## [2.3.0] - 2026-05-25

Minor release: read cache for language + module repositories. Fully backwards compatible with v2.2.x — the layer is opt-in via config but defaults to `enabled = true`. See [UPGRADING.md](./UPGRADING.md#v22--v23) for details.

### Added

- **Read cache for language + module repositories** (opt-in, defaults to on):
  - New `CachedLanguageRepository` and `CachedModuleRepository` decorators backed by Laravel's `Cache` contract. `find`, `findBySlug` / `findByCode`, `default`, `all`, and `allActiveTree` consult the cache before the DB.
  - Version-key invalidation: each namespace (`access:lang`, `access:module`) keeps an integer in cache; mutations bump it so subsequent reads transparently miss without explicit per-key flushes. Works on every Laravel cache store (file, redis, memcached, array).
  - Defence-in-depth: a `CacheInvalidationListener` subscribes to the relevant domain events (`LanguageDefaultChanged`, `ModuleCreated/Updated/Deleted`) and bumps the version when writes happen outside the repository (Tinker, raw queries, console commands that still dispatch the event).
  - New config block: `access.cache` (`enabled`, `store`, `ttl`). Set `enabled` to `false` to bypass the layer entirely — bindings fall back to the plain Eloquent adapters.
  - `null` returns are cached too (negative-cache friendly via a wrapper sentinel that survives `Cache::has()` semantics across stores).

## [2.2.0] - 2026-05-24

Minor release: ships role cloning, bulk module operations, bulk user-to-role assignment, opt-in permission inheritance via module hierarchy, role hierarchy via `parent_role_id`, and import/export console commands. Fully backwards compatible with v2.1.x — see [UPGRADING.md](./UPGRADING.md#v21--v22) for opt-in details.

### Added

- `POST /api/admin/roles/{source}/clone` — produce a new role with the same module-permission matrix as `{source}`. Payload: `{ name, display_name? }`. Inherits guard / tenant / level from the source; `is_system` is always `false` on the clone; missing `display_name` falls back to the source's. Authorization: `admin.roles.create`. Returns 201 with the cloned role plus its enriched modules block.
- `CloneRoleRequest` form request backing the new endpoint.
- `RoleController::clone()` method (signature widened to inject `CloneRole`).
- **Bulk module endpoints**:
  - `POST /api/admin/modules/bulk` — create many modules atomically. Payload: `{ modules: [ { slug, name, ... }, ... ] }`. Returns 201 with a `data` collection. Rolls back on any per-entry failure (existing slug, duplicate slug within payload, missing parent module, etc.).
  - `DELETE /api/admin/modules/bulk` — soft-delete many modules atomically. Payload: `{ ids: [uuid, uuid, ...] }`. Returns 204. Returns 404 (and rolls back) if any id is missing.
  - `BulkCreateModulesRequest` / `BulkDeleteModulesRequest` form requests back the endpoints.
  - `ModuleController` constructor widened to inject `BulkCreateModules` / `BulkDeleteModules`.
- **Bulk user assignment**:
  - `POST /api/admin/roles/{role}/users/bulk` — bind a set of users to a role atomically. Payload: `{ user_ids: [uuid, ...], organization_id? }`. Authorization: `admin.roles.update`. Idempotent — re-running with the same payload is a no-op (existing rows in `role_user` are left untouched).
  - `AssignUsersToRoleRequest` form request backing the endpoint.
  - `EloquentUserRoleAssigner` adapter implements the new core `UserRoleAssigner` port via direct writes to the `role_user` pivot. Bound in `AccessServiceProvider::registerRepositories()`.
  - `RoleController` constructor widened to inject `AssignUsersToRole`.

- **Permission inheritance via module hierarchy** (opt-in):
  - New config key `access.inheritance.enabled` (default `false`). When `true`, `$user->can('events.weddings.view')` walks the module tree upward — a parent's binding grants the same action on every descendant.
  - `HasAccessPermissions::canAccess()` delegates to `PermissionInheritanceResolver` (from `modularize-rbac/core` ^1.6) when the flag is on. The default `false` preserves v2.0/v2.1 semantics where a binding must live on the requested module itself.
- **Import / export console commands** for env-to-env replication:
  - `php artisan access:export [--output=path.json]` — dump modules, module permissions, role-module bindings, roles, languages, and translations as JSON. Carries a `schema_version` for forward-compat. Writes to stdout if `--output` is omitted.
  - `php artisan access:import path.json [--strategy=merge|replace] [--force]` — `merge` upserts every row by id (translations upsert by their natural key); `replace` wipes every package-owned table before insert. `replace` prompts for confirmation unless `--force` is passed.
  - Rejects unknown `schema_version` values with a descriptive error.
  - Both commands registered by `AccessServiceProvider::registerConsoleCommands()`.
- **Role hierarchy via `parent_role_id`** (always on, additive):
  - New migration `2026_06_02_000000_add_parent_role_id_to_roles.php` adds a nullable self-FK on `roles.parent_role_id` with `onDelete('set null')`. Idempotent via `Schema::hasColumn()`.
  - `RoleMapper` round-trips the field; `Role` Eloquent model adds it to `$fillable`.
  - `StoreRoleRequest` accepts an optional `parent_role_id` (UUID, validated by the use-case).
  - `RoleResource` exposes `parent_role_id`.
  - `HasAccessPermissions::canAccess()` now walks the user's roles' `parent_role_id` chain. A role inherits the permission matrix of every ancestor — bindings on the parent are honored on the child without explicit duplication. Cycle-safe (visited set short-circuits malformed chains created by raw SQL).
  - `EloquentRoleRepository::resolveAncestors()` adapter implementing the new `modularize-rbac/core` ^1.7 port.

### Changed

- `composer.json` requires `modularize-rbac/core: ^1.7` (was `^1.3`). Additive bump — picks up `CloneRole`, `BulkCreateModules`, `BulkDeleteModules`, `AssignUsersToRole`, the `UserRoleAssigner` port, `PermissionInheritanceResolver`, and the role hierarchy primitives (`parentRoleId`, `resolveAncestors`).

## [2.1.0] - 2026-05-24

Minor release: closes the HTTP gap for use-cases that previously had no route, ships exception i18n, adds an audit retention command, and improves docs + CI. Fully backwards compatible with v2.0.x — see [UPGRADING.md](./UPGRADING.md#v20--v21) for opt-in details.

### Added

- New REST endpoints exposing use-cases that already existed in the core but had no HTTP surface:
  - `POST /api/admin/roles` — create a role (`admin.roles.create`). Validates name format, guard, optional tenant, and uniqueness.
  - `DELETE /api/admin/roles/{role}` — delete a role (`admin.roles.delete`). Rejects system roles and roles with active bindings.
  - `GET /api/admin/roles/{role}/permission-matrix` — full per-module flag matrix in one call (`admin.roles.view`). Replaces the inline `enrich()` N+1 in callers.
  - `GET /api/admin/users/{user}/accessible-modules` — distinct modules the user can access via any of their roles (`admin.modules.view`).
- `StoreRoleRequest`, `RolePermissionMatrixResource`, `AccessibleModuleResource`, `UserController` to back the new routes.
- `EloquentRoleRepository::delete()` and `findByName()` adapter methods (mirroring the new core port additions in `modularize-rbac/core` v1.2.0).

### Changed

- `RoleController` constructor signature widened to inject the new use-cases (`CreateRole`, `DeleteRole`, `GetRolePermissionMatrix`). Hosts that replaced the controller with a subclass need to thread the new dependencies through.
- `composer.json` requires `modularize-rbac/core: ^1.2` (additive bump).
- `SyncRoleModulesRequest`: switched `modules` from `required, array` to `present, array` (empty arrays are accepted — needed for the "drop all bindings" flow before deleting a role). Each entry must now be an `array` (rejects scalars like `42` or `"x"`).
- `StoreLanguageRequest` / `UpdateLanguageRequest`: when `config('access.allowed_locales')` is a non-empty list, language `code` submissions must match an entry. Empty / unset config preserves the previous behavior (any code accepted).

### Added (continued)

- New optional config key `access.allowed_locales` — whitelist for language `code` submissions. Defaults to `[]` (accept all).
- Translation files at `lang/en/exceptions.php` and `lang/pt_BR/exceptions.php`. Loaded under the `access` namespace via `loadTranslationsFrom`. Publishable via `php artisan vendor:publish --tag=access-lang` for hosts that want to add more locales.
- Exception JSON responses now include a localized `error_type` field (`"Invalid input"` / `"Entrada inválida"`, etc.) alongside the existing `message` field. The detailed `message` stays in whatever language the use-case emitted (English in v2.1) — only the headline is localized. Backwards compatible: clients reading `message` see no change.
- `php artisan access:audit:purge --older-than=<cutoff> [--dry-run]` console command for audit log retention. `<cutoff>` accepts a relative interval (`Nd` / `Nm` / `Ny`) or an absolute ISO-8601 date. `--dry-run` reports how many rows would be removed without actually deleting. Schedule it via Laravel's scheduler for ongoing retention.
- `composer.json` requires `modularize-rbac/core: ^1.3` (was `^1.2`). Additive bump — picks up the new `AuditRepository::deleteOlderThan()` port method, which `EloquentAuditRepository` now implements.
- `composer.json` declares `support.docs`, `support.chat`, and `funding` (GitHub Sponsors) for richer Packagist metadata.
- `phpstan.baseline.neon` (empty) wired into `phpstan.neon.dist` includes — gives future-us a place to stash known-issue ignores without blocking unrelated PRs.
- CI workflow caches the composer download dir per matrix cell (`actions/cache@v4`). Cuts cold-CI install time on PRs that don't touch dependencies.
- New `.github/workflows/release.yml` — auto-creates a GitHub Release with `--generate-notes` on every `v*` tag push.
- `UPGRADING.md` consolidates upgrade guidance for v2.0 → v2.1, v1.x → v2.0, and `casamento/rbac` → v1.0.
- README "Quickstart" section — fresh-host → first authorized request in ~5 minutes.

## [2.0.1] - 2026-05-23

Hotfix release: clean up dead config keys, emit logs for silenced infrastructure failures, and close test-coverage gaps in the v2.0 adapters.

### Removed
- `access.admin_team_id` and `access.translations_enabled` from `config/access.php` — both were declared in v1 but never consulted in the package code. If your host published the config and read these keys directly (rare), drop the references.

### Changed
- `AuditingListener` now logs at `warning` level when an audit entry fails to persist, instead of failing silently. The main domain flow still completes — auditing is best-effort.
- `LaravelTenantContext` logs at `warning` level when the container's tenant binding holds a non-UUID value. Previously the malformed value was swallowed without trace.
- `HasAccessPermissions::canAccess()` retains its silent `return false` for malformed ability strings (covered by an inline comment): the method is called for every `$user->can(...)` across the host app, including non-package abilities, so logging would flood the request log.

### Added
- `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `.github/PULL_REQUEST_TEMPLATE.md`.
- Integration tests for `EloquentUserRoleResolver`, `LaravelTenantContext`, `LaravelLocaleResolver`, and `TranslationApplier` (18 new test cases, suite total 60).

## [2.0.0] - 2026-05-23

Second major. Spatie is now fully optional, `$user->can('events.view')` works without it via the new `HasAccessPermissions` trait, the audit log is automated, and the package ships a turn-key admin policy plus operational console commands.

### Highlights

- **Spatie hard requirement dropped.** `spatie/laravel-permission` is now in `suggest`. Hosts can install + run the package with **zero** Spatie footprint.
- **`HasAccessPermissions` trait** — drop on the host User model and `$user->can('events.view')` works through the package's own schema + a `Gate::before` callback registered by the ServiceProvider.
- **Audit log auto-populated.** Every domain event flows through `AuditingListener` → `access_audit_log` with actor + tenant context. Query via `GET /api/admin/audit` or `php artisan access:audit`.
- **`AccessAdminPolicy`** — single Gate::before for the package's `admin.*` abilities. No more host-side `Gate::define()` boilerplate.
- **Console commands** — `access:diagnose`, `access:sync-spatie`, `access:audit`.
- **`TenantContext` port + `LaravelTenantContext` adapter** — use-cases can default to the current tenant from a container binding.
- **Read models** — `GetRolePermissionMatrix` + `ListUserAccessibleModules` replace the inline `enrich()` N+1 queries the v1 controllers did.

### Breaking changes vs. v1.x

#### Spatie
- `ModularizeRbac\Laravel\Models\Role` no longer extends `Spatie\Permission\Models\Role`.
- `ModularizeRbac\Laravel\Models\Permission` no longer extends `Spatie\Permission\Models\Permission`.
- `$role->givePermissionTo()` / `$role->revokePermissionTo()` / `$role->permissions` (Spatie's relation) — **gone**. Use `$role->users()`, `$role->rolePermissions()`, or `\Spatie\Permission\Models\Role::find($id)` if Spatie is installed.
- `spatie/laravel-permission` moved from `require` to `suggest` in `composer.json`.

#### Migrations
- The legacy migration `2026_03_11_003000_create_permission_tables.php` (depended on `config('permission.*')`) is **deleted**. Replaced by `2026_06_01_000000_create_access_permission_tables.php` (idempotent, no Spatie config dependency).
- New migrations:
  - `2026_06_01_000010_create_role_user.php` — pivot the `HasAccessPermissions` trait reads.
  - `2026_06_01_000020_create_access_audit_log.php` — audit log.

#### Middleware default
- `config('access.middleware')` default changed from `['api', 'auth:sanctum', 'admin.auth']` to `['api', 'auth:sanctum']`. The `admin.auth` alias is **opt-in** now — hosts that relied on it need to add it back to the config (or to specific routes).

#### Config
- New keys: `access.audit.enabled`, `access.policies.admin`.
- `spatie.enabled` now defaults to `null` (auto-detect).

### Added

- `Modularize\Core\Application\Ports\TenantContext` (consumed via the new `LaravelTenantContext` adapter).
- `Modularize\Core\Application\Ports\UserRoleResolver` (consumed via `EloquentUserRoleResolver`).
- `Modularize\Core\Application\Ports\AuditRepository` (consumed via `EloquentAuditRepository`).
- `Modularize\Core\Domain\Audit\AuditEntry` + `AuditEventName` value object.
- `Modularize\Core\Application\Audit\ListAuditEntries` use-case.
- `Modularize\Core\Application\Role\GetRolePermissionMatrix` use-case.
- `Modularize\Core\Application\Module\ListUserAccessibleModules` use-case.
- `ModularizeRbac\Laravel\Concerns\HasAccessPermissions` trait.
- `ModularizeRbac\Laravel\Authorization\AccessAdminPolicy`.
- `ModularizeRbac\Laravel\Audit\AuditingListener`.
- `ModularizeRbac\Laravel\Console\{DiagnoseCommand, SyncSpatieCommand, AuditCommand}`.
- `ModularizeRbac\Laravel\Tenant\LaravelTenantContext`.
- New route: `GET /api/admin/audit`.

### Upgrade from v1.x — step-by-step

These steps assume a host that's running `modularize-rbac/laravel ^1.1`.

1. **Bump the dependency.**
   ```bash
   composer require modularize-rbac/laravel:^2.0
   ```

2. **Publish the new config** (or merge manually). The middleware default + new `audit` / `policies` blocks need to land:
   ```bash
   php artisan vendor:publish --tag=access-config --force
   ```
   If the host customized `config/access.php`, diff and merge manually.

3. **Migrate.** The new migrations are idempotent via `Schema::hasTable` guards — existing tables stay intact.
   ```bash
   php artisan migrate
   ```

4. **(If host customized Role / Permission models)** Stop extending `Spatie\Permission\Models\Role` / `Spatie\Permission\Models\Permission`. Use plain `Eloquent\Model` and copy any methods you need from the v2 package models. **`givePermissionTo()` / `revokePermissionTo()` on the host's Role break** — call them on `Spatie\Permission\Models\Role::find($id)` if Spatie remains installed, or use the package's own pivot via `RoleModulePermission`.

5. **Add the trait to your User.**
   ```php
   use ModularizeRbac\Laravel\Concerns\HasAccessPermissions;

   class User extends Authenticatable
   {
       use HasAccessPermissions;
   }
   ```

6. **Wire user→role assignments via the new pivot.** If the host previously used Spatie's `model_has_roles`, migrate rows into `role_user`:
   ```sql
   INSERT INTO role_user (role_id, user_id, organization_id, created_at, updated_at)
   SELECT role_id, model_id, organization_id, NOW(), NOW()
   FROM model_has_roles WHERE model_type = 'App\\Models\\User';
   ```

7. **Decide on Spatie sync.** Keep `spatie/laravel-permission` installed if any code still uses `$user->hasRole(...)` etc. through Spatie's `HasRoles` trait. The package keeps `role_has_permissions` in sync as long as `config('access.spatie.enabled')` is `null` (auto) or `true`. To drop Spatie entirely, uninstall it and set the flag to `false` (or omit it — null + Spatie absent = sync off).

8. **Decide on the admin policy.** v2.0 binds `AccessAdminPolicy` to `Gate::before` for `admin.*` abilities by default. Hosts that wired `Gate::define()` manually for `admin.modules.view` etc. can either:
   - Keep the default policy and seed `admin.*` modules + bindings, or
   - Set `config('access.policies.admin')` to `null` and continue with their `Gate::define()` calls.

9. **Re-sync into Spatie (optional).** If staying on Spatie, run a one-shot resync to reconcile any drift:
   ```bash
   php artisan access:sync-spatie --dry-run   # inspect
   php artisan access:sync-spatie             # apply
   ```

10. **Diagnose.**
    ```bash
    php artisan access:diagnose
    ```

### Compatibility matrix

| Scenario | v1.x | v2.0 |
|---|---|---|
| Install without Spatie | ❌ hard require | ✅ supported |
| `$user->can('events.view')` without Spatie | ❌ | ✅ via `HasAccessPermissions` |
| Audit log of domain events | manual | ✅ automatic |
| `admin.modules.view` etc. | `Gate::define()` per ability | `AccessAdminPolicy` covers all |
| Tenant resolution | host code | `TenantContext` port |
| Console diagnostics | none | ✅ `access:diagnose` |
| Spatie `role_has_permissions` sync | observer (eager) | `SpatiePermissionGateway` (event-driven) |

---

## [1.1.0] - 2026-05-23

Additive only — no breaking changes vs. v1.0.

### Added (via `modularize-rbac/core` v1.1.0 bump)
- `TenantContext` port.
- `Audit` domain (entity + VO + repository port + `ListAuditEntries` use-case).
- Read models: `GetRolePermissionMatrix`, `ListUserAccessibleModules`.
- `UserRoleResolver` port.

## [1.0.0] - 2026-05-23

First publishable Packagist release. Hexagonal refactor split across PRs 0-6.

### Breaking changes vs. `casamento/rbac` 0.1.0

#### Naming
- Package renamed from `casamento/rbac` to `modularize-rbac/laravel`.
- Namespace `Casamento\Rbac\*` → `ModularizeRbac\Laravel\*`.
- ServiceProvider: `RbacServiceProvider` → `AccessServiceProvider`.
- Config file: `config/rbac.php` → `config/access.php`. Publish tag is now `access-config`.
- Config keys moved from `config('rbac.*')` to `config('access.*')`.

#### Architecture
- The framework-agnostic core (entities, value objects, domain services, use-cases, ports) lives in a separate package: [`modularize-rbac/core`](https://github.com/chrisdjst/access-core).
- This package is a thin Laravel bridge.
- `RoleModulePermissionObserver`, `Concerns\HasUuid`, `Concerns\HasTranslations` removed.

#### REST API
- URLs and verbs unchanged; response shapes preserved.
- Validation errors return 422 with a field-keyed error map.
- Authorization failures return 403.
- Not-found IDs return 404.

## [0.1.0] - 2026-04-23

Initial extraction from the Casamento platform as `casamento/rbac`. Never published to Packagist.
