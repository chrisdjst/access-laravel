# Upgrading `modularize-rbac/laravel`

This guide consolidates the upgrade notes for major and minor versions of the bridge. Patch releases never require upgrade steps — the [CHANGELOG](./CHANGELOG.md) is the source of truth for everything.

---

## v2.7 → v2.8

`v2.8.0` is fully backwards compatible with `v2.7.x`. **Two new migrations to run.** The release ships three additive primitives that are either opt-in or preserve the v2.7 default response shape.

### Composer bump + migrate

```bash
composer require modularize-rbac/laravel:^2.8
php artisan migrate
```

The two new migrations are idempotent:

- `2026_06_05_000000_add_soft_deletes_to_roles_and_languages.php` — `deleted_at` column on both tables.
- `2026_06_06_000000_create_role_module_permission_history.php` — append-only history table.

### Behavior change: `DELETE /api/admin/roles/{id}` now soft-deletes

The row stays with `deleted_at` set. Subsequent `GET /api/admin/roles/{id}` returns 404 (same as v2.7 contract); listings exclude it. The new `POST /api/admin/roles/{id}/restore` reverses it.

Hosts that need true purge keep calling `RoleRepository::delete()` directly from ops scripts — it still hard-deletes via `forceDelete()`.

### Restoring soft-deleted roles

```bash
curl -X POST https://app.test/api/admin/roles/{id}/restore
# → 200 with the restored role
# → 422 when the row is not soft-deleted
# → 404 when missing entirely
```

Authorization: `admin.roles.delete` (restore is the inverse of delete).

### Binding history (always on)

Every change to `role_module_permission` is captured in `role_module_permission_history`:

```bash
curl "https://app.test/api/admin/roles/{id}/bindings/history?limit=50"
# → {data: [{change_type, module_permission_id_before, ...}], meta: {total, limit, offset}}
```

Authorization: `admin.roles.view`. Query params: `limit`, `offset`, `since` (ISO-8601), `module_id`. Captures `create`/`update`/`delete` change types with actor + timestamp.

### Custom permission actions (opt-in)

Via `modularize-rbac/core` v1.9's new `PermissionActionRegistry`:

```php
// app/Providers/AppServiceProvider.php
use ModularizeRbac\Core\Domain\RoleModulePermission\PermissionActionRegistry;

public function register(): void
{
    $this->app->singleton(PermissionActionRegistry::class, function () {
        $registry = new PermissionActionRegistry();
        $registry->register('is_export_allowed', 'export');
        $registry->register('is_sign_allowed', 'sign');

        return $registry;
    });
}
```

The default registry pre-seeds the 5 native actions, so hosts that don't bind a custom instance see no change. Persistence side: `ModulePermission` carries the custom flags in an optional `extraFlags` array. See the [core v1.9 CHANGELOG](https://github.com/chrisdjst/access-core/blob/main/CHANGELOG.md#190---2026-05-25) for details.

### Deprecation watch

- `ModulePermission::FLAG_TO_ACTION` const stays in v2.8 but will be removed in v3.0. Hosts reading it directly should migrate to `PermissionActionRegistry::all()` instead.

---

## v2.6 → v2.7

`v2.7.0` is fully backwards compatible with `v2.6.x`. The release ships five observability + security features that are either opt-in or preserve the v2.6 default. One new migration to run.

### Composer bump + migrate

```bash
composer require modularize-rbac/laravel:^2.7
php artisan migrate
```

The migration adds nullable `entry_hash` and `previous_hash` columns to `access_audit_log` (used by the optional hash chain — see below). Idempotent; running on a host that already added the columns is a no-op.

No `modularize-rbac/core` bump.

### Telemetry events (drop-in)

```php
use ModularizeRbac\Laravel\Events\Telemetry\AbilityResolved;
use ModularizeRbac\Laravel\Events\Telemetry\CacheLookup;

Event::listen(AbilityResolved::class, function ($e) {
    // $e->ability, $e->allowed, $e->source, $e->durationMicros
});
Event::listen(CacheLookup::class, function ($e) {
    // $e->namespace, $e->key, $e->hit, $e->version
});
```

