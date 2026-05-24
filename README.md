# modularize-rbac/laravel

Laravel bridge for [`modularize-rbac/core`](https://github.com/chrisdjst/access-core). Ships Eloquent repositories, HTTP controllers, FormRequests, migrations, an audit log pipeline, console commands, and an optional Spatie permission adapter.

[![CI](https://github.com/chrisdjst/access-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/chrisdjst/access-laravel/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/modularize-rbac/laravel.svg)](https://packagist.org/packages/modularize-rbac/laravel)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## What v2.0 ships

A drop-in admin RBAC layer with:

- **Modules** — feature catalog with hierarchy, soft-delete, sort order, i18n.
- **Roles** — guard-scoped, tenant-aware, level-ordered, system-flag protected.
- **Permissions** — `{slug}.{action}` names, package-owned (Spatie is optional).
- **Role × Module matrix** — flag-based UI translated to action names by a domain service.
- **Languages + Translations** — polymorphic translations with locale fallback.
- **REST API** — `/api/admin/modules`, `/roles`, `/languages`, `/audit`.
- **Audit log** — every domain event is auto-persisted to `access_audit_log`.
- **`HasAccessPermissions` trait** — drop on your User to make `$user->can('events.view')` work **without Spatie**.
- **`AccessAdminPolicy`** — turn-key Gate::before for the package's `admin.*` abilities.
- **Console commands** — `access:diagnose`, `access:sync-spatie`, `access:audit`.
- **Spatie integration is opt-in** — the package works whether or not `spatie/laravel-permission` is installed.

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│  Infrastructure (this package)                               │
│  Eloquent · Mappers · Controllers · Resources · Audit        │
│  Console commands · Policies · Spatie adapter (opt-in)       │
└──────────────────────────┬───────────────────────────────────┘
                           │ implements ports
┌──────────────────────────▼───────────────────────────────────┐
│  modularize-rbac/core (framework-agnostic, PHP 8.2+)         │
│  Use-cases · Domain entities · Ports · Events · Read models  │
└──────────────────────────────────────────────────────────────┘
```

## Quickstart

From a fresh Laravel 11 / 12 host to a first authorized request in roughly five minutes.

### 1. Install

```bash
composer require modularize-rbac/laravel
php artisan vendor:publish --tag=access-config
php artisan migrate
```

### 2. Wire the User model

```php
// app/Models/User.php
use ModularizeRbac\Laravel\Concerns\HasAccessPermissions;

class User extends Authenticatable
{
    use HasAccessPermissions;
}
```

### 3. Seed a module, a role, and a binding

```php
// database/seeders/DatabaseSeeder.php (or a tinker session)
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Role\CreateRole\CreateRole;
use ModularizeRbac\Core\Application\Role\CreateRoleInput;
use ModularizeRbac\Core\Application\RoleModulePermission\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\RoleModulePermission\SyncRoleModules\SyncRoleModulesInput;

$module = app(CreateModule::class)->execute(new CreateModuleInput(
    slug: 'events',
    name: 'Events',
    redirect: '/events',
    icon: 'calendar',
    rootModuleId: null,
    sortOrder: 10,
));

$role = app(CreateRole::class)->execute(new CreateRoleInput(
    name: 'event_viewer',
    displayName: 'Event Viewer',
    guardName: 'web',
    level: 100,
));

app(SyncRoleModules::class)->execute(new SyncRoleModulesInput(
    roleId: $role->id,
    modules: [
        ['module_id' => $module->id, 'is_reading_allowed' => true],
    ],
));

DB::table('role_user')->insert([
    'role_id' => $role->id,
    'user_id' => 1,
    'organization_id' => null,
    'created_at' => now(),
    'updated_at' => now(),
]);
```

### 4. Use it

```php
// In any controller / Gate / Blade
if ($request->user()->can('events.view')) {
    // ✓ allowed via role_user → role_module_permission → module
}
```

### 5. (Optional) Hit the admin API

The admin REST surface lives under `config('access.route_prefix')` (default `api/admin`). With a bearer token whose User has `admin.modules.view`:

```bash
curl -H "Authorization: Bearer $TOKEN" https://app.test/api/admin/modules
```

That's the full path. The rest of this README is configuration knobs, the full REST table, and architecture details.

## Install

```bash
composer require modularize-rbac/laravel
php artisan vendor:publish --tag=access-config
php artisan migrate
```

Edit `config/access.php` and point `tenant_model` at your tenant class or leave `null` for single-tenant setups.

## Host wiring

### `config/auth.php`

Define the `admin` guard the package defaults to:

```php
'guards' => [
    'admin' => [
        'driver' => 'sanctum',
        'provider' => 'admin_users',
    ],
],
```

### `HasAccessPermissions` on your User

```php
use ModularizeRbac\Laravel\Concerns\HasAccessPermissions;

class User extends Authenticatable
{
    use HasAccessPermissions;
}
```

Provides:
- `$user->rbacRoles()` BelongsToMany via the `role_user` pivot
- `$user->canAccess('events.view')` — direct lookup against the package schema

The `AccessServiceProvider` registers `Gate::before` so `$user->can('events.view')` works through Laravel's normal authorization flow.

### Tenant context (optional)

Multi-tenant hosts bind the current tenant id in the container from their tenant-resolution middleware:

```php
$app->instance('access.current_tenant_id', (string) $request->user()->organization_id);
```

`TenantContext::currentTenantId()` reads this value. Single-tenant hosts never bind the key.

### Spatie integration (optional)

`spatie/laravel-permission` is in `suggest` since v2.0. Install it alongside if you want `role_has_permissions` kept in sync (so Spatie's `HasRoles` trait keeps working on a different User model):

```bash
composer require spatie/laravel-permission
```

```php
// config/access.php
'spatie' => [
    'enabled' => null, // null = auto, true = force on, false = force off
],
```

## REST API

All routes under `config('access.route_prefix')` (default `api/admin`):

| Method | URL | Action |
|---|---|---|
| GET | /modules | List modules |
| POST | /modules | Create |
| GET | /modules/{id} | Show |
| PUT | /modules/{id} | Update |
| DELETE | /modules/{id} | Soft delete |
| GET | /roles | List roles |
| GET | /roles/{id} | Show + matrix |
| PUT | /roles/{id} | Update display_name + translations |
| PUT | /roles/{id}/modules | Sync the role's permission matrix |
| GET | /languages | List |
| POST | /languages | Create |
| GET | /languages/{id} | Show |
| PUT | /languages/{id} | Update |
| DELETE | /languages/{id} | Delete (rejects default) |
| PUT | /languages/{id}/default | Mark as default |
| **GET** | **/audit** | **List audit entries (`?event=&actor_id=&tenant_id=&since=&until=&limit=&offset=`)** |

## Console commands

- `php artisan access:diagnose` — pre-deploy health check.
- `php artisan access:sync-spatie [--dry-run]` — force resync of every role-module binding into Spatie's pivot.
- `php artisan access:audit [--event= --actor= --tenant= --since= --until= --limit= --format=table|json]` — query the audit log.

## Authorization model

Two layers:

1. **User layer** — `Gate::before` (registered by the ServiceProvider) calls `$user->canAccess($ability)` when the User has the `HasAccessPermissions` trait. Resolves `events.view`-style abilities directly from `role_user` + `role_module_permission` + `module_permissions`.

2. **Admin layer** — `AccessAdminPolicy` (the default `config('access.policies.admin')`) wraps the same `canAccess()` check but scoped to `admin.*` abilities the package's use-cases consult (`admin.modules.view`, `admin.audit.view`, ...). Hosts override via config.

To grant `admin.modules.view`, create a module with slug `admin.modules`, bind it to a role with `is_reading_allowed = true`, and assign the role to the user via `role_user`.

## Calling use-cases directly

Every use-case is container-resolvable:

```php
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;

$module = app(CreateModule::class)->execute(new CreateModuleInput(
    slug: 'billing',
    name: 'Billing',
    redirect: '/billing',
    icon: 'receipt',
    rootModuleId: null,
    sortOrder: 10,
));
```

## Upgrading

- [UPGRADING.md](./UPGRADING.md) — consolidated upgrade guide for v2.0 → v2.1, v1.x → v2.0, and `casamento/rbac` → v1.0.
- [CHANGELOG.md](./CHANGELOG.md) — full history with all additive changes and bugfixes.

## Layout

```
.
├── composer.json
├── config/access.php
├── database/migrations/        # v2.0 schema (idempotent)
├── routes/api.php
├── src/
│   ├── AccessServiceProvider.php
│   ├── Audit/                  # AuditingListener
│   ├── Authorization/          # GateAuthorizer, AccessAdminPolicy
│   ├── Concerns/               # HasAccessPermissions trait
│   ├── Console/                # diagnose / sync-spatie / audit
│   ├── Eloquent/
│   │   ├── Mappers/            # Entity <-> Eloquent
│   │   └── Repositories/       # Implement core ports
│   ├── Events/                 # LaravelEventDispatcher
│   ├── Http/                   # Controllers / FormRequests / Resources
│   ├── Localization/           # LaravelLocaleResolver
│   ├── Models/                 # Persistence DTOs
│   ├── Persistence/            # Clock / IdGenerator / UnitOfWork
│   ├── Spatie/                 # Optional permission gateway
│   ├── Tenant/                 # LaravelTenantContext
│   └── Translations/           # TranslationApplier
└── tests/                      # Pest + Testbench (matrix: with/without Spatie)
```
