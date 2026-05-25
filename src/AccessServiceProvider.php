<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Foundation\Exceptions\Handler as FoundationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate as GateFacade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ModularizeRbac\Core\Exceptions\AuthorizationFailed;
use ModularizeRbac\Core\Exceptions\InvalidInput;
use ModularizeRbac\Core\Exceptions\NotFound;
use ModularizeRbac\Core\Application\Ports\AuditRepository;
use ModularizeRbac\Core\Application\Ports\Authorizer;
use ModularizeRbac\Core\Application\Ports\DomainEventDispatcher;
use ModularizeRbac\Core\Application\Ports\ExternalPermissionGateway;
use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Core\Application\Ports\LocaleResolver;
use ModularizeRbac\Core\Application\Ports\ModuleRepository;
use ModularizeRbac\Core\Application\Ports\PermissionRepository;
use ModularizeRbac\Core\Application\Ports\RoleModulePermissionRepository;
use ModularizeRbac\Core\Application\Ports\RoleRepository;
use ModularizeRbac\Core\Application\Ports\TenantContext;
use ModularizeRbac\Core\Application\Ports\TranslationRepository;
use ModularizeRbac\Core\Application\Ports\UnitOfWork;
use ModularizeRbac\Core\Application\Ports\UserRoleAssigner;
use ModularizeRbac\Core\Application\Ports\UserRoleResolver;
use ModularizeRbac\Core\Domain\Shared\Clock;
use ModularizeRbac\Core\Domain\Shared\IdGenerator;
use ModularizeRbac\Laravel\Audit\AuditingListener;
use ModularizeRbac\Laravel\Authorization\GateAuthorizer;
use ModularizeRbac\Laravel\Authorization\ModuleHierarchyIndex;
use ModularizeRbac\Laravel\Cache\CacheInvalidationListener;
use ModularizeRbac\Laravel\Cache\CachedLanguageRepository;
use ModularizeRbac\Laravel\Cache\CachedModuleRepository;
use ModularizeRbac\Laravel\Cache\CacheVersion;
use ModularizeRbac\Laravel\Eloquent\Mappers\AuditEntryMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\LanguageMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\ModuleMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\ModulePermissionMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\PermissionMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\RoleMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\RoleModulePermissionMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\TranslationMapper;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentAuditRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentLanguageRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentModuleRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentPermissionRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentRoleModulePermissionRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentRoleRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentTranslationRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentUserRoleAssigner;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentUserRoleResolver;
use ModularizeRbac\Laravel\Events\LaravelEventDispatcher;
use ModularizeRbac\Laravel\Localization\LaravelLocaleResolver;
use ModularizeRbac\Laravel\Persistence\LaravelUnitOfWork;
use ModularizeRbac\Laravel\Persistence\SystemClock;
use ModularizeRbac\Laravel\Persistence\UuidV4IdGenerator;
use ModularizeRbac\Laravel\Spatie\NullExternalPermissionGateway;
use ModularizeRbac\Laravel\Spatie\SpatiePermissionGateway;
use ModularizeRbac\Laravel\Tenant\LaravelTenantContext;

