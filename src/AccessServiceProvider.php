<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Foundation\Exceptions\Handler as FoundationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate as GateFacade;
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

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerRoutes();
        $this->registerExceptionRenderers();
        $this->registerAccessGate();
        $this->registerConsoleCommands();
        $this->registerAdminPolicy();
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

        $this->app->bind(ModuleRepository::class, EloquentModuleRepository::class);
        $this->app->bind(RoleRepository::class, EloquentRoleRepository::class);
        $this->app->bind(PermissionRepository::class, EloquentPermissionRepository::class);
        $this->app->bind(LanguageRepository::class, EloquentLanguageRepository::class);
        $this->app->bind(TranslationRepository::class, EloquentTranslationRepository::class);
        $this->app->bind(RoleModulePermissionRepository::class, EloquentRoleModulePermissionRepository::class);
        $this->app->bind(AuditRepository::class, EloquentAuditRepository::class);

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
    }

    protected function registerRoutes(): void
    {
        Route::prefix((string) config('access.route_prefix', 'admin'))
            ->middleware((array) config('access.middleware', ['auth:sanctum']))
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
