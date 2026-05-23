# modularize-rbac/laravel

Laravel bridge for [`modularize-rbac/core`](https://github.com/chrisdjst/access-core). Ships Eloquent repositories, HTTP controllers, FormRequests, migrations, and an optional Spatie permissions adapter so a host Laravel app can wire the hexagonal RBAC core in one `composer require`.

[![CI](https://github.com/chrisdjst/access-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/chrisdjst/access-laravel/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/modularize-rbac/laravel.svg)](https://packagist.org/packages/modularize-rbac/laravel)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## What it gives you

A drop-in admin RBAC layer with:

- **Modules** â€” feature catalog with hierarchy, soft-delete, sort order, i18n.
- **Roles** â€” guard-scoped, tenant-aware (optional), level-ordered, system-flag protected.
- **Permissions** â€” `{slug}.{action}` names compatible with Spatie's `Gate`.
- **Role Ã— Module permission matrix** â€” flag-based UI (`is_reading_allowed`, `is_writing_allowed`, ...) translated to Spatie actions by a domain service.
- **Languages + Translations** â€” polymorphic translations for module/role names with locale fallback.
- **REST API** â€” `/api/admin/modules`, `/api/admin/roles`, `/api/admin/languages` ready to mount.

## Architecture

```
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚  Infrastructure (this package)                               â”‚
â”‚  Eloquent models Â· Repositories Â· Controllers Â· Requests     â”‚
â”‚  Resources Â· ServiceProvider Â· Migrations Â· Routes Â· Spatie  â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                           â”‚ implements ports
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â–¼â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚  modularize-rbac/core (framework-agnostic)                 â”‚
â”‚  Application use-cases Â· Domain entities Â· Ports Â· Events    â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
```

This package depends on `modularize-rbac/core` for the entire domain + application layer. You can also embed `access-core` in any non-Laravel PHP project â€” see [its README](https://github.com/chrisdjst/access-core).

## Install

```bash
composer require modularize-rbac/laravel
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

## Layout

```
.
â”œâ”€â”€ composer.json
â”œâ”€â”€ config/access.php
â”œâ”€â”€ database/migrations/        # 9 migrations
â”œâ”€â”€ routes/api.php
â”œâ”€â”€ src/
â”‚   â”œâ”€â”€ AccessServiceProvider.php
â”‚   â”œâ”€â”€ Authorization/          # GateAuthorizer
â”‚   â”œâ”€â”€ Eloquent/
â”‚   â”‚   â”œâ”€â”€ Mappers/            # Entity <-> Eloquent
â”‚   â”‚   â””â”€â”€ Repositories/       # Implement access-core ports
â”‚   â”œâ”€â”€ Events/                 # LaravelEventDispatcher
â”‚   â”œâ”€â”€ Http/
â”‚   â”‚   â”œâ”€â”€ Controllers/        # Thin â€” call use-cases
â”‚   â”‚   â”œâ”€â”€ Requests/           # FormRequest validation
â”‚   â”‚   â””â”€â”€ Resources/          # Output DTO â†’ JSON
â”‚   â”œâ”€â”€ Localization/           # LaravelLocaleResolver
â”‚   â”œâ”€â”€ Models/                 # Pure persistence DTOs
â”‚   â”œâ”€â”€ Persistence/            # SystemClock, UuidV4IdGenerator, LaravelUnitOfWork
â”‚   â”œâ”€â”€ Spatie/                 # Optional permission gateway
â”‚   â””â”€â”€ Translations/           # TranslationApplier helper
â”œâ”€â”€ tests/                      # Pest + Testbench
â””â”€â”€ frontend/                   # NPM package (separate concern)
```

## Out of scope

- AdminUser auth (the `admin.auth` middleware alias is host-defined).
- Tenant model â€” pluggable via `config('access.tenant_model')`.
- v1.0 still hard-requires `spatie/laravel-permission`. v2.0 will fully decouple.