See the README's [Telemetry recipes](./README.md#telemetry-recipes) section for ready-to-paste Sentry, Prometheus, and structured-log integrations.

### Audit log level knob

```php
// config/access.php
'audit' => [
    'enabled' => true,
    'log_failures' => 'error',   // warning | error | critical | false
],
```

Default `'warning'` matches v2.6 behavior. Set to `false` to swallow failures silently.

### PII redaction

```php
// config/access.php
'audit' => [
    // ...
    'redact_fields' => [
        'password', 'api_token', 'email', 'cpf', 'cnpj', 'phone',
    ],
],
```

Default list covers common PII + secret field names. Matching is case-insensitive substring — `'email'` covers `email`, `user_email`, `customer_email`, etc. Walks nested arrays. Set to `[]` to disable.

### Tamper-evident hash chain (opt-in)

```php
// config/access.php
'audit' => [
    // ...
    'hash_chain' => ['enabled' => true],
],
```

When enabled, every new audit entry gets a `sha256(previous_hash || canonical(this row))` chained to the prior entry in the same `(tenant_id, event_name)` partition. Existing pre-flip rows keep their NULL hash columns and the verify command skips them.

Verify the chain end-to-end:

```bash
php artisan access:audit:verify           # all partitions
php artisan access:audit:verify --since=2026-01-01T00:00:00Z
php artisan access:audit:verify --event=module.created
```

Exits 1 if any row's hash doesn't match — wire it into a periodic cron or your CI/CD compliance pipeline.

---

## v2.5 → v2.6

`v2.6.0` is fully backwards compatible with `v2.5.x`. No schema changes, no API changes. The release ships three DX tools you can opt into when convenient.

### Composer bump

```bash
composer require modularize-rbac/laravel:^2.6
```

No `modularize-rbac/core` bump.

### One-command install (new)

For fresh hosts:

```bash
composer require modularize-rbac/laravel
php artisan access:install
```

This replaces the previous 3-step recipe (`vendor:publish --tag=access-config` → `migrate` → "now add the trait to User"). Flags:

| Flag | What it does |
|---|---|
| `--no-config` | Skip publishing `config/access.php` |
| `--no-migrate` | Skip running migrations |
| `--with-lang` | Also publish `lang/{en,pt_BR}/exceptions.php` |
| `--with-seeder` | Also publish the example `AccessSeeder.php` |

The command does NOT auto-edit your User model — it prints the trait snippet at the end so you can copy it cleanly into your own file.

### Factories (new)

Every package model now has an Eloquent factory:

```php
use ModularizeRbac\Laravel\Models\Module;
use ModularizeRbac\Laravel\Models\Role;

Module::factory()->count(50)->create();
Role::factory()->system()->withParent($parent)->create();
```

States: `Module::factory()->inactive() / withParent() / trashed()`, `Role::factory()->system() / withParent() / forGuard() / forTenant()`, `Permission::factory()->forModule() / action()`, `Language::factory()->isDefault() / inactive()`, `ModulePermission::factory()->allowAll() / readOnly()`.

The factory namespace is registered in production autoload, so host seeders + tests can use them without extra config.

### Example seeder (new)

```bash
php artisan vendor:publish --tag=access-seeder
php artisan db:seed --class=AccessSeeder
```

Creates a default English language, three modules, an admin role with full matrix, and a viewer role with read-only access — all routed through the `CreateModule` / `CreateRole` / `SyncRoleModules` use-cases so domain events + audit log + Spatie sync all fire. Edit it to match your app, or copy the pattern.

---

## v2.4 → v2.5

`v2.5.0` is fully backwards compatible with `v2.4.x`. No schema changes, no breaking API changes. The release ships four polish PRs that hosts can opt into incrementally.

### Composer bump

```bash
composer require modularize-rbac/laravel:^2.5
```

Pulls `modularize-rbac/core: ^1.8` (additive — `Pagination`, `PaginatedResult`, `ModuleFilter`, `RoleFilter` VOs + `searchPaginated()` port methods + `ListModulesPaginated` / `ListRolesPaginated` use-cases).

### What's new for clients

**Pagination + filters on `GET /modules` and `GET /roles`** (opt-in via query params):

