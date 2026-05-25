import { createClient, type CreateClientOptions, type paths } from '@modularize-rbac/sdk-ts';

/**
 * The runtime client used by every hook. Type derived from the sdk-ts
 * `createClient` return value — that's an openapi-fetch instance typed
 * against the spec's `paths` object.
 */
export type RbacApi = ReturnType<typeof createClient>;

/**
 * Factory that builds the sdk-ts client. Re-exported under the
 * @modularize-rbac/admin-react surface so hosts that only want hooks
 * don't have to take an explicit dep on @modularize-rbac/sdk-ts at
 * the import level (they still install it as a peer dep, but the
 * surface is one package import).
 */
export function createRbacApi(options: CreateClientOptions): RbacApi {
  return createClient(options);
}

/**
 * Spec-derived response payload types reused across hooks + components.
 * Hosts can pull anything from `paths` / `components` directly if they
 * need a less-common shape.
 */
export type ModulesIndexResponse = paths['/modules']['get']['responses']['200']['content']['application/json'];
// `GET /roles` and `GET /audit` are typed at the operation level but
// their 200 response doesn't carry an explicit content schema in the
// current spec — hosts wanting strong types pull
// `paths['/roles']['get']['responses']['200']` directly.

// Re-export the sdk-ts top-level types so hosts importing
// `@modularize-rbac/admin-react` get access to the spec without
// taking the sdk-ts package name in their imports.
export type { paths, components, CreateClientOptions } from '@modularize-rbac/sdk-ts';
