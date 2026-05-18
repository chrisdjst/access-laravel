<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    |
    | All package routes are registered under this prefix. Default 'admin'
    | mirrors the casamento app's /admin/modules, /admin/roles convention.
    */
    'route_prefix' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | Route middleware
    |--------------------------------------------------------------------------
    |
    | Middleware stack applied to all package routes. The host app is
    | expected to provide `admin.auth` (or equivalent) that resolves the
    | admin user and sets the team context.
    */
    'middleware' => ['auth:sanctum', 'admin.auth'],

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
];
