# @casamento/admin-rbac

TypeScript types + API client factory + React Query hooks for the `casamento/rbac` Laravel package. No JSX pages — the host owns UI.

## What's inside

```ts
// Types
import type { AdminModule, AdminRole, AdminLanguage, RoleModuleEntry } from '@casamento/admin-rbac';

// HTTP factory (HttpClient = anything with get/post/put/delete returning { data: T })
import { createRbacApi, type RbacApi, type HttpClient } from '@casamento/admin-rbac';

// React provider + hook
import { RbacProvider, useRbacApi } from '@casamento/admin-rbac';

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
} from '@casamento/admin-rbac';
```

## Install (host app)

```jsonc
// host app frontend/package.json
"dependencies": {
  "@casamento/admin-rbac": "file:../../modularize/frontend"
}
```

```bash
cd C:/workspace/modularize/frontend
npm install
npm run build   # generates dist/

cd C:/workspace/casamento/frontend
npm install --legacy-peer-deps
```

### Hot-reload during development (recommended)

```bash
cd C:/workspace/modularize/frontend
npm link
cd C:/workspace/casamento/frontend
npm link @casamento/admin-rbac
# in modularize/frontend, run `npm run dev` to watch tsc rebuilds
```

## Setup

```tsx
// host App.tsx
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RbacProvider } from '@casamento/admin-rbac';
import apiClient from './lib/api/client'; // your axios instance

const queryClient = new QueryClient();

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <RbacProvider apiClient={apiClient}>
        {/* rest of app */}
      </RbacProvider>
    </QueryClientProvider>
  );
}
```

## Using hooks

```tsx
import { useAdminModules, useUpdateModule } from '@casamento/admin-rbac';
import { toast } from 'sonner';

export function ModulesPage() {
  const { data: modules = [], isLoading } = useAdminModules();
  const update = useUpdateModule({
    onSuccessMessage: (m) => toast.success(m),
    onErrorMessage: (m) => toast.error(m),
  });

  return /* your UI */;
}
```

## Peer deps

- `react ^18 || ^19`
- `@tanstack/react-query ^5`

The package does NOT import axios — it accepts any `HttpClient` shape, so use axios, ky, or a fetch wrapper.

## Build

```bash
npm run build    # tsc -p tsconfig.build.json
npm run dev      # watch mode
```

Outputs `dist/index.js` + `dist/index.d.ts` with sourcemaps and declaration maps.