```bash
GET /api/admin/modules?limit=10&offset=0&is_active=true&root_module_id=...&slug_like=event
GET /api/admin/roles?limit=20&offset=0&guard=admin&is_system=false&level_min=10&level_max=90&has_parent=true
```

Without any query param the endpoints return the full list (v2.4 contract) but now include `meta.count`:

```json
{ "data": [ ... ], "meta": { "count": 42 } }
```

With pagination params, the envelope becomes:

```json
{ "data": [ ... ], "meta": { "total": 142, "limit": 10, "offset": 0 } }
```

`meta.count` only appears on the non-paginated branch, so clients can distinguish the two paths by the meta keys present.

**`Access-Api-Version` header** is stamped on every response (value: `"1"`). Bumps only on breaking response shape changes.

**Rate limiting on bulk endpoints** — default 10 attempts/minute per authenticated user on `POST/DELETE /api/admin/modules/bulk`, `POST /api/admin/roles/{role}/clone`, and `POST /api/admin/roles/{role}/users/bulk`. Override via config:

```php
// config/access.php
'rate_limit' => ['bulk' => '30,1'],   // 30/min
// or to disable:
'rate_limit' => ['bulk' => null],
```

**`openapi.json`** in the package repo root. Point your SDK generator (Postman, openapi-generator, Stainless, etc.) at it. Hosts that publish their own API docs site can copy the file into their docs build pipeline.

### Generate the spec locally

```bash
php artisan access:openapi --update   # rewrite openapi.json after editing OpenApiDefinition.php
php artisan access:openapi --check    # CI-friendly drift check (exits 1 on mismatch)
```

The package CI has a workflow (`.github/workflows/openapi-drift.yml`) that runs this check on every PR.

### Backwards-compat notes

- **Filter validation**: `?level_min=50&level_max=10` returns 422 because the band is inverted. Hosts that previously passed silly values silently are now told.
- **`limit=` cap**: the max accepted is 1000. Requesting more returns 422.
- **No-params response shape**: now includes a small `meta: { count: N }` object. Clients reading `data[*]` are unaffected. Clients explicitly asserting `meta` is absent need to relax that assertion.

---

## v2.3 → v2.4

`v2.4.0` is fully backwards compatible with `v2.3.x`. No API changes. Two minor mechanical steps to apply locally:

### Composer bump

```bash
composer require modularize-rbac/laravel:^2.4
```

No `modularize-rbac/core` bump.

### Run the new migration

```bash
php artisan migrate
```

Adds a single index on `role_module_permission.role_id`. Idempotent — re-running on hosts that already added it manually is a no-op. Tables already in production stay intact.

### Benchmark suite (opt-in)

The release ships a PHPBench-based benchmark suite under `benchmarks/`:

```bash
composer bench
```

Documented in [BENCHMARK.md](./BENCHMARK.md). Run takes 2–3 minutes; reproduces the perf wins described in CHANGELOG. Hosts don't need to run it — it's there for upstream regression tracking.

### What changed under the hood

- `HasAccessPermissions::canAccess()` is faster on hosts using role hierarchies (`parent_role_id`) and on hosts with permission inheritance enabled. Behavior is identical — same answers, fewer queries.
- A new scoped service `ModuleHierarchyIndex` is bound in the container. Hosts that resolve the bridge's ServiceProvider in custom contexts should make sure their custom bootstrappers don't override `Application::scoped()` in ways that break it.

---

## v2.2 → v2.3

`v2.3.0` is fully backwards compatible with `v2.2.x`. No schema or API changes — the only addition is a read cache layer that's **on by default**.

### Composer bump

```bash
composer require modularize-rbac/laravel:^2.3
```

No `modularize-rbac/core` bump required (still `^1.7`).

### Read cache (enabled by default)

The language + module repositories are now decorated with a Laravel-cache-backed read cache. Behavior:

- Reads (`find`, `findBySlug`/`findByCode`, `default`, `all`, `allActiveTree`) consult the cache first.
- Writes through the use-cases auto-invalidate via a version-key bump.
- A defence-in-depth event listener bumps the version when `LanguageDefaultChanged` / `ModuleCreated|Updated|Deleted` are dispatched — direct DB writes that still emit the event keep the cache coherent.

