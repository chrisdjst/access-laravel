# modularize/access-laravel

Laravel bridge for [`modularize/access-core`](https://github.com/chrisdjst/access-core): module + role + permission management with i18n translations, backed by Eloquent and (optionally) Spatie permissions.

> **Status: WIP, refactoring towards v1.0.**
>
> This package was previously published as `casamento/rbac`. It is currently being rewritten with a hexagonal architecture, splitting the framework-agnostic core into `modularize/access-core`. The current `main` branch carries the renamed namespaces; the deeper architectural refactor is rolling out in PRs 1-6 (see [CHANGELOG.md](./CHANGELOG.md)). The first stable Packagist release will be `v1.0.0`.

## What's inside (current state)

- Models: `Module`, `ModulePermission`, `ModulePrice`, `RoleModulePermission`, `Role` (extends Spatie), `Permission` (extends Spatie), `Language`, `Translation`.
- Controllers + routes: `GET/POST/PUT/DELETE /admin/modules`, `/admin/roles`, `/admin/languages`, plus `PUT /admin/roles/{id}/modules` for the permission matrix.
- FormRequests, JsonResources, observer that mirrors the custom `role_module_permission` pivot into Spatie's `role_has_permissions`.
- 9 migrations covering Spatie tables + modules/permissions/languages/translations.
- Concerns: `HasUuid`, `HasTranslations` (these will be replaced by domain value objects + services in PR 1).

A separate frontend NPM package (`@casamento/admin-rbac`, will be renamed) lives under `frontend/` — see `frontend/README.md`.

## Layout

```
.
├── composer.json
├── config/access.php           # publishable config
├── database/migrations/        # 9 migrations
├── routes/api.php              # module + role + language routes
├── src/
│   ├── Concerns/               # HasUuid, HasTranslations (to be extracted to access-core)
│   ├── Contracts/              # Tenant marker interface
│   ├── Http/{Controllers,Requests,Resources}/
│   ├── Models/
│   ├── Observers/
│   └── AccessServiceProvider.php
├── tests/
└── frontend/                   # NPM package (separate concern)
```

## Backend install (host Laravel app)

While `v1.0.0` is not yet on Packagist, install via path repository in the host:

```json
"require": {
    "modularize/access-laravel": "^1.0@dev"
},
"repositories": [
    { "type": "path", "url": "../access-laravel", "options": { "symlink": true } }
]
```

Then:

```bash
composer require modularize/access-laravel:^1.0@dev
php artisan vendor:publish --tag=access-config
php artisan migrate
```

Edit `config/access.php` and point `tenant_model` at your tenant class (e.g. `App\Models\Organization::class`) or leave `null` for single-tenant setups. Roles call `->tenant()` to resolve their owner.

### config/auth.php

The package's default `guard_name` is `admin`. Ensure your host's `config/auth.php` defines that guard:

```php
'guards' => [
    'admin' => [
        'driver' => 'sanctum',
        'provider' => 'admin_users',
    ],
],
```

### admin.auth middleware

The package's routes use `auth:sanctum` + `admin.auth`. The host app must register the `admin.auth` alias (typically in `bootstrap/app.php`) pointing at a middleware that:
1. Confirms the authenticated user is an admin.
2. Sets the Spatie team context: `setPermissionsTeamId(config('access.admin_team_id'))`.

> Note: in v1.0 the default middleware stack will be lighter (`['api', 'auth:sanctum']`); the `admin.auth` alias will become opt-in.

### config/permission.php

To use the package's `Role` + `Permission` instead of Spatie's defaults:

```php
'models' => [
    'permission' => \Modularize\Access\Laravel\Models\Permission::class,
    'role' => \Modularize\Access\Laravel\Models\Role::class,
],
```

### Windows note

`"symlink": true` requires Developer Mode. Without it, Composer falls back to copying.

### Docker / Sail note

When the host runs in a container, mount the package path explicitly in `docker-compose.yml`:

```yaml
services:
  app:
    volumes:
      - '.:/var/www/html'
      - '../access-laravel:/var/www/access-laravel'
```

Then run `composer install` **inside the container**.

## Routes registered

All under `config('access.route_prefix')` (default `api/admin`):

| Method | URL | Action |
|---|---|---|
| GET | /modules | List modules + sub-modules |
| POST | /modules | Create module |
| GET | /modules/{id} | Show one |
| PUT | /modules/{id} | Update (incl. translations) |
| DELETE | /modules/{id} | Soft delete |
| GET | /roles | List roles |
| GET | /roles/{role} | Show role + flags matrix |
| PUT | /roles/{role} | Update display_name + translations |
| PUT | /roles/{role}/modules | Sync the role's full permission matrix |
| GET | /languages | List languages |
| POST | /languages | Create |
| GET | /languages/{id} | Show |
| PUT | /languages/{id} | Update |
| DELETE | /languages/{id} | Delete (rejects default language) |
| PUT | /languages/{id}/default | Mark as default |

## Out of scope

- AdminUser auth (the `admin.auth` middleware is host-defined).
- Admin shell UI pages — the package owns types + API + hooks; hosts own JSX/styling.
- Tenant model — pluggable via `config('access.tenant_model')`.
- Spatie permission package — currently a hard dependency; will become opt-in in PR 5.
