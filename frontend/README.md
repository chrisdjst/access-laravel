# @casamento/admin-rbac

React + TypeScript admin UI for the `casamento/rbac` Laravel package. Provides:

- Pages: `ModulesPage`, `RolesPage`, `RbacPage` (permission matrix)
- Hooks: `useRbac` (modules, roles, permission sync)
- API client factory consuming an injected axios instance
- `RbacProvider` + `adminRbacRoutes()` for React Router composition

## Status

WIP — package skeleton only. Pages/hooks land in PR 4.

## Install (host app)

```json
// host app frontend/package.json
"dependencies": {
  "@casamento/admin-rbac": "file:../../modularize/frontend"
}
```

```bash
# Build the lib once so `dist/` exists
cd ../../modularize/frontend
npm install
npm run build

# Then install in the host
cd -          # back to host frontend
npm install
```

### Hot-reload during development (recommended)

```bash
cd C:\workspace\modularize\frontend
npm link
cd C:\workspace\casamento\frontend
npm link @casamento/admin-rbac
```

Now changes in `modularize/frontend/src/` appear in the host app without rebuilding (after the watcher rebuilds `dist/`).

## Usage

```tsx
import { RbacProvider, adminRbacRoutes } from '@casamento/admin-rbac';
import { apiClient } from '@/lib/api/client';

// In your route config:
<RbacProvider apiClient={apiClient}>
  <AdminLayout>
    {adminRbacRoutes()}
  </AdminLayout>
</RbacProvider>
```
