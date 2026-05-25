import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, type RenderHookOptions } from '@testing-library/react';
import { type ReactNode } from 'react';

import { createRbacApi } from '../src/api/rbac.js';
import { RbacProvider } from '../src/provider.js';

/**
 * Wraps `renderHook` from RTL with the QueryClientProvider and
 * RbacProvider every hook needs. Each call gets a fresh QueryClient
 * so cache state from one test doesn't leak into another.
 */
export function renderHookWithProviders<TResult, TProps>(
  callback: (props: TProps) => TResult,
  options?: Omit<RenderHookOptions<TProps>, 'wrapper'>,
) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
      mutations: { retry: false },
    },
  });

  const apiClient = createRbacApi({ baseUrl: 'http://app.test/api/admin' });

  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <RbacProvider apiClient={apiClient}>{children}</RbacProvider>
    </QueryClientProvider>
  );

  return renderHook(callback, { wrapper, ...options });
}
