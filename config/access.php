<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    |
    | All package routes are registered under this URL prefix. Defaults to
    | 'api/admin' so endpoints look like /api/admin/modules. Override in
    | the host app's published config to fit a different layout.
    */
    'route_prefix' => 'api/admin',

    /*
    |--------------------------------------------------------------------------
    | Route middleware
    |--------------------------------------------------------------------------
    |
    | Middleware stack applied to all package routes. Default includes the
    | `api` group (Laravel's stateless API stack: throttle, substitute
    | bindings) plus `auth:sanctum` and `admin.auth` (host-defined alias
    | that resolves the admin user and sets the Spatie team context).
    */
    'middleware' => ['api', 'auth:sanctum', 'admin.auth'],

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    |
    | The Spatie permission guard used by Role/Permission models. Must
    | match an entry in the host app's config/auth.php guards list.
    */
    'guard_name' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | Tenant model
    |--------------------------------------------------------------------------
    |
    | Class name of the model that owns roles (e.g. Organization, Account,
    | Workspace). Null = roles are global (single-tenant setup).
    | The roles table carries a column whose name lives in `tenant_column`.
    */
    'tenant_model' => null,

    'tenant_column' => 'organization_id',

    /*
    |--------------------------------------------------------------------------
    | Admin team id
    |--------------------------------------------------------------------------
    |
    | Sentinel UUID used by the admin middleware to scope permission checks
    | to "global admin" (not tied to any tenant). Spatie's teams feature
    | requires a non-null team id; this is the conventional zero-uuid.
    */
    'admin_team_id' => '00000000-0000-0000-0000-000000000000',

    /*
    |--------------------------------------------------------------------------
    | Translations
    |--------------------------------------------------------------------------
    |
    | When true, Module + Role names/descriptions can be translated via the
    | package's Language + Translation tables. Set false to drop the i18n
    | layer (models will fall back to their canonical column values).
    */
    'translations_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Spatie permission sync
    |--------------------------------------------------------------------------
    |
    | v1.0 still requires spatie/laravel-permission because Role + Permission
    | Eloquent models extend Spatie's. This flag controls whether the
    | SyncRoleModules use-case actively replicates grants/revokes into
    | Spatie's `role_has_permissions` table.
    |
    |   null  -> auto (sync enabled if Spatie classes are available)
    |   true  -> force sync on
    |   false -> force sync off (use NullExternalPermissionGateway)
    |
    | Fully decoupling Role from SpatieRole is planned for v2.0; until then
    | uninstalling Spatie is not supported.
    */
    'spatie' => [
        'enabled' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    |
    | Auto-record every domain event the package dispatches into the
    | `access_audit_log` table. Useful for compliance / forensics:
    | every module create/update/delete, every role permission sync,
    | every default-language swap leaves a trail with actor + tenant
    | + payload.
    |
    | Hosts that don't need it can disable to skip the persistence
    | step on every use-case. The audit table can also be queried
    | through `GET /api/admin/audit` (admin.audit.view ability).
    */
    'audit' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    |
    | Policy class registered as `Gate::before` for the package's
    | admin.* abilities. Default {@see \ModularizeRbac\Laravel\Authorization\AccessAdminPolicy}
    | delegates to the user's canAccess() (provided by the
    | HasAccessPermissions trait). Hosts can point this at their own
    | class for a custom mapping, or set to null to opt out entirely
    | and wire abilities by hand via Gate::define().
    */
    'policies' => [
        'admin' => \ModularizeRbac\Laravel\Authorization\AccessAdminPolicy::class,
    ],
];