class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/access.php', 'access');

        $this->registerInfraAdapters();
        $this->registerRepositories();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/access.php' => config_path('access.php'),
        ], 'access-config');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'access');
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/access'),
        ], 'access-lang');

        // Publish the example seeder as `database/seeders/AccessSeeder.php`
        // in the host's app. Renaming the .stub extension prevents the
        // file from being loaded by Composer's autoloader of the package
        // itself (it's source-by-design — hosts edit it after publish).
        $this->publishes([
            __DIR__.'/../database/seeders/AccessSeeder.stub' => database_path('seeders/AccessSeeder.php'),
        ], 'access-seeder');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerBulkLimiter();
        $this->registerRoutes();
        $this->registerExceptionRenderers();
        $this->registerAccessGate();
        $this->registerConsoleCommands();
        $this->registerAdminPolicy();
    }

    /**
     * Register the `access-bulk` named limiter consumed by the bulk
     * write endpoints. Reads `access.rate_limit.bulk` (default "10,1"
     * = 10 attempts per 1 minute). Setting the config to null
     * registers a passthrough that never limits, so hosts that
     * already throttle upstream can disable the package-level cap.
     */
    protected function registerBulkLimiter(): void
    {
        RateLimiter::for('access-bulk', static function (Request $request): array|Limit {
            $config = config('access.rate_limit.bulk', '10,1');
            if ($config === null || $config === '') {
                return Limit::none();
            }
            [$attempts, $minutes] = array_pad(explode(',', (string) $config, 2), 2, '1');
            $attempts = max(1, (int) $attempts);
            $minutes = max(1, (int) $minutes);

            // Per-user when authenticated, per-IP otherwise. Matches
            // Laravel's own `throttle:api` heuristic.
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'anon';

            return Limit::perMinutes($minutes, $attempts)->by('access-bulk:'.$key);
        });
    }

    protected function registerConsoleCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            \ModularizeRbac\Laravel\Console\DiagnoseCommand::class,
            \ModularizeRbac\Laravel\Console\SyncSpatieCommand::class,
            \ModularizeRbac\Laravel\Console\AuditCommand::class,
            \ModularizeRbac\Laravel\Console\AuditPurgeCommand::class,
            \ModularizeRbac\Laravel\Console\ExportCommand::class,
            \ModularizeRbac\Laravel\Console\ImportCommand::class,
            \ModularizeRbac\Laravel\Console\OpenApiCommand::class,
        ]);
    }

    /**
     * Register the configured admin policy as a `Gate::before`. The
     * policy short-circuits package abilities (admin.*) without
     * forcing the host to declare each one with `Gate::define()`.
     *
     * Hosts that want full control set `config('access.policies.admin')`
     * to null and wire abilities themselves.
     */
    protected function registerAdminPolicy(): void
    {
        $policy = config('access.policies.admin');
        if (! is_string($policy) || $policy === '') {
            return;
        }
        if (! class_exists($policy)) {
            return;
        }
        $instance = $this->app->make($policy);
        if (! method_exists($instance, 'before')) {
            return;
        }

        GateFacade::before(static function ($user, string $ability) use ($instance): ?bool {
            return $instance->before($user, $ability);
        });
    }

    /**
     * Register a `Gate::before` callback that delegates to the
     * {@see \ModularizeRbac\Laravel\Concerns\HasAccessPermissions}
     * trait when the authenticated user has it. Returning null from
     * the callback (when the user lacks the trait or doesn't grant
     * the ability) lets Laravel continue evaluating policies; an
     * explicit `true` short-circuits any other gates.
     *
     * Hosts that don't use the trait are unaffected — Gate behaves
     * exactly as before. Hosts that use Spatie's HasRoles trait
     * instead also continue to work since this callback returns
     * null when canAccess() returns false (not blocking other paths).
     */
    protected function registerAccessGate(): void
    {
        GateFacade::before(static function ($user, string $ability): ?bool {
            if ($user === null) {
                return null;
            }
            if (! method_exists($user, 'canAccess')) {
                return null;
            }

            return $user->canAccess($ability) ? true : null;
        });
    }

    /**
     * Wire each domain exception to a meaningful HTTP status code.
     * The host can override these renderers in its own Exception
     * Handler if it needs a different shape, but the defaults match
     * the legacy v0.1.0 contract (422 / 404 / 403).
     */
    protected function registerExceptionRenderers(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        if (! $handler instanceof FoundationHandler) {
            return; // host overrides the handler — let them wire it.
        }

        $handler->renderable(static function (InvalidInput $e): JsonResponse {
            return new JsonResponse([
                'message' => $e->getMessage(),
                'error_type' => trans('access::exceptions.invalid_input'),
                'errors' => [$e->field => [$e->getMessage()]],
            ], 422);
        });
        $handler->renderable(static function (NotFound $e): JsonResponse {
            return new JsonResponse([
                'message' => $e->getMessage(),
                'error_type' => trans('access::exceptions.not_found'),
            ], 404);
        });
        $handler->renderable(static function (AuthorizationFailed $e): JsonResponse {
            return new JsonResponse([
                'message' => $e->getMessage(),
                'error_type' => trans('access::exceptions.authorization_failed'),
            ], 403);
        });
    }

    protected function registerInfraAdapters(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdGenerator::class, UuidV4IdGenerator::class);

        // Scoped: one instance per request, so the inheritance walk
        // memoizes module-table reads within a request lifecycle.
        $this->app->scoped(ModuleHierarchyIndex::class, function (Application $app): ModuleHierarchyIndex {
            return new ModuleHierarchyIndex($app->make(\ModularizeRbac\Core\Application\Ports\ModuleRepository::class));
        });

        $this->app->singleton(UnitOfWork::class, function (Application $app): LaravelUnitOfWork {
            return new LaravelUnitOfWork(
                $app->make(ConnectionResolverInterface::class),
            );
        });

        $this->app->singleton(LocaleResolver::class, function (Application $app): LaravelLocaleResolver {
            return new LaravelLocaleResolver($app);
        });

        $this->app->singleton(TenantContext::class, function (Application $app): LaravelTenantContext {
            return new LaravelTenantContext($app);
        });

        $this->app->singleton(DomainEventDispatcher::class, function (Application $app): LaravelEventDispatcher {
            $auditEnabled = (bool) config('access.audit.enabled', true);

            return new LaravelEventDispatcher(
                dispatcher: $app->make(Dispatcher::class),
                audit: $auditEnabled ? $app->make(AuditingListener::class) : null,
            );
        });

        $this->app->singleton(Authorizer::class, function (Application $app): GateAuthorizer {
            return new GateAuthorizer(
                gate: $app->make(Gate::class),
                auth: $app->make(AuthFactory::class),
                guardName: (string) config('access.guard_name', 'admin'),
            );
        });

        $this->app->singleton(ExternalPermissionGateway::class, function (): ExternalPermissionGateway {
            return $this->resolveExternalPermissionGateway();
        });
    }

    protected function registerRepositories(): void
    {
        $this->app->singleton(ModuleMapper::class);
        $this->app->singleton(RoleMapper::class);
        $this->app->singleton(PermissionMapper::class);
        $this->app->singleton(LanguageMapper::class);
        $this->app->singleton(TranslationMapper::class);
        $this->app->singleton(ModulePermissionMapper::class);
        $this->app->singleton(RoleModulePermissionMapper::class);
        $this->app->singleton(AuditEntryMapper::class);

        $this->app->bind(RoleRepository::class, EloquentRoleRepository::class);
        $this->app->bind(PermissionRepository::class, EloquentPermissionRepository::class);
        $this->app->bind(TranslationRepository::class, EloquentTranslationRepository::class);
        $this->app->bind(RoleModulePermissionRepository::class, EloquentRoleModulePermissionRepository::class);
        $this->app->bind(AuditRepository::class, EloquentAuditRepository::class);

        $this->registerCacheableRepository(
            ModuleRepository::class,
            EloquentModuleRepository::class,
            'access:module',
            fn (ModuleRepository $inner, CacheContract $cache, CacheVersion $ver, int $ttl) =>
                new CachedModuleRepository($inner, $cache, $ver, $ttl),
        );
        $this->registerCacheableRepository(
            LanguageRepository::class,
            EloquentLanguageRepository::class,
            'access:lang',
            fn (LanguageRepository $inner, CacheContract $cache, CacheVersion $ver, int $ttl) =>
                new CachedLanguageRepository($inner, $cache, $ver, $ttl),
        );

        $this->app->bind(UserRoleResolver::class, function (Application $app): EloquentUserRoleResolver {
            return new EloquentUserRoleResolver(
                $app->make(ConnectionResolverInterface::class)->connection(),
            );
        });

        $this->app->bind(UserRoleAssigner::class, function (Application $app): EloquentUserRoleAssigner {
            return new EloquentUserRoleAssigner(
                $app->make(ConnectionResolverInterface::class)->connection(),
            );
        });

        $this->registerCacheInvalidationListener();
    }

    /**
     * Bind `$portInterface` either to a {@see CachedLanguageRepository}-style
     * decorator (when `config('access.cache.enabled')` is true) or
     * directly to the underlying Eloquent adapter.
     *
     * @template T of object
     *
     * @param  class-string<T>  $portInterface
     * @param  class-string<T>  $eloquentClass
     * @param  string  $cacheNamespace  used as the prefix for the version key + entries
     * @param  callable(T, CacheContract, CacheVersion, int): T  $decoratorFactory
     */
    protected function registerCacheableRepository(
        string $portInterface,
        string $eloquentClass,
        string $cacheNamespace,
        callable $decoratorFactory,
    ): void {
        // Always register a singleton closure that reads config at
        // resolution time. Hosts can flip access.cache.enabled at
        // runtime + call $app->forgetInstance($port) to swap.
        $this->app->singleton($portInterface, function (Application $app) use ($eloquentClass, $cacheNamespace, $decoratorFactory) {
            $inner = $app->make($eloquentClass);

            if (! (bool) config('access.cache.enabled', true)) {
                return $inner;
            }

            $store = config('access.cache.store');
            $storeName = is_string($store) && $store !== '' ? $store : null;
            $cache = $app->make(CacheFactory::class)->store($storeName);
            $version = new CacheVersion($cache, $cacheNamespace);
            $ttl = (int) config('access.cache.ttl', 3600);

            return $decoratorFactory($inner, $cache, $version, $ttl);
        });
    }

    /**
     * Wire the cache invalidator to the relevant domain events so
     * direct DB writes (Tinker, raw queries, console commands)
     * still flush the read cache via the dispatched event.
     */
    protected function registerCacheInvalidationListener(): void
    {
        if (! (bool) config('access.cache.enabled', true)) {
            return;
        }

        $this->app->singleton(CacheInvalidationListener::class, function (Application $app): CacheInvalidationListener {
            $store = config('access.cache.store');
            $storeName = is_string($store) && $store !== '' ? $store : null;
            $cache = $app->make(CacheFactory::class)->store($storeName);

            return new CacheInvalidationListener(
                languageVersion: new CacheVersion($cache, 'access:lang'),
                moduleVersion: new CacheVersion($cache, 'access:module'),
            );
        });

        $events = $this->app->make(Dispatcher::class);
        $events->listen(
            \ModularizeRbac\Core\Domain\Events\LanguageDefaultChanged::class,
            [CacheInvalidationListener::class, 'onLanguageDefaultChanged'],
        );
        $events->listen(
            \ModularizeRbac\Core\Domain\Events\ModuleCreated::class,
            [CacheInvalidationListener::class, 'onModuleCreated'],
        );
        $events->listen(
            \ModularizeRbac\Core\Domain\Events\ModuleUpdated::class,
            [CacheInvalidationListener::class, 'onModuleUpdated'],
        );
        $events->listen(
            \ModularizeRbac\Core\Domain\Events\ModuleDeleted::class,
            [CacheInvalidationListener::class, 'onModuleDeleted'],
        );
    }

    protected function registerRoutes(): void
    {
        // Always stamp the api-version header on package responses so
        // SDK consumers can detect contract drift across host upgrades.
        // Appended after the host-provided middleware list so it runs
        // closest to the controller (and to the response).
        $middleware = array_merge(
            (array) config('access.middleware', ['auth:sanctum']),
            [\ModularizeRbac\Laravel\Http\Middleware\AddApiVersionHeader::class],
        );

        Route::prefix((string) config('access.route_prefix', 'admin'))
            ->middleware($middleware)
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Pick the right ExternalPermissionGateway based on whether
     * spatie/laravel-permission is installed AND the host has not
     * explicitly disabled the integration via config.
     *
     * Defaults: enabled when Spatie's PermissionRegistrar class is
     * available, disabled otherwise. Hosts can force off (or force
     * on with a custom gateway by overriding the binding) via
     * `config('access.spatie.enabled')`.
     */
    protected function resolveExternalPermissionGateway(): ExternalPermissionGateway
    {
        $spatieAvailable = class_exists(\Spatie\Permission\PermissionRegistrar::class);
        $configured = config('access.spatie.enabled');
        $enabled = $configured === null
            ? $spatieAvailable
            : ($configured === true && $spatieAvailable);

        return $enabled
            ? new SpatiePermissionGateway($this->app->make(ConnectionResolverInterface::class)->connection())
            : new NullExternalPermissionGateway();
    }
}
