# Changelog

All notable changes to `modularize-rbac/laravel` are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

## [1.0.0] - Unreleased

First publishable Packagist release. Hexagonal refactor split across PRs 0-6.

### Breaking changes vs. `casamento/rbac` 0.1.0

#### Naming
- Package renamed from `casamento/rbac` to `modularize-rbac/laravel`.
- Namespace `Casamento\Rbac\*` â†’ `ModularizeRbac\Laravel\*`.
- ServiceProvider: `RbacServiceProvider` â†’ `AccessServiceProvider`.
- Config file: `config/rbac.php` â†’ `config/access.php`. Publish tag is now `access-config`.
- Config keys moved from `config('rbac.*')` to `config('access.*')`.

#### Architecture
- The framework-agnostic core (entities, value objects, domain services, use-cases, ports) lives in a separate package: [`modularize-rbac/core`](https://github.com/chrisdjst/access-core).
- This package is a thin Laravel bridge: it implements the core's ports with Eloquent, exposes HTTP controllers, registers migrations and routes.
- `RoleModulePermissionObserver` removed â€” its sync algorithm now lives in `ModularizeRbac\Core\Domain\RoleModulePermission\RoleModulePermissionSynchronizer` (pure-function domain service) and runs inside the `SyncRoleModules` use-case.
- `Concerns\HasUuid` and `Concerns\HasTranslations` traits removed â€” UUID generation goes through the `IdGenerator` port; translation lookup goes through the `TranslationResolver` domain service.

#### REST API
- URLs and verbs are unchanged from 0.1.0; response shapes preserved for `Module`, `Role`, `Language` resources.
- Validation errors now return 422 with a `field`-keyed error map.
- Authorization failures return 403 via a registered `renderable` (mapping `AuthorizationFailed` domain exception).
- Not-found IDs return 404 via the same mechanism.

### Added
- **Ports**: `ModuleRepository`, `RoleRepository`, `PermissionRepository`, `LanguageRepository`, `TranslationRepository`, `RoleModulePermissionRepository`, `UnitOfWork`, `DomainEventDispatcher`, `LocaleResolver`, `Authorizer`, `ExternalPermissionGateway`.
- **Adapters**:
  - `Eloquent/Repositories/Eloquent*Repository` implementing each `*Repository` port.
  - `Persistence/SystemClock`, `Persistence/UuidV4IdGenerator`, `Persistence/LaravelUnitOfWork`.
  - `Localization/LaravelLocaleResolver`.
  - `Events/LaravelEventDispatcher`.
  - `Authorization/GateAuthorizer`.
  - `Spatie/SpatiePermissionGateway` (opt-in) + `Spatie/NullExternalPermissionGateway` (default when sync disabled).
- **Translation HTTP layer**: `Translations/TranslationApplier` converts the legacy `translations[]` payload into per-(field, locale) repository operations.
- **Config flag**: `access.spatie.enabled` to control whether the SyncRoleModules use-case replicates to Spatie's `role_has_permissions`.
- **CI**: `.github/workflows/ci.yml` matrix PHP 8.2/8.3/8.4 Ã— Laravel 11/12.
- **Tests**: 15 integration + feature tests via Orchestra Testbench (SQLite in-memory).

### Fixed
- Removed hardcoded `"version": "0.1.0"` from `composer.json` â€” Packagist resolves versions from Git tags.
- Removed dead PSR-4 autoload entry pointing at non-existent `database/factories/`.
- BOMs accidentally introduced by PowerShell-based namespace rename stripped from all PHP files.

### Release order (one-time)

The `modularize-rbac/core` constraint in `composer.json` is `*@dev` during initial publication because access-core hasn't been tagged v1.0.0 on Packagist yet. The release ritual is:

1. Tag `access-core` v1.0.0 â†’ submit to Packagist.
2. Tighten this constraint to `^1.0` and remove the `repositories.path` block in a follow-up PR.
3. Tag `access-laravel` v1.0.0 â†’ submit to Packagist.

### Roadmap

- **v2.0**: fully decouple `Role` and `Permission` Eloquent models from `Spatie\Permission\Models\*`. v1.0 still hard-requires `spatie/laravel-permission` even when the sync flag is off.

## [0.1.0] - 2026-04-23

Initial extraction from the Casamento platform as `casamento/rbac`. Never published to Packagist.
