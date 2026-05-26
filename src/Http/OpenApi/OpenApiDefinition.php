<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Central holder for the package's OpenAPI annotations.
 *
 * All `#[OA\*]` attributes for the package's public REST surface live
 * here rather than being spread across the controllers. Rationale:
 *
 *   1. Controllers stay readable — no 200-line attribute prefaces.
 *   2. `php artisan access:openapi` scans only this one file, which is
 *      fast and predictable.
 *   3. A breaking response shape change requires editing exactly one
 *      place + bumping `AddApiVersionHeader::API_VERSION`.
 *
 * The spec covers the routes that live under
 * `config('access.route_prefix')`. Path parameters use UUID format.
 */
#[OA\OpenApi(
    openapi: '3.1.0',
    info: new OA\Info(
        version: '1.0.0',
        title: 'modularize-rbac/laravel admin API',
        description: 'REST API for the modularize-rbac/laravel admin panel. Every response carries an `Access-Api-Version` header.',
    ),
    servers: [
        new OA\Server(url: '/api/admin', description: 'Default route prefix'),
    ],
    tags: [
        new OA\Tag(name: 'modules', description: 'Catalog of feature modules.'),
        new OA\Tag(name: 'roles', description: 'Roles + their permission matrices.'),
        new OA\Tag(name: 'languages', description: 'Languages + the default-language flag.'),
        new OA\Tag(name: 'audit', description: 'Audit log query.'),
        new OA\Tag(name: 'users', description: 'User-facing read endpoints.'),
    ],
)]
#[OA\Schema(
    schema: 'Module',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'icon', type: 'string', nullable: true),
        new OA\Property(property: 'redirect', type: 'string', nullable: true),
        new OA\Property(property: 'root_module_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'sort_order', type: 'integer'),
        new OA\Property(property: 'is_active', type: 'boolean'),
        new OA\Property(property: 'translations', type: 'object'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Role',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'display_name', type: 'string', nullable: true),
        new OA\Property(property: 'guard_name', type: 'string'),
        new OA\Property(property: 'level', type: 'integer'),
        new OA\Property(property: 'is_system', type: 'boolean'),
        new OA\Property(property: 'parent_role_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'organization_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'translations', type: 'object'),
        new OA\Property(property: 'modules', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Language',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'is_default', type: 'boolean'),
        new OA\Property(property: 'is_active', type: 'boolean'),
    ],
)]
#[OA\Schema(
    schema: 'PaginatedMeta',
    properties: [
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'limit', type: 'integer'),
        new OA\Property(property: 'offset', type: 'integer'),
    ],
)]
#[OA\Schema(
    schema: 'Error',
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'error_type', type: 'string'),
        new OA\Property(property: 'errors', type: 'object', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'AuditEntry',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'event_name', type: 'string'),
        new OA\Property(property: 'actor_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'tenant_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'payload', type: 'object'),
        new OA\Property(property: 'occurred_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'entry_hash', type: 'string', nullable: true, description: 'sha256 hex of previous_hash || canonical(this). Present only when access.audit.hash_chain.enabled is true.'),
        new OA\Property(property: 'previous_hash', type: 'string', nullable: true),
    ],
)]
final class OpenApiDefinition
{
    // Module endpoints -----------------------------------------------