New config block:

```php
// config/access.php
'cache' => [
    'enabled' => true,   // false to bypass the layer entirely
    'store' => null,     // null = default Laravel cache store
    'ttl' => 3600,       // seconds
],
```

To disable the layer entirely:

```php
'cache' => ['enabled' => false],
```

Hosts running on a cache store with broken `null` semantics (rare) can lower the TTL or set `enabled => false` — the wrapper sentinel that survives `Cache::has()` makes the layer safe on all default stores tested (`array`, `file`, `database`, `redis`).

### Why this is opt-out instead of opt-in

Unlike the v2.2 inheritance feature, the cache is correctness-neutral by construction: every mutation that goes through a use-case bumps the version, and the event listener catches the rest. The default-on stance gives hosts the performance win without an extra config knob to flip.

---

## v2.1 → v2.2

`v2.2.0` is fully backwards compatible with `v2.1.x`. The notes below describe additive features and one new migration to apply.

### Composer bump

```bash
composer require modularize-rbac/laravel:^2.2
```

This pulls `modularize-rbac/core: ^1.7` (additive — `CloneRole`, `BulkCreateModules`, `BulkDeleteModules`, `AssignUsersToRole`, `UserRoleAssigner`, `PermissionInheritanceResolver`, `parentRoleId`, `resolveAncestors`).

### Apply the new migration

```bash
php artisan migrate
```

Adds a nullable `parent_role_id` self-FK on the `roles` table. Idempotent — re-running on a host that already added the column is a no-op.

### New REST endpoints (additive)

| Method | URL | Ability |
|---|---|---|
| POST | `/api/admin/roles/{source}/clone` | `admin.roles.create` |
| POST | `/api/admin/roles/{role}/users/bulk` | `admin.roles.update` |
| POST | `/api/admin/modules/bulk` | `admin.modules.create` |
| DELETE | `/api/admin/modules/bulk` | `admin.modules.delete` |

`StoreRoleRequest` now accepts an optional `parent_role_id` (UUID). Hosts that subclassed `RoleController` or `ModuleController` need to thread the new use-case dependencies through their constructor — the parent signatures widened to inject `CloneRole`, `AssignUsersToRole`, `BulkCreateModules`, `BulkDeleteModules`.

### Console: import / export

```bash
php artisan access:export --output=/tmp/access.json
php artisan access:import /tmp/access.json --strategy=merge
php artisan access:import /tmp/access.json --strategy=replace --force   # DESTRUCTIVE
```

Payloads carry `schema_version: 1`. The importer rejects unknown versions.

### Opt-in: permission inheritance via module hierarchy

```php
// config/access.php
'inheritance' => [
    'enabled' => true,
],
```

Defaults to `false`. When enabled, `$user->can('events.weddings.view')` walks the module tree upward — a parent's binding grants the same action on every descendant. Adopt only after auditing that your module hierarchy + role bindings reflect the inheritance you intend.

### Always-on: role hierarchy via `parent_role_id`

After running the migration, `roles.parent_role_id` is a nullable self-FK. A role with a `parent_role_id` set inherits the entire permission matrix of every ancestor — `HasAccessPermissions::canAccess()` now walks the chain on every check. Hosts that don't populate the column see no behavior change.

Cycle prevention:
- `Role::create()` rejects self-parenting at the domain layer.
- `EloquentRoleRepository::resolveAncestors()` short-circuits on any cycle introduced by raw SQL.

---

## v2.0 → v2.1

`v2.1.0` is fully backwards compatible with `v2.0.x` — no schema changes, no public API removals. The notes below describe **opt-in** changes hosts may want to adopt.

### Composer bump

```bash
composer require modularize-rbac/laravel:^2.1
```

This pulls `modularize-rbac/core: ^1.3` (additive — `AuditRepository::deleteOlderThan`, `CreateRole`, `DeleteRole`).

### New REST endpoints (additive)

