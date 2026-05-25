# @modularize-rbac/sdk-ts

Typed TypeScript SDK for [`modularize-rbac/laravel`](https://github.com/chrisdjst/access-laravel) generated from the package's `openapi.json`.

```bash
npm i @modularize-rbac/sdk-ts
```

## Quick start

```ts
import { createClient } from '@modularize-rbac/sdk-ts';

const client = createClient({ baseUrl: 'https://app.test/api/admin' });

const { data, error } = await client.GET('/roles', {
  params: { query: { limit: 20, level_min: 50 } },
});

if (error) {
  console.error(error.message);
} else {
  console.log(data.data); // Role[]
}
```

## Auth

Pass a custom `fetch` that injects your Bearer token, or include the `Authorization` header on `createClient`:

```ts
const client = createClient({
  baseUrl: 'https://app.test/api/admin',
  headers: {
    Authorization: `Bearer ${token}`,
  },
});
```

Or wrap the global `fetch` once and reuse across calls — see the openapi-fetch docs.

## Type-only imports

If you only need the spec types (no runtime client):

```ts
import type { paths, components } from '@modularize-rbac/sdk-ts';

type Role = components['schemas']['Role'];
type RolesIndexResponse = paths['/roles']['get']['responses']['200']['content']['application/json'];
```

## Postman collection

A ready-to-import Postman collection lives at the repo root: [postman.json](../postman.json). Drag it into Postman or Insomnia to get every endpoint with example bodies. The collection is generated from the same `openapi.json` and refreshed by the same CI gate that protects the SDK types.

## Regenerating

The types + collection ship pre-generated. To rebuild from a newer spec:

```bash
git pull
cd sdk-ts
npm install
npm run generate   # types only
npm run postman    # postman collection only
npm run build      # types + tsc → dist
```

The CI workflow `.github/workflows/sdk-ts-drift.yml` fails PRs that update `openapi.json`, `sdk-ts/`, or `postman.json` without regenerating both artefacts.

## Versioning

`0.x` while the surface stabilizes. Breaking changes can land in any minor; once we're on `1.0` the SDK follows the bridge's REST API contract via the `Access-Api-Version` header.
