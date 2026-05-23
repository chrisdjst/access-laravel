# Contributing to `modularize-rbac/laravel`

Thanks for your interest. This package is the Laravel bridge that plugs [`modularize-rbac/core`](https://github.com/chrisdjst/access-core) into a host Laravel app via Eloquent, HTTP controllers, console commands, and an optional Spatie adapter.

## Before you start

- Read the [README](README.md) for the architecture and host wiring.
- Check the [CHANGELOG](CHANGELOG.md) for the latest release and breaking changes.
- Search [open issues](https://github.com/chrisdjst/access-laravel/issues) and [discussions](https://github.com/chrisdjst/access-laravel/discussions).

## Development workflow

```bash
git clone git@github.com:chrisdjst/access-laravel.git
cd access-laravel
composer install

# run the test suite (with Spatie installed)
vendor/bin/pest

# validate the matrix used in CI by removing Spatie and re-running
composer remove --dev spatie/laravel-permission
vendor/bin/pest
composer require --dev spatie/laravel-permission:^6.24
```

CI runs the same matrix on every PR: PHP 8.2/8.3/8.4 × Laravel 11/12 × `[with, without]` Spatie.

## Branch naming

- `feat/<short-description>` — new features
- `fix/<short-description>` — bug fixes
- `chore/<short-description>` — refactors, docs, tooling
- `pr/v<release>-<topic>` — multi-PR roadmap branches

## Commit messages

Conventional Commits, scoped to the affected layer when helpful:

```
feat(http): add POST /roles endpoint
fix(eloquent): hydrate audit payload from JSON column
chore(ci): cache composer downloads
docs(readme): clarify HasAccessPermissions wiring
```

The first line is < 72 chars. Body explains the *why*.

## Pull requests

- Open against `main`.
- Fill in `.github/PULL_REQUEST_TEMPLATE.md`.
- Keep diff focused; split unrelated changes.
- Tests required for new use-cases / adapters / endpoints.
- `composer validate --strict` must stay clean.
- If you introduce a breaking change, justify it and update `CHANGELOG.md` under `Unreleased > Breaking`.

## What goes where

This package handles the Laravel infrastructure: Eloquent repositories, HTTP controllers, FormRequests, JsonResources, console commands, migrations, the Spatie adapter.

Pure domain or application logic (entities, value objects, domain services, use-cases, ports) belongs in [`modularize-rbac/core`](https://github.com/chrisdjst/access-core).

If you're tempted to add `Modularize\Core\Domain\…` here, send the PR upstream to access-core instead.

## Releasing

Maintainers tag releases via `git tag -a vX.Y.Z` + `gh release create`. Packagist's webhook auto-updates.

SemVer:
- Major: breaking changes to public PHP API or REST contract
- Minor: new endpoints, new use-cases exposed, new adapters
- Patch: bug fixes, doc-only changes

## Questions

Open a [Discussion](https://github.com/chrisdjst/access-laravel/discussions) or file an issue. Security reports — see [SECURITY.md](SECURITY.md).
