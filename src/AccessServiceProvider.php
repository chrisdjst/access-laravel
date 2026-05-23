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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ModularizeRbac\Core\Exceptions\AuthorizationFailed;
use ModularizeRbac\Core\Exceptions\InvalidInput;
use ModularizeRbac\Core\Exceptions\NotFound;
use ModularizeRbac\Core\Application\Ports\Authorizer;
use ModularizeRbac\Core\Application\Ports\DomainEventDispatcher;
use ModularizeRbac\Core\Application\Ports\ExternalPermissionGateway;
use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Core\Application\Ports\LocaleResolver;
use ModularizeRbac\Core\Application\Ports\ModuleRepository;
use ModularizeRbac\Core\Application\Ports\PermissionRepository;
use ModularizeRbac\Core\Application\Ports\RoleModulePermissionRepository;
use ModularizeRbac\Core\Application\Ports\RoleRepository;
use ModularizeRbac\Core\Application\Ports\TranslationRepository;
use ModularizeRbac\Core\Application\Ports\UnitOfWork;
use ModularizeRbac\Core\Domain\Shared\Clock;
use ModularizeRbac\Core\Domain\Shared\IdGenerator;
use ModularizeRbac\Laravel\Authorization\GateAuthorizer;
use ModularizeRbac\Laravel\Eloquent\Mappers\LanguageMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\ModuleMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\ModulePermissionMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\PermissionMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\RoleMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\RoleModulePermissionMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\TranslationMapper;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentLanguageRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentModuleRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentPermissionRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentRoleModulePermissionRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentRoleRepository;
use ModularizeRbac\Laravel\Eloquent\Repositories\EloquentTranslationRepository;
use ModularizeRbac\Laravel\Events\LaravelEventDispatcher;
use ModularizeRbac\Laravel\Localization\LaravelLocaleResolver;
use ModularizeRbac\Laravel\Persistence\LaravelUnitOfWork;
use ModularizeRbac\Laravel\Persistence\SystemClock;
use ModularizeRbac\Laravel\Persistence\UuidV4IdGenerator;
use ModularizeRbac\Laravel\Spatie\NullExternalPermissionGateway;
use ModularizeRbac\Laravel\Spatie\SpatiePermissionGateway;

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

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerRoutes();
        $this->registerExceptionRenderers();
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
                'errors' => [$e->field => [$e->getMessage()]],
            ], 422);
        });
        $handler->renderable(static function (NotFound $e): JsonResponse {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        });
        $handler->renderable(static function (AuthorizationFailed $e): JsonResponse {
            return new JsonResponse(['message' => $e->getMessage()], 403);
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

        $this->app->singleton(DomainEventDispatcher::class, function (Application $app): LaravelEventDispatcher {
            return new LaravelEventDispatcher($app->make(Dispatcher::class));
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

        $this->app->bind(ModuleRepository::class, EloquentModuleRepository::class);
        $this->app->bind(RoleRepository::class, EloquentRoleRepository::class);
        $this->app->bind(PermissionRepository::class, EloquentPermissionRepository::class);
        $this->app->bind(LanguageRepository::class, EloquentLanguageRepository::class);
        $this->app->bind(TranslationRepository::class, EloquentTranslationRepository::class);
        $this->app->bind(RoleModulePermissionRepository::class, EloquentRoleModulePermissionRepository::class);
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
