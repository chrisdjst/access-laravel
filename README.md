# modularize/access-laravel

Laravel bridge for [`modularize/access-core`](https://github.com/chrisdjst/access-core). Ships Eloquent repositories, HTTP controllers, FormRequests, migrations, and an optional Spatie permissions adapter so a host Laravel app can wire the hexagonal RBAC core in one `composer require`.

[![CI](https://github.com/chrisdjst/access-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/chrisdjst/access-laravel/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/modularize/access-laravel.svg)](https://packagist.org/packages/modularize/access-laravel)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## What it gives you

A drop-in admin RBAC layer with:

- **Modules** — feature catalog with hierarchy, soft-delete, sort order, i18n.
- **Roles** — guard-scoped, tenant-aware (optional), level-ordered, system-flag protected.
- **Permissions** — `{slug}.{action}` names compatible with Spatie's `Gate`.
- **Role × Module permission matrix** — flag-based UI (`is_reading_allowed`, `is_writing_allowed`, ...) translated to Spatie actions by a domain service.
- **Languages + Translations** — polymorphic translations for module/role names with locale fallback.
- **REST API** — `/api/admin/modules`, `/api/admin/roles`, `/api/admin/languages` ready to mount.

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│  Infrastructure (this package)                               │
│  Eloquent models · Repositories · Controllers · Requests     │
│  Resources · ServiceProvider · Migrations · Routes · Spatie  │
└──────────────────────────┬───────────────────────────────────┘
                           │ implements ports
┌──────────────────────────▼───────────────────────────────────┐
│  modularize/access-core (framework-agnostic)                 │
│  Application use-cases · Domain entities · Ports · Events    │
└──────────────────────────────────────────────────────────────┘
```

This package depends on `modularize/access-core` for the entire domain + application layer. You can also embed `access-core` in any non-Laravel PHP project — see [its README](https://github.com/chrisdjst/access-core).

## Install

```bash
composer require modularize/access-laravel
php artisan vendor:publish --tag=access-config
php artisan migrate
```

Edit `config/access.php` and point `tenant_model` at your tenant class (e.g. `App\Models\Organization::class`) or leave `null` for single-tenant setups.

## Host wiring

### `config/auth.php`

The package defaults to the `admin` guard. Define it in the host:

```php
'guards' => [
    'admin' => [
        'driver' => 'sanctum',
        'provider' => 'admin_users',
    ],
],
```

### Middleware

By default routes use `['api', 'auth:sanctum']`. If your admin flow needs a custom alias (e.g. `admin.auth` that sets the Spatie team id), register it in `bootstrap/app.php` and add it to `config('access.middleware')`.

### Spatie integration (optional sync)

`spatie/laravel-permission` is currently a hard dependency in v1.0 because `Role`/`Permission` extend Spatie's models. The **sync** (replicating role-module-permission grants into `role_has_permissions`) is opt-out:

```php
// config/access.php
'spatie' => [
    'enabled' => null, // null = auto, true = force on, false = force off
],
```

When the sync is off, `SyncRoleModules` still writes to the package's own tables; only the Spatie-side replication is skipped. v2.0 will fully decouple `Role` from `SpatieRole`.

## REST API

All routes under `config('access.route_prefix')` (default `api/admin`):

| Method | URL | Action |
|---|---|---|
| GET | /modules | List modules + sub-modules |
| POST | /modules | Create module |
| GET | /modules/{id} | Show one |
| PUT | /modules/{id} | Update (incl. translations) |
| DELETE | /modules/{id} | Soft delete |
| GET | /roles | List roles (filter by `?guard=` and `?organization_id=`) |
| GET | /roles/{id} | Show role + flags matrix |
| PUT | /roles/{id} | Update display_name + translations |
| PUT | /roles/{id}/modules | Sync the role's full permission matrix |
| GET | /languages | List languages |
| POST | /languages | Create |
| GET | /languages/{id} | Show |
| PUT | /languages/{id} | Update |
| DELETE | /languages/{id} | Delete (rejects default language) |
| PUT | /languages/{id}/default | Mark as default |

## Authorization

Each use-case calls `Authorizer::ensure('admin.X.Y')` at its boundary. The Laravel adapter (`GateAuthorizer`) delegates to Laravel's `Gate` resolved against the configured guard. Register policies / `Gate::define()` in your host's `AuthServiceProvider` for the canonical abilities the package checks:

- `admin.modules.view`, `admin.modules.create`, `admin.modules.update`, `admin.modules.delete`
- `admin.roles.view`, `admin.roles.update`
- `admin.languages.view`, `admin.languages.create`, `admin.languages.update`, `admin.languages.delete`

## Calling use-cases directly (CLI, jobs, custom controllers)

Every use-case is resolvable from the container:

```php
use Modularize\Access\Application\Module\CreateModule\CreateModule;
use Modularize\Access\Application\Module\CreateModule\CreateModuleInput;

$module = app(CreateModule::class)->execute(new CreateModuleInput(
    slug: 'billing',
    name: 'Billing',
    redirect: '/billing',
    icon: 'receipt',
    rootModuleId: null,
    sortOrder: 10,
));
```

## Layout

```
.
├── composer.json
├── config/access.php
├── database/migrations/        # 9 migrations
├── routes/api.php
├── src/
│   ├── AccessServiceProvider.php
│   ├── Authorization/          # GateAuthorizer
│   ├── Eloquent/
│   │   ├── Mappers/            # Entity <-> Eloquent
│   │   └── Repositories/       # Implement access-core ports
│   ├── Events/                 # LaravelEventDispatcher
│   ├── Http/
│   │   ├── Controllers/        # Thin — call use-cases
│   │   ├── Requests/           # FormRequest validation
│   │   └── Resources/          # Output DTO → JSON
│   ├── Localization/           # LaravelLocaleResolver
│   ├── Models/                 # Pure persistence DTOs
│   ├── Persistence/            # SystemClock, UuidV4IdGenerator, LaravelUnitOfWork
│   ├── Spatie/                 # Optional permission gateway
│   └── Translations/           # TranslationApplier helper
├── tests/                      # Pest + Testbench
└── frontend/                   # NPM package (separate concern)
```

## Out of scope

- AdminUser auth (the `admin.auth` middleware alias is host-defined).
- Tenant model — pluggable via `config('access.tenant_model')`.
- v1.0 still hard-requires `spatie/laravel-permission`. v2.0 will fully decouple.