| Method | URL | Ability |
|---|---|---|
| POST | `/api/admin/roles` | `admin.roles.create` |
| DELETE | `/api/admin/roles/{role}` | `admin.roles.delete` |
| GET | `/api/admin/roles/{role}/permission-matrix` | `admin.roles.view` |
| GET | `/api/admin/users/{user}/accessible-modules` | `admin.modules.view` |

Existing clients that don't call these routes need no changes. Hosts that subclassed `RoleController` will need to thread the new use-case dependencies (`CreateRole`, `DeleteRole`, `GetRolePermissionMatrix`) through their constructor — the parent constructor signature widened.

### Request validation behavior

- `SyncRoleModulesRequest`: `modules` is now `present, array` (was `required, array`). Empty arrays are accepted to support the "clear all bindings before delete" flow. Each entry must be an `array` — scalars like `42` or `"x"` are now rejected at the FormRequest layer instead of failing deeper.
- `StoreLanguageRequest` / `UpdateLanguageRequest`: when `config('access.allowed_locales')` is a non-empty list, the `code` field is restricted to that list. Empty / unset config (the default) accepts any code.

### New optional config: `access.allowed_locales`

```php
// config/access.php
'allowed_locales' => ['en', 'pt_BR', 'es'],
```

Set to `[]` (default) to preserve v2.0 behavior of accepting any code.

### Localized exception responses

Exception JSON responses now include a localized `error_type` field alongside `message`:

```json
{
  "error": "...",
  "error_type": "Invalid input",
  "message": "Module slug must be unique."
}
```

The detailed `message` stays in whatever language the use-case emitted (English in v2.1). Only the headline is localized. **Clients that read `message` see no change.** Hosts that want extra locales can publish:

```bash
php artisan vendor:publish --tag=access-lang
```

### Audit retention command

```bash
php artisan access:audit:purge --older-than=90d           # delete entries older than 90 days
php artisan access:audit:purge --older-than=2026-01-01    # absolute ISO-8601 cutoff
php artisan access:audit:purge --older-than=90d --dry-run # report count only
```

Cutoff accepts `Nd` / `Nm` / `Ny` relative intervals or an absolute date. Schedule it via Laravel's scheduler if you want ongoing retention.

---

## v1.x → v2.0

`v2.0.0` was a major release. Read this section in full before bumping.

### Composer bump

```bash
composer require modularize-rbac/laravel:^2.0
```

`spatie/laravel-permission` moves to `suggest`. If your host still consumes Spatie directly (`HasRoles` trait on User, `$user->hasRole(...)`, etc.), keep it installed:

```bash
composer require spatie/laravel-permission
```

### Publish the new config + migrate

The middleware default and new `audit` / `policies` blocks need to land. If you've customized `config/access.php`, diff and merge manually rather than overwriting:

```bash
php artisan vendor:publish --tag=access-config --force
php artisan migrate
```

The new migrations are idempotent via `Schema::hasTable` guards — existing tables stay intact.

### Stop extending Spatie's models (if you'd customized them)

The package's `Role` and `Permission` no longer extend `Spatie\Permission\Models\*`. Hosts that subclassed them in v1 to add helpers should:

- Use plain `Eloquent\Model` as the base.
- Copy any methods you actually need.
- **`$role->givePermissionTo()` / `$role->revokePermissionTo()` are gone** — call them on `\Spatie\Permission\Models\Role::find($id)` if Spatie is still installed, or use the package's own pivot via the `RoleModulePermission` model.

### Add the trait to your User model

```php
use ModularizeRbac\Laravel\Concerns\HasAccessPermissions;

class User extends Authenticatable
{
    use HasAccessPermissions;
}
```

This makes `$user->can('events.view')` work through the package's schema via a `Gate::before` callback the ServiceProvider registers. **No Spatie required.**

### Migrate user→role assignments to the new pivot

If your host previously stored these in Spatie's `model_has_roles`, copy them into `role_user`:

```sql
INSERT INTO role_user (role_id, user_id, organization_id, created_at, updated_at)
SELECT role_id, model_id, organization_id, NOW(), NOW()
FROM model_has_roles
WHERE model_type = 'App\\Models\\User';
```

Adjust the `model_type` filter to your User class. The `organization_id` column is nullable — single-tenant hosts can drop it from the `SELECT`.

