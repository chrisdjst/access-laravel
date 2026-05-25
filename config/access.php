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
    | Middleware stack applied to all package routes. The v2.0 default is
    | conservative: just the `api` group + Sanctum auth. Hosts that need a
    | custom admin alias (e.g. one that sets a Spatie team context) add it
    | here.
    |
    | Pre-v2 default included `admin.auth`. That alias is now host-defined
    | and OPT-IN — see the upgrade guide in CHANGELOG.md.
    */
    'middleware' => ['api', 'auth:sanctum'],

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
    | Allowed locales
    |--------------------------------------------------------------------------
    |
    | Optional whitelist consulted by `StoreLanguageRequest` and
    | `UpdateLanguageRequest`. When set to a non-empty list, language `code`
    | submissions must match one of the entries. Leave empty / unset to
    | accept any BCP-47-ish code that survives the LanguageCode VO.
    |
    | Example: ['pt_BR', 'en', 'es', 'fr']
    */
    'allowed_locales' => [],

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

        /*
        | When the audit listener fails to persist an entry (DB
        | unavailable, serialization quirk, etc.), the main domain
        | flow still completes — auditing is best-effort. The level
        | at which the failure lands in the Laravel log is
        | configurable here:
        |
        |   'warning' (default), 'error', 'critical', etc — any
        |       Monolog level.
        |   false  → swallow silently (do NOT log the failure).
        */
        'log_failures' => 'warning',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Per-user throttle applied to the package's expensive write
    | endpoints (POST/DELETE /modules/bulk, POST /roles/{id}/clone,
    | POST /roles/{id}/users/bulk).
    |
    |   bulk — "{max_attempts},{per_minutes}" string. Default 10/min.
    |          Set to null to disable the throttle entirely (the
    |          AccessServiceProvider registers a no-op limiter then).
    */
    'rate_limit' => [
        'bulk' => '10,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Read cache (CachedLanguageRepository / CachedModuleRepository)
    |--------------------------------------------------------------------------
    |
    | Decorate the language + module repositories with an in-process
    | read cache backed by Laravel's `Cache` contract. Writes through
    | the use-cases automatically bump a version key, so cached reads
    | stay coherent without explicit per-key flushes.
    |
    |   enabled — turn the layer on/off. Defaults to true.
    |   store   — Laravel cache store name (file/redis/etc). null = default.
    |   ttl     — TTL in seconds for individual cache entries.
    |             Default 3600 = 1 hour. Long-lived entries are fine
    |             since version-key invalidation already invalidates
    |             on writes; the TTL just bounds the orphan footprint.
    */
    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission inheritance via module hierarchy
    |--------------------------------------------------------------------------
    |
    | When enabled, `$user->can('events.weddings.view')` walks the module
    | tree upward: if a role has `view` on `events.weddings` directly the
    | answer is yes; if not but the role has `view` on the parent `events`
    | the answer is still yes. Defaults to `false` to preserve v2.0/v2.1
    | semantics where a binding must live on the requested module itself.
    |
    | Opt in by setting to `true` once your module hierarchy + role
    | bindings reflect the inheritance you expect — there is no undoing
    | accidental grants other than reviewing role bindings.
    */
    'inheritance' => [
        'enabled' => false,
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