    #[OA\Get(
        path: '/modules',
        operationId: 'modules.index',
        tags: ['modules'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 1000)),
            new OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0)),
            new OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'root_module_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'slug_like', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Module')),
                new OA\Property(property: 'meta', oneOf: [
                    new OA\Schema(ref: '#/components/schemas/PaginatedMeta'),
                    new OA\Schema(properties: [new OA\Property(property: 'count', type: 'integer')]),
                ]),
            ])),
            new OA\Response(response: 422, description: 'Invalid query parameter', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function listModules(): void {}

    #[OA\Post(
        path: '/modules',
        operationId: 'modules.store',
        tags: ['modules'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'slug', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'redirect', type: 'string', nullable: true),
            new OA\Property(property: 'icon', type: 'string', nullable: true),
            new OA\Property(property: 'root_module_id', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'sort_order', type: 'integer'),
            new OA\Property(property: 'is_active', type: 'boolean'),
            new OA\Property(property: 'translations', type: 'object'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Module'),
            ])),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function storeModule(): void {}

    #[OA\Post(
        path: '/modules/bulk',
        operationId: 'modules.bulkStore',
        tags: ['modules'],
        description: 'Subject to the `access-bulk` rate limiter (default 10/min/user).',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'modules', type: 'array', items: new OA\Items(type: 'object')),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 429, description: 'Rate limited'),
        ],
    )]
    public function bulkStoreModules(): void {}

    #[OA\Delete(
        path: '/modules/bulk',
        operationId: 'modules.bulkDestroy',
        tags: ['modules'],
        description: 'Subject to the `access-bulk` rate limiter.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
        ])),
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 404, description: 'One or more ids missing'),
            new OA\Response(response: 429, description: 'Rate limited'),
        ],
    )]
    public function bulkDestroyModules(): void {}

    // Role endpoints -------------------------------------------------

    #[OA\Get(
        path: '/roles',
        operationId: 'roles.index',
        tags: ['roles'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 1000)),
            new OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0)),
            new OA\Parameter(name: 'guard', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'organization_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'is_system', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'level_min', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0)),
            new OA\Parameter(name: 'level_max', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0)),
            new OA\Parameter(name: 'has_parent', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 422, description: 'Invalid query parameter'),
        ],
    )]
    public function listRoles(): void {}

    #[OA\Post(
        path: '/roles',
        operationId: 'roles.store',
        tags: ['roles'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'display_name', type: 'string', nullable: true),
            new OA\Property(property: 'guard_name', type: 'string'),
            new OA\Property(property: 'organization_id', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'level', type: 'integer'),
            new OA\Property(property: 'is_system', type: 'boolean'),
            new OA\Property(property: 'parent_role_id', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'translations', type: 'object'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Role'),
            ])),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function storeRole(): void {}

    #[OA\Post(
        path: '/roles/{role}/clone',
        operationId: 'roles.clone',
        tags: ['roles'],
        description: 'Subject to the `access-bulk` rate limiter.',
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'display_name', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 404, description: 'Source role not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 429, description: 'Rate limited'),
        ],
    )]
    public function cloneRole(): void {}

    #[OA\Post(
        path: '/roles/{role}/users/bulk',
        operationId: 'roles.bulkAssignUsers',
        tags: ['roles'],
        description: 'Subject to the `access-bulk` rate limiter. Idempotent.',
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'user_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
            new OA\Property(property: 'organization_id', type: 'string', format: 'uuid', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 429, description: 'Rate limited'),
        ],
    )]
    public function bulkAssignUsers(): void {}

    // Audit endpoint -------------------------------------------------

    #[OA\Get(
        path: '/audit',
        operationId: 'audit.index',
        tags: ['audit'],
        parameters: [
            new OA\Parameter(name: 'event', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'actor_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'tenant_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'since', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'until', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditEntry')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginatedMeta'),
            ])),
        ],
    )]
    public function listAudit(): void {}

    // Per-module endpoints ------------------------------------------

    #[OA\Get(
        path: '/modules/{id}',
        operationId: 'modules.show',
        tags: ['modules'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Module'),
            ])),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function showModule(): void {}

    #[OA\Put(
        path: '/modules/{id}',
        operationId: 'modules.update',
        tags: ['modules'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'redirect', type: 'string', nullable: true),
            new OA\Property(property: 'icon', type: 'string', nullable: true),
            new OA\Property(property: 'root_module_id', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'sort_order', type: 'integer'),
            new OA\Property(property: 'is_active', type: 'boolean'),
            new OA\Property(property: 'translations', type: 'object'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Module'),
            ])),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function updateModule(): void {}

    #[OA\Delete(
        path: '/modules/{id}',
        operationId: 'modules.destroy',
        tags: ['modules'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function destroyModule(): void {}

    // Per-role endpoints --------------------------------------------

    #[OA\Get(
        path: '/roles/{role}',
        operationId: 'roles.show',
        tags: ['roles'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Role'),
            ])),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function showRole(): void {}

    #[OA\Put(
        path: '/roles/{role}',
        operationId: 'roles.update',
        tags: ['roles'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'display_name', type: 'string', nullable: true),
            new OA\Property(property: 'translations', type: 'object'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Role'),
            ])),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function updateRole(): void {}

    #[OA\Delete(
        path: '/roles/{role}',
        operationId: 'roles.destroy',
        tags: ['roles'],
        description: 'Soft-delete since v2.8. Restore via POST /roles/{role}/restore.',
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Has bindings or is a system role'),
        ],
    )]
    public function destroyRole(): void {}

    #[OA\Post(
        path: '/roles/{role}/restore',
        operationId: 'roles.restore',
        tags: ['roles'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Role'),
            ])),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Role is not soft-deleted'),
        ],
    )]
    public function restoreRole(): void {}

    #[OA\Put(
        path: '/roles/{role}/modules',
        operationId: 'roles.syncModules',
        tags: ['roles'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'modules', type: 'array', items: new OA\Items(type: 'object', properties: [
                new OA\Property(property: 'module_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'is_listing_allowed', type: 'boolean'),
                new OA\Property(property: 'is_reading_allowed', type: 'boolean'),
                new OA\Property(property: 'is_writing_allowed', type: 'boolean'),
                new OA\Property(property: 'is_editing_allowed', type: 'boolean'),
                new OA\Property(property: 'is_delete_allowed', type: 'boolean'),
            ])),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Role'),
            ])),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function syncRoleModules(): void {}

    #[OA\Get(
        path: '/roles/{role}/permission-matrix',
        operationId: 'roles.permissionMatrix',
        tags: ['roles'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function rolePermissionMatrix(): void {}

    #[OA\Get(
        path: '/roles/{role}/bindings/history',
        operationId: 'roles.bindingsHistory',
        tags: ['roles'],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'since', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'module_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ],
    )]
    public function roleBindingsHistory(): void {}

    // User endpoints -------------------------------------------------

    #[OA\Get(
        path: '/users/{user}/accessible-modules',
        operationId: 'users.accessibleModules',
        tags: ['users'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ],
    )]
    public function userAccessibleModules(): void {}

    // Languages endpoints -------------------------------------------

    #[OA\Get(
        path: '/languages',
        operationId: 'languages.index',
        tags: ['languages'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Language')),
            ])),
        ],
    )]
    public function listLanguages(): void {}

    #[OA\Post(
        path: '/languages',
        operationId: 'languages.store',
        tags: ['languages'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'is_default', type: 'boolean'),
            new OA\Property(property: 'is_active', type: 'boolean'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Language'),
            ])),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function storeLanguage(): void {}

    #[OA\Get(
        path: '/languages/{id}',
        operationId: 'languages.show',
        tags: ['languages'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Language'),
            ])),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function showLanguage(): void {}

    #[OA\Put(
        path: '/languages/{id}',
        operationId: 'languages.update',
        tags: ['languages'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'is_default', type: 'boolean'),
            new OA\Property(property: 'is_active', type: 'boolean'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Language'),
            ])),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function updateLanguage(): void {}

    #[OA\Delete(
        path: '/languages/{id}',
        operationId: 'languages.destroy',
        tags: ['languages'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Default language cannot be deleted'),
        ],
    )]
    public function destroyLanguage(): void {}

    #[OA\Put(
        path: '/languages/{id}/default',
        operationId: 'languages.setDefault',
        tags: ['languages'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Language'),
            ])),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function setDefaultLanguage(): void {}
}