### Decide on Spatie sync

`config('access.spatie.enabled')` accepts:

- `null` (default) — auto-detect: sync if `spatie/laravel-permission` is installed, skip otherwise.
- `true` — force on. Errors at boot if Spatie isn't installed.
- `false` — force off, even if Spatie is installed.

When sync is on, the package mirrors role-permission bindings into `role_has_permissions` via a write-through gateway as use-cases mutate state.

### Decide on the admin policy

v2.0 binds `AccessAdminPolicy` to `Gate::before` for `admin.*` abilities by default. Hosts that wired `Gate::define('admin.modules.view', ...)` manually in v1 can either:

- **Adopt the policy** — seed `admin.*` modules and bind them to admin roles. Recommended.
- **Keep the manual gates** — set `config('access.policies.admin')` to `null` and continue defining each ability with `Gate::define()`.

### Optional: resync Spatie pivot

If staying on Spatie, run a one-shot resync to reconcile any drift:

```bash
php artisan access:sync-spatie --dry-run   # inspect
php artisan access:sync-spatie             # apply
```

### Verify with diagnose

```bash
php artisan access:diagnose
```

Reports config validity, missing migrations, Spatie status, and policy bindings.

### Middleware default change

`config('access.middleware')` default went from `['api', 'auth:sanctum', 'admin.auth']` to `['api', 'auth:sanctum']`. Hosts that relied on `admin.auth` need to add it back, either to the config or to specific routes.

### Compatibility matrix

| Scenario | v1.x | v2.0+ |
|---|---|---|
| Install without Spatie | ❌ hard require | ✅ supported |
| `$user->can('events.view')` without Spatie | ❌ | ✅ via `HasAccessPermissions` |
| Audit log of domain events | manual | ✅ automatic |
| `admin.modules.view` etc. | `Gate::define()` per ability | `AccessAdminPolicy` covers all |
| Tenant resolution | host code | `TenantContext` port |
| Console diagnostics | none | ✅ `access:diagnose` |
| Spatie `role_has_permissions` sync | observer (eager) | gateway (event-driven) |

---

## `casamento/rbac` 0.1.x → `modularize-rbac/laravel` v1.0

`v1.0.0` was the first Packagist-published release after extraction from the Casamento platform. The breaking changes are mostly mechanical (renames + a namespace flip).

### Package + namespace

| Was | Now |
|---|---|
| `casamento/rbac` | `modularize-rbac/laravel` |
| `Casamento\Rbac\*` | `ModularizeRbac\Laravel\*` |
| `RbacServiceProvider` | `AccessServiceProvider` |
| `config/rbac.php` | `config/access.php` |
| `config('rbac.*')` | `config('access.*')` |
| Publish tag `rbac-config` | `access-config` |

A regex-friendly batch rename across the host:

```bash
# Composer
composer remove casamento/rbac
composer require modularize-rbac/laravel:^1.0

# Source — adjust paths to your host layout
grep -rl 'Casamento\\Rbac' app config | xargs sed -i 's/Casamento\\Rbac/ModularizeRbac\\Laravel/g'
grep -rl "config('rbac" app config | xargs sed -i "s/config('rbac/config('access/g"
grep -rl 'RbacServiceProvider' app config | xargs sed -i 's/RbacServiceProvider/AccessServiceProvider/g'

# Config file
mv config/rbac.php config/access.php
```

### Architecture split

The framework-agnostic core is now a separate package: [`modularize-rbac/core`](https://github.com/chrisdjst/access-core). This package is a thin Laravel bridge. The split is transparent to host code that consumes the bridge — use-cases stay container-resolvable under the new namespace.

Removed entirely:
- `RoleModulePermissionObserver` (replaced by domain events + write-through gateway)
- `Concerns\HasUuid` (replaced by `Persistence\IdGenerator`)
- `Concerns\HasTranslations` (replaced by `Translations\TranslationApplier`)

### REST API contract

URLs, verbs, and response shapes are unchanged. Validation errors return 422 with a field-keyed error map; authorization failures return 403; missing IDs return 404. Existing API clients should need no changes.
