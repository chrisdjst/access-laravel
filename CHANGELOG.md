# Changelog

All notable changes to `modularize-rbac/laravel` are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

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
