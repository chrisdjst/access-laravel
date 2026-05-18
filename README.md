# casamento/rbac

Modules + sub-modules + roles + permissions management package for Laravel apps. Extracted from the casamento platform; reusable across projects that need a Spatie-permission-backed admin RBAC layer with a translatable module tree.

## What's inside

**Backend (`casamento/rbac` Composer package)**:
- Models: `Module`, `ModulePermission`, `ModulePrice`, `RoleModulePermission`, `Role` (extends Spatie), `Permission` (extends Spatie), `Language`, `Translation`.
- Controllers + routes: `GET/POST/PUT/DELETE /admin/modules`, `/admin/roles`, `/admin/languages`, plus `PUT /admin/roles/{id}/modules` for the permission matrix.
- FormRequests, JsonResources, observer that mirrors the custom `role_module_permission` pivot into Spatie's `role_has_permissions`.
- 9 migrations covering Spatie tables + modules/permissions/languages/translations. Table names unchanged.
- Concerns: `HasUuid`, `HasTranslations`.

**Frontend (`@casamento/admin-rbac` NPM package)**:
- TypeScript types: `AdminModule`, `AdminRole`, `AdminLanguage`, etc.
- `createRbacApi(httpClient)` factory — takes anything with the right shape (axios fits).
- `<RbacProvider apiClient={...}>` + `useRbacApi()` hook.
- React Query hooks: `useAdminModules`, `useUpdateModule`, `useAdminRoles`, `useSyncRoleModules`, `useAdminLanguages`, language CRUD. Mutations accept optional callbacks for toast wiring.

## Layout

```
.
├── composer.json           # PHP package manifest (version 0.1.0)
├── config/rbac.php         # publishable config
├── database/migrations/    # 9 migrations
├── routes/api.php          # module + role + language routes
├── src/
│   ├── Concerns/           # HasUuid, HasTranslations traits
│   ├── Contracts/          # Tenant marker interface
│   ├── Http/{Controllers,Requests,Resources}/
│   ├── Models/
│   ├── Observers/
│   └── RbacServiceProvider.php
├── tests/
└── frontend/               # NPM package @casamento/admin-rbac
    ├── package.json
    ├── src/
    │   ├── api/rbac.ts     # createRbacApi(httpClient)
    │   ├── hooks/useRbac.ts
    │   ├── provider.tsx
    │   ├── types/index.ts
    │   ├── version.ts
    │   └── index.ts
    └── README.md
```

## Backend install (host Laravel app)

In the host's `composer.json`:

```json
"require": {
    "casamento/rbac": "^0.1.0"
},
"repositories": [
    { "type": "path", "url": "../modularize", "options": { "symlink": true } }
]
```

Then:

```bash
composer require casamento/rbac:^0.1.0
php artisan vendor:publish --tag=rbac-config
php artisan migrate
```

Edit `config/rbac.php` and point `tenant_model` at your tenant class (e.g. `App\Models\Organization::class`) or leave `null` for single-tenant setups. Roles call `->tenant()` to resolve their owner.

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
2. Sets the Spatie team context: `setPermissionsTeamId(config('rbac.admin_team_id'))`.

### config/permission.php

To use the package's `Role` + `Permission` instead of Spatie's defaults:

```php
'models' => [
    'permission' => \Casamento\Rbac\Models\Permission::class,
    'role' => \Casamento\Rbac\Models\Role::class,
],
```

### Windows note

`"symlink": true` requires Developer Mode (Settings → Privacy & Security → For developers). Without it, Composer falls back to copying — every change in `../modularize` requires `composer update casamento/rbac` in the host app.

## Frontend install

See `frontend/README.md`.

## Routes registered

All under `config('rbac.route_prefix')` (default `api/admin`):

| Method | URL | Action |
|---|---|---|
| GET | /modules | List modules + sub-modules |
| POST | /modules | Create module |
| GET | /modules/{id} | Show one |
| PUT | /modules/{id} | Update (incl. translations) |
| DELETE | /modules/{id} | Soft delete |
| GET | /roles | List roles (filter by guard, organization_id) |
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

The package intentionally does NOT ship:
- AdminUser auth (the `admin.auth` middleware is host-defined).
- Admin shell UI pages — the package owns types + API + hooks; hosts own JSX/styling. Page extraction would couple the lib to a specific design system.
- Tenant model — pluggable via `config('rbac.tenant_model')`.
- Spatie permission package itself — declared as `require` so host already has it; if the host shares a different version, align there.
