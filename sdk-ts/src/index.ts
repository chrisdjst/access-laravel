/**
 * @modularize-rbac/sdk-ts — typed TypeScript client for
 * modularize-rbac/laravel's admin API, generated from openapi.json.
 *
 * Default surface:
 *
 *   import { createClient } from '@modularize-rbac/sdk-ts';
 *   import type { paths } from '@modularize-rbac/sdk-ts/types';
 *
 *   const client = createClient({ baseUrl: 'https://app.test/api/admin' });
 *   const { data, error } = await client.GET('/roles', { params: { query: { limit: 10 } } });
 *
 * The client returned by `createClient()` is an `openapi-fetch` instance
 * pre-configured with sensible defaults:
 *
 *   - Adds `Accept: application/json` to every request.
 *   - Adds `Access-Api-Version: 1` so the host can log spec-version mismatch.
 *   - Caller can pass `headers`, a custom `fetch`, or any other openapi-fetch
 *     options as a second argument.
 *
 * Error handling: openapi-fetch returns `{ data, error }`. `error` is the
 * decoded 4xx/5xx body shaped according to the spec's Error schema.
 */

import createOpenApiFetch, { type ClientOptions } from 'openapi-fetch';

import type { paths } from './types.js';

export type { paths } from './types.js';
export type { components } from './types.js';

export interface CreateClientOptions extends Omit<ClientOptions, 'baseUrl'> {
  /**
   * Base URL the client prepends to every request — usually
   * `https://your-host/api/admin` (matches the package default of
   * `config('access.route_prefix') = 'api/admin'`).
   */
  baseUrl: string;
}

export function createClient(options: CreateClientOptions) {
  const { baseUrl, headers = {}, ...rest } = options;

  return createOpenApiFetch<paths>({
    baseUrl,
    headers: {
      Accept: 'application/json',
      'Access-Api-Version': '1',
      ...headers,
    },
    ...rest,
  });
}
