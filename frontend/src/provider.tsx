import { createContext, useContext, type ReactNode } from 'react';

import type { RbacApi } from './api/rbac.js';

const RbacContext = createContext<RbacApi | null>(null);

interface RbacProviderProps {
  /**
   * The sdk-ts client. Build it via `createClient({ baseUrl, headers })`
   * from `@modularize-rbac/sdk-ts`, or via `createRbacApi(...)` re-exported
   * by this package — both return the same thing.
   */
  apiClient: RbacApi;
  children: ReactNode;
}

/**
 * Wraps any subtree that uses the @modularize-rbac/admin-react hooks.
 * The provider holds a typed sdk-ts client; nothing about base URLs or
 * auth lives in the package itself.
 */
export function RbacProvider({ apiClient, children }: RbacProviderProps) {
  return <RbacContext.Provider value={apiClient}>{children}</RbacContext.Provider>;
}

/**
 * Access the RBAC HTTP client from inside a `<RbacProvider>` subtree.
 * Throws a clear error if used outside the provider.
 */
export function useRbacApi(): RbacApi {
  const api = useContext(RbacContext);
  if (!api) {
    throw new Error(
      '[@modularize-rbac/admin-react] useRbacApi() must be called inside <RbacProvider>.',
    );
  }
  return api;
}
