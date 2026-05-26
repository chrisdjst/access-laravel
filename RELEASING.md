# Releasing

This repo ships two npm packages alongside the PHP Composer package. Each has its own release flow.

## PHP package — `modularize-rbac/laravel`

Packagist auto-publishes when a Git tag is pushed. The tag drives the Composer version: `v2.8.0`, `v2.8.1`, etc. No manual upload step.

```bash
git tag v2.8.1
git push origin v2.8.1
```

Within ~15 minutes the release is live on [Packagist](https://packagist.org/packages/modularize-rbac/laravel).

## npm packages — `@modularize-rbac/sdk-ts` and `@modularize-rbac/admin-react`

Both are published via [`.github/workflows/npm-publish.yml`](.github/workflows/npm-publish.yml) when a release tag is pushed. Tag conventions:

| Tag pattern         | Package                          | Source directory |
|--------------------|----------------------------------|------------------|
| `sdk-ts-v*`        | `@modularize-rbac/sdk-ts`        | `sdk-ts/`        |
| `admin-react-v*`   | `@modularize-rbac/admin-react`   | `frontend/`      |

### Prerequisites (one-time)

1. **npm org**: the `@modularize-rbac` scope exists on npmjs.com and the publishing account is an org member.
2. **GitHub secret**: `NPM_TOKEN` is set in the repo's Actions secrets to a [granular automation token](https://docs.npmjs.com/creating-and-viewing-access-tokens) scoped to the org with publish rights on both packages.

### Cutting a release

1. **Bump the version** in `sdk-ts/package.json` or `frontend/package.json`. Open a PR, get it reviewed, merge.
2. **Locally, after the merge lands on `main`**:
   ```bash
   git pull --ff-only origin main
   # For sdk-ts:
   git tag sdk-ts-v0.1.1
   git push origin sdk-ts-v0.1.1
   # For admin-react:
   git tag admin-react-v0.1.1
   git push origin admin-react-v0.1.1
   ```
3. The workflow:
   - verifies the tag version matches `package.json` (exits 1 on mismatch),
   - installs deps + builds sdk-ts (always, since admin-react peer-depends on it),
   - installs + builds + tests admin-react (only when publishing it),
   - runs `npm publish --access public`.
4. Confirm the package appears on https://www.npmjs.com/org/modularize-rbac within a couple of minutes.

### Releasing both at once

Push both tags one after the other. They run in parallel jobs (independent of each other).

```bash
git tag sdk-ts-v0.1.0
git tag admin-react-v0.1.0
git push origin sdk-ts-v0.1.0 admin-react-v0.1.0
```

### Rolling back a bad release

`npm unpublish` is only available within 72 hours and only for packages with no dependents. Prefer **deprecating** instead:

```bash
npm deprecate '@modularize-rbac/sdk-ts@0.1.0' 'Use 0.1.1 — fixes …'
```

…then publish a patch.
