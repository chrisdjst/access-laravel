import '@radix-ui/themes/styles.css';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Theme } from '@radix-ui/themes';
import { initialize, mswLoader } from 'msw-storybook-addon';
import { useMemo, type ReactNode } from 'react';
import type { Preview } from '@storybook/react';

import { createRbacApi } from '../src/api/rbac.js';
import { RbacProvider } from '../src/provider.js';
import { defaultHandlers } from './msw-handlers.js';

// Boot msw once for every story. The defaultHandlers cover the
// happy path; individual stories pass their own via
// parameters.msw.handlers to simulate empty / loading / error states.
initialize({ onUnhandledRequest: 'warn' });

const StoryShell = ({ children }: { children: ReactNode }) => {
  const queryClient = useMemo(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: { retry: false, gcTime: 0, staleTime: 0 },
          mutations: { retry: false },
        },
      }),
    [],
  );

  const apiClient = useMemo(
    () => createRbacApi({ baseUrl: 'http://app.test/api/admin' }),
    [],
  );

  return (
    <Theme accentColor="indigo" radius="medium" appearance="light">
      <QueryClientProvider client={queryClient}>
        <RbacProvider apiClient={apiClient}>{children}</RbacProvider>
      </QueryClientProvider>
    </Theme>
  );
};

const preview: Preview = {
  parameters: {
    layout: 'padded',
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
    msw: {
      handlers: defaultHandlers,
    },
  },
  loaders: [mswLoader],
  decorators: [
    (Story) => (
      <StoryShell>
        <Story />
      </StoryShell>
    ),
  ],
};

export default preview;
