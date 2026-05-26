# @modularize-rbac/admin-react

React hooks + admin components for [`modularize-rbac/laravel`](https://github.com/chrisdjst/access-laravel). Pre-built primitives (Roles editor, Modules tree, Audit viewer, Languages admin, AccessGuard) plus the underlying React Query hooks if you want to roll your own UI.

```bash
npm i @modularize-rbac/admin-react @modularize-rbac/sdk-ts
```

## What's inside

```ts
// API client + types — re-exported from @modularize-rbac/sdk-ts.
import type { paths, components } from '@modularize-rbac/admin-react';

// React provider + hook (wraps the sdk-ts client + React Query).
import { RbacProvider, useRbacApi } from '@modularize-rbac/admin-react';

// React Query hooks
import {
  useAdminModules,
  useUpdateModule,
  useAdminRoles,
  useAdminRole,
  useUpdateRole,
  useSyncRoleModules,
  useAdminLanguages,
  useCreateLanguage,
  useUpdateLanguage,
  useDeleteLanguage,
  useSetDefaultLanguage,
} from '@modularize-rbac/admin-react';

// Reference components (ship pre-built):
import { AuditViewer } from '@modularize-rbac/admin-react';
// RolesPage, ModulesTreeEditor, LanguagesAdmin, AccessGuard ship in subsequent 0.x releases.
```

## Setup

```tsx
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RbacProvider } from '@modularize-rbac/admin-react';
import { createClient } from '@modularize-rbac/sdk-ts';

const queryClient = new QueryClient();
const apiClient = createClient({
  baseUrl: 'https://app.test/api/admin',
  headers: { Authorization: `Bearer ${token}` },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <RbacProvider apiClient={apiClient}>
        {/* your routes */}
      </RbacProvider>
    </QueryClientProvider>
  );
}
```

## Reference components

### `<AuditViewer />`

Paginated viewer for the audit log with filter controls (event / actor / tenant / time window) and per-row expandable JSON payload. Rows that participate in the v2.7 hash chain get a green "chain ok" badge. `[REDACTED]` markers in the payload (from the PII redaction layer) are highlighted in amber so reviewers can tell at a glance what was scrubbed.

```tsx
import { AuditViewer } from '@modularize-rbac/admin-react';

export function AdminAuditRoute() {
  return <AuditViewer limit={50} />;
}
```

The default event dropdown lists the standard domain events; pass `labels.filters.event` (or override with a custom select on top) to expose application-specific event names.

## Using hooks

```tsx
import { useAdminModules } from '@modularize-rbac/admin-react';

export function ModulesPage() {
  const { data, isLoading } = useAdminModules();

  if (isLoading) return <p>Loading…</p>;
  return <ul>{data?.map((m) => <li key={m.id}>{m.name}</li>)}</ul>;
}
```

## Peer deps

- `react ^18 || ^19`
- `@tanstack/react-query ^5`
- `@modularize-rbac/sdk-ts ^0.1`

## Migrating from `@casamento/admin-rbac`

This package is the renamed continuation of `@casamento/admin-rbac` v0.1.0. The legacy name is unmaintained.

```diff
- import { useAdminModules } from '@casamento/admin-rbac';
+ import { useAdminModules } from '@modularize-rbac/admin-react';
```

Types changed shape too — hand-rolled `AdminModule` / `AdminRole` are gone. Pull the equivalent type out of the spec via `@modularize-rbac/sdk-ts` instead:

```diff
- import type { AdminModule } from '@casamento/admin-rbac';
+ import type { components } from '@modularize-rbac/admin-react';
+ type AdminModule = components['schemas']['Module'];
```

## Build

```bash
npm run build    # tsc -p tsconfig.build.json
npm run dev      # watch mode
```

Outputs `dist/index.js` + `dist/index.d.ts` with sourcemaps and declaration maps.

## Storybook

Every component ships with a Storybook story documenting its props + at least one usage scenario. Run locally:

```bash
npm run storybook        # http://localhost:6006
npm run build-storybook  # static build → storybook-static/
```

Stories run against `msw` mock handlers (`.storybook/msw-handlers.ts`) so they work fully offline. Components are wrapped in:

- `@radix-ui/themes` (light, indigo accent, medium radius).
- A fresh `QueryClient` per story (no cross-story cache pollution).
- `RbacProvider` built on `@modularize-rbac/sdk-ts`.

## Testing

```bash
npm test               # vitest run
npm run test:watch     # watch mode
npm run test:coverage  # vitest run --coverage
```

Tests use Vitest + msw via `setupServer` in Node. Coverage thresholds: 80% statements / lines, 70% branches, 80% functions.
