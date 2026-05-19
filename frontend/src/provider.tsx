import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { createRbacApi, type HttpClient, type RbacApi } from './api/rbac.js';

const RbacContext = createContext<RbacApi | null>(null);

interface RbacProviderProps {
  /** Host-app HTTP client (axios instance or anything with the same shape). */
  apiClient: HttpClient;
  children: ReactNode;
}

/**
 * Wraps any subtree that uses the @casamento/admin-rbac hooks. Inject the
 * host app's HTTP client so the package never owns base URL or auth headers.
 */
export function RbacProvider({ apiClient, children }: RbacProviderProps) {
  const api = useMemo(() => createRbacApi(apiClient), [apiClient]);
  return <RbacContext.Provider value={api}>{children}</RbacContext.Provider>;
}

/**
 * Access the RBAC HTTP client from inside a `<RbacProvider>` subtree.
 * Throws a clear error if used outside the provider.
 */
export function useRbacApi(): RbacApi {
  const api = useContext(RbacContext);
  if (!api) {
    throw new Error(
      '[@casamento/admin-rbac] useRbacApi() must be called inside <RbacProvider>.',
    );
  }
  return api;
}
