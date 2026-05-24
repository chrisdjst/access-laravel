# Upgrading `modularize-rbac/laravel`

This guide consolidates the upgrade notes for major and minor versions of the bridge. Patch releases never require upgrade steps — the [CHANGELOG](./CHANGELOG.md) is the source of truth for everything.

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
