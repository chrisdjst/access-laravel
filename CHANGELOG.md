# Changelog

All notable changes to `modularize/access-laravel` are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

## [Unreleased]

### Breaking
- Package renamed from `casamento/rbac` to `modularize/access-laravel`.
- Namespace `Casamento\Rbac\*` renamed to `Modularize\Access\Laravel\*`.
- `RbacServiceProvider` renamed to `AccessServiceProvider`.
- Config file renamed: `config/rbac.php` → `config/access.php`. Publish tag is now `access-config`.
- Config keys moved from `config('rbac.*')` to `config('access.*')`.

### Added
- `LICENSE` (MIT).
- `CHANGELOG.md`.
- `.gitattributes` with `export-ignore` to keep the Packagist tarball lean.

### Fixed
- Removed `version: "0.1.0"` field from `composer.json` (Packagist resolves versions from Git tags).
- Removed dead PSR-4 autoload entry pointing at `database/factories/` (directory does not exist).

### Planned (next PRs)
- Extract framework-agnostic core into `modularize/access-core` (PRs 1-2).
- Replace Eloquent-based domain with hexagonal architecture (PR 3).
- Thin HTTP controllers calling application use-cases (PR 4).
- Make `spatie/laravel-permission` an optional adapter (PR 5).
- First public Packagist release as `v1.0.0` (PR 6).
