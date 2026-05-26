/**
 * AUTO-GENERATED — do not edit by hand.
 *
 * Source: ../openapi.json
 * Run    `npm run generate` (inside sdk-ts/) to regenerate.
 *
 * The CI workflow .github/workflows/sdk-ts-drift.yml fails on PRs
 * that change openapi.json without regenerating this file.
 */

export type paths = {
    readonly "/audit": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["audit.index"];
        readonly put?: never;
        readonly post?: never;
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/languages": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["languages.index"];
        readonly put?: never;
        readonly post: operations["languages.store"];
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/languages/{id}": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["languages.show"];
        readonly put: operations["languages.update"];
        readonly post?: never;
        readonly delete: operations["languages.destroy"];
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/languages/{id}/default": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get?: never;
        readonly put: operations["languages.setDefault"];
        readonly post?: never;
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/modules": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["modules.index"];
        readonly put?: never;
        readonly post: operations["modules.store"];
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/modules/{id}": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["modules.show"];
        readonly put: operations["modules.update"];
        readonly post?: never;
        readonly delete: operations["modules.destroy"];
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/modules/bulk": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get?: never;
        readonly put?: never;
        /** @description Subject to the `access-bulk` rate limiter (default 10/min/user). */
        readonly post: operations["modules.bulkStore"];
        /** @description Subject to the `access-bulk` rate limiter. */
        readonly delete: operations["modules.bulkDestroy"];
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/roles": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["roles.index"];
        readonly put?: never;
        readonly post: operations["roles.store"];
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/roles/{role}": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["roles.show"];
        readonly put: operations["roles.update"];
        readonly post?: never;
        /** @description Soft-delete since v2.8. Restore via POST /roles/{role}/restore. */
        readonly delete: operations["roles.destroy"];
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/roles/{role}/bindings/history": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["roles.bindingsHistory"];
        readonly put?: never;
        readonly post?: never;
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/roles/{role}/clone": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get?: never;
        readonly put?: never;
        /** @description Subject to the `access-bulk` rate limiter. */
        readonly post: operations["roles.clone"];
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/roles/{role}/modules": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get?: never;
        readonly put: operations["roles.syncModules"];
        readonly post?: never;
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/roles/{role}/permission-matrix": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["roles.permissionMatrix"];
        readonly put?: never;
        readonly post?: never;
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/roles/{role}/restore": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get?: never;
        readonly put?: never;
        readonly post: operations["roles.restore"];
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/roles/{role}/users/bulk": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get?: never;
        readonly put?: never;
        /** @description Subject to the `access-bulk` rate limiter. Idempotent. */
        readonly post: operations["roles.bulkAssignUsers"];
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
    readonly "/users/{user}/accessible-modules": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["users.accessibleModules"];
        readonly put?: never;
        readonly post?: never;
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
};
export type webhooks = Record<string, never>;
export type components = {
    schemas: {
        readonly AuditEntry: {
            /** Format: uuid */
            readonly actor_id?: string | null;
            /** @description sha256 hex of previous_hash || canonical(this). Present only when access.audit.hash_chain.enabled is true. */
            readonly entry_hash?: string | null;
            readonly event_name?: string;
            /** Format: uuid */
            readonly id?: string;
            /** Format: date-time */
            readonly occurred_at?: string;
            readonly payload?: Record<string, unknown>;
            readonly previous_hash?: string | null;
            /** Format: uuid */
            readonly tenant_id?: string | null;
        };
        readonly Error: {
            readonly error_type?: string;
            readonly errors?: Record<string, unknown> | null;
            readonly message?: string;
        };
        readonly Language: {
            readonly code?: string;
            /** Format: uuid */
            readonly id?: string;
            readonly is_active?: boolean;
            readonly is_default?: boolean;
            readonly name?: string;
        };
        readonly Module: {
            /** Format: date-time */
            readonly created_at?: string;
            readonly icon?: string | null;
            /**
             * Format: uuid
             * @description Central holder for the package's OpenAPI annotations.
             *
             *     All `#[OA\*]` attributes for the package's public REST surface live
             *     here rather than being spread across the controllers. Rationale:
             *
             *     1. Controllers stay readable — no 200-line attribute prefaces.
             *     2. `php artisan access:openapi` scans only this one file, which is
             *     fast and predictable.
             *     3. A breaking response shape change requires editing exactly one
             *     place + bumping `AddApiVersionHeader::API_VERSION`.
             *
             *     The spec covers the routes that live under
             *     `config('access.route_prefix')`. Path parameters use UUID format.
             */
            readonly id?: string;
            readonly is_active?: boolean;
            readonly name?: string;
            readonly redirect?: string | null;
            /** Format: uuid */
            readonly root_module_id?: string | null;
            readonly slug?: string;
            readonly sort_order?: number;
            readonly translations?: Record<string, unknown>;
            /** Format: date-time */
            readonly updated_at?: string;
        };
        readonly PaginatedMeta: {
            readonly limit?: number;
            readonly offset?: number;
            readonly total?: number;
        };
        readonly Role: {
            /** Format: date-time */
            readonly created_at?: string;
            readonly display_name?: string | null;
            readonly guard_name?: string;
            /** Format: uuid */
            readonly id?: string;
            readonly is_system?: boolean;
            readonly level?: number;
            readonly modules?: readonly Record<string, unknown>[];
            readonly name?: string;
            /** Format: uuid */
            readonly organization_id?: string | null;
            /** Format: uuid */
            readonly parent_role_id?: string | null;
            readonly translations?: Record<string, unknown>;
            /** Format: date-time */
            readonly updated_at?: string;
        };
    };
    responses: never;
    parameters: never;
    requestBodies: never;
    headers: never;
    pathItems: never;
};
export type $defs = Record<string, never>;
export interface operations {
    readonly "audit.index": {
        readonly parameters: {
            readonly query?: {
                readonly actor_id?: string;
                readonly event?: string;
                readonly limit?: number;
                readonly offset?: number;
                readonly since?: string;
                readonly tenant_id?: string;
                readonly until?: string;
            };
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: readonly components["schemas"]["AuditEntry"][];
                        readonly meta?: components["schemas"]["PaginatedMeta"];
                    };
                };
            };
        };
    };
    readonly "languages.index": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: readonly components["schemas"]["Language"][];
                    };
                };
            };
        };
    };
    readonly "languages.store": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly code?: string;
                    readonly is_active?: boolean;
                    readonly is_default?: boolean;
                    readonly name?: string;
                };
            };
        };
        readonly responses: {
            /** @description Created */
            readonly 201: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Language"];
                    };
                };
            };
            /** @description Validation failed */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "languages.show": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly id: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Language"];
                    };
                };
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "languages.update": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly id: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly code?: string;
                    readonly is_active?: boolean;
                    readonly is_default?: boolean;
                    readonly name?: string;
                };
            };
        };
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Language"];
                    };
                };
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Validation failed */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "languages.destroy": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly id: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description No Content */
            readonly 204: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Default language cannot be deleted */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "languages.setDefault": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly id: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Language"];
                    };
                };
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "modules.index": {
        readonly parameters: {
            readonly query?: {
                readonly is_active?: boolean;
                readonly limit?: number;
                readonly offset?: number;
                readonly root_module_id?: string;
                readonly slug_like?: string;
            };
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: readonly components["schemas"]["Module"][];
                        readonly meta?: components["schemas"]["PaginatedMeta"] | {
                            readonly count?: number;
                        };
                    };
                };
            };
            /** @description Invalid query parameter */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    readonly "modules.store": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly icon?: string | null;
                    readonly is_active?: boolean;
                    readonly name?: string;
                    readonly redirect?: string | null;
                    /** Format: uuid */
                    readonly root_module_id?: string | null;
                    readonly slug?: string;
                    readonly sort_order?: number;
                    readonly translations?: Record<string, unknown>;
                };
            };
        };
        readonly responses: {
            /** @description Created */
            readonly 201: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Module"];
                    };
                };
            };
            /** @description Validation failed */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "modules.show": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly id: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Module"];
                    };
                };
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "modules.update": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly id: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly icon?: string | null;
                    readonly is_active?: boolean;
                    readonly name?: string;
                    readonly redirect?: string | null;
                    /** Format: uuid */
                    readonly root_module_id?: string | null;
                    readonly sort_order?: number;
                    readonly translations?: Record<string, unknown>;
                };
            };
        };
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Module"];
                    };
                };
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Validation failed */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "modules.destroy": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly id: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description No Content */
            readonly 204: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "modules.bulkStore": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly modules?: readonly Record<string, unknown>[];
                };
            };
        };
        readonly responses: {
            /** @description Created */
            readonly 201: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Validation failed */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Rate limited */
            readonly 429: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "modules.bulkDestroy": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly ids?: readonly string[];
                };
            };
        };
        readonly responses: {
            /** @description No Content */
            readonly 204: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description One or more ids missing */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Rate limited */
            readonly 429: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.index": {
        readonly parameters: {
            readonly query?: {
                readonly guard?: string;
                readonly has_parent?: boolean;
                readonly is_system?: boolean;
                readonly level_max?: number;
                readonly level_min?: number;
                readonly limit?: number;
                readonly offset?: number;
                readonly organization_id?: string;
            };
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Invalid query parameter */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.store": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly display_name?: string | null;
                    readonly guard_name?: string;
                    readonly is_system?: boolean;
                    readonly level?: number;
                    readonly name?: string;
                    /** Format: uuid */
                    readonly organization_id?: string | null;
                    /** Format: uuid */
                    readonly parent_role_id?: string | null;
                    readonly translations?: Record<string, unknown>;
                };
            };
        };
        readonly responses: {
            /** @description Created */
            readonly 201: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Role"];
                    };
                };
            };
            /** @description Validation failed */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.show": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly role: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Role"];
                    };
                };
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.update": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly role: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly display_name?: string | null;
                    readonly translations?: Record<string, unknown>;
                };
            };
        };
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Role"];
                    };
                };
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Validation failed */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.destroy": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly role: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description No Content */
            readonly 204: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Has bindings or is a system role */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.bindingsHistory": {
        readonly parameters: {
            readonly query?: {
                readonly limit?: number;
                readonly module_id?: string;
                readonly offset?: number;
                readonly since?: string;
            };
            readonly header?: never;
            readonly path: {
                readonly role: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.clone": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly role: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly display_name?: string | null;
                    readonly name?: string;
                };
            };
        };
        readonly responses: {
            /** @description Created */
            readonly 201: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Source role not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Validation failed */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Rate limited */
            readonly 429: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.syncModules": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly role: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    readonly modules?: readonly {
                        readonly is_delete_allowed?: boolean;
                        readonly is_editing_allowed?: boolean;
                        readonly is_listing_allowed?: boolean;
                        readonly is_reading_allowed?: boolean;
                        readonly is_writing_allowed?: boolean;
                        /** Format: uuid */
                        readonly module_id?: string;
                    }[];
                };
            };
        };
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Role"];
                    };
                };
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Validation failed */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.permissionMatrix": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly role: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.restore": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly role: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": {
                        readonly data?: components["schemas"]["Role"];
                    };
                };
            };
            /** @description Not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Role is not soft-deleted */
            readonly 422: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "roles.bulkAssignUsers": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly role: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody: {
            readonly content: {
                readonly "application/json": {
                    /** Format: uuid */
                    readonly organization_id?: string | null;
                    readonly user_ids?: readonly string[];
                };
            };
        };
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Role not found */
            readonly 404: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
            /** @description Rate limited */
            readonly 429: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
    readonly "users.accessibleModules": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path: {
                readonly user: string;
            };
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description OK */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content?: never;
            };
        };
    };
}
