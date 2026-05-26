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
import {
  RolesPage,
  ModulesTreeEditor,
  LanguagesAdmin,
  AuditViewer,
  AccessGuard,
  AccessGuardProvider,
  useHasAbility,
} from '@modularize-rbac/admin-react';
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

### `<RolesPage />`

Drop-in admin page for managing roles. Lists roles paginated, with create / clone / soft-delete / restore actions wired to the matching hooks.

```tsx
import { RolesPage } from '@modularize-rbac/admin-react';

export function AdminRolesRoute() {
  return <RolesPage limit={25} />;
}
```

All visible strings can be overridden for i18n via the `labels` prop (see `RolesPageLabels`). Pass `onRoleSelect` to receive the role id when a row is clicked — wire it up to your own route for the role editor.

### `<ModulesTreeEditor />`

Drag-and-drop tree editor for the module catalog. Built on `@dnd-kit/sortable`: drag a row to reorder siblings (persists `sort_order` via `PUT /modules/{id}`). Per-row Add Child / Edit / Delete + multi-select with bulk-delete bar at the top.

```tsx
import { ModulesTreeEditor } from '@modularize-rbac/admin-react';

export function AdminModulesRoute() {
  return <ModulesTreeEditor onModuleSelect={(id) => navigate(`/admin/modules/${id}`)} />;
}
```

Every label is overridable via the `labels` prop (`ModulesTreeEditorLabels`). The component issues one `PUT /modules/{id}` per row when a drag completes (one per affected sort_order), batched by React Query.

### `<LanguagesAdmin />`

CRUD table for the configured languages with a "set as default" action gated by a confirmation modal (changing the default cascades through translation fallbacks). Default-language rows cannot be deleted — the API itself refuses with a 422 anyway.

```tsx
import { LanguagesAdmin } from '@modularize-rbac/admin-react';

export function AdminLanguagesRoute() {
  return <LanguagesAdmin />;
}
```

All visible strings (including the default-change warning) override via the `labels` prop.

### `<AuditViewer />`

Paginated viewer for the audit log with filter controls (event / actor / tenant / time window) and per-row expandable JSON payload. Rows that participate in the v2.7 hash chain get a green "chain ok" badge. `[REDACTED]` markers in the payload (from the PII redaction layer) are highlighted in amber so reviewers can tell at a glance what was scrubbed.

```tsx
import { AuditViewer } from '@modularize-rbac/admin-react';

export function AdminAuditRoute() {
  return <AuditViewer limit={50} />;
}
```

The default event dropdown lists the standard domain events; pass `labels.filters.event` (or override with a custom select on top) to expose application-specific event names.

### `<AccessGuard />` + `<AccessGuardProvider />`

Conditionally render UI based on the current user's abilities. The package does *not* fetch the ability list — the host already knows what the current user can do (auth response, JWT claims, session). Wrap the relevant subtree in `<AccessGuardProvider abilities={...} />` and nested `<AccessGuard ability="...">` reads it without prop drilling.

```tsx
import { AccessGuard, AccessGuardProvider } from '@modularize-rbac/admin-react';

export default function App() {
  const { abilities } = useCurrentUser(); // host's own auth state

  return (
    <AccessGuardProvider abilities={abilities}>
      <Toolbar />
    </AccessGuardProvider>
  );
}

function Toolbar() {
  return (
    <>
      <AccessGuard ability="events.create"><CreateButton /></AccessGuard>
      <AccessGuard ability="events.delete" fallback={<DisabledHint />}>
        <DeleteButton />
      </AccessGuard>
    </>
  );
}
```

The `useHasAbility(ability, abilities?)` hook is also exported for ad-hoc imperative checks (e.g. inside a route loader).

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
