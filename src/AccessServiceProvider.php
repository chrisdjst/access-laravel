<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modularize\Access\Application\Ports\Authorizer;
use Modularize\Access\Application\Ports\DomainEventDispatcher;
use Modularize\Access\Application\Ports\ExternalPermissionGateway;
use Modularize\Access\Application\Ports\LanguageRepository;
use Modularize\Access\Application\Ports\LocaleResolver;
use Modularize\Access\Application\Ports\ModuleRepository;
use Modularize\Access\Application\Ports\PermissionRepository;
use Modularize\Access\Application\Ports\RoleModulePermissionRepository;
use Modularize\Access\Application\Ports\RoleRepository;
use Modularize\Access\Application\Ports\TranslationRepository;
use Modularize\Access\Application\Ports\UnitOfWork;
use Modularize\Access\Domain\Shared\Clock;
use Modularize\Access\Domain\Shared\IdGenerator;
use Modularize\Access\Laravel\Authorization\GateAuthorizer;
use Modularize\Access\Laravel\Eloquent\Mappers\LanguageMapper;
use Modularize\Access\Laravel\Eloquent\Mappers\ModuleMapper;
use Modularize\Access\Laravel\Eloquent\Mappers\ModulePermissionMapper;
use Modularize\Access\Laravel\Eloquent\Mappers\PermissionMapper;
use Modularize\Access\Laravel\Eloquent\Mappers\RoleMapper;
use Modularize\Access\Laravel\Eloquent\Mappers\RoleModulePermissionMapper;
use Modularize\Access\Laravel\Eloquent\Mappers\TranslationMapper;
use Modularize\Access\Laravel\Eloquent\Repositories\EloquentLanguageRepository;
use Modularize\Access\Laravel\Eloquent\Repositories\EloquentModuleRepository;
use Modularize\Access\Laravel\Eloquent\Repositories\EloquentPermissionRepository;
use Modularize\Access\Laravel\Eloquent\Repositories\EloquentRoleModulePermissionRepository;
use Modularize\Access\Laravel\Eloquent\Repositories\EloquentRoleRepository;
use Modularize\Access\Laravel\Eloquent\Repositories\EloquentTranslationRepository;
use Modularize\Access\Laravel\Events\LaravelEventDispatcher;
use Modularize\Access\Laravel\Localization\LaravelLocaleResolver;
use Modularize\Access\Laravel\Models\RoleModulePermission;
use Modularize\Access\Laravel\Observers\RoleModulePermissionObserver;
use Modularize\Access\Laravel\Persistence\LaravelUnitOfWork;
use Modularize\Access\Laravel\Persistence\SystemClock;
use Modularize\Access\Laravel\Persistence\UuidV4IdGenerator;
use Modularize\Access\Laravel\Spatie\NullExternalPermissionGateway;

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

        // Legacy compatibility: the observer-driven Spatie sync stays
        // active until PR 4 swaps the controllers to use-cases. From
        // PR 5 onwards, the observer is removed and Spatie sync runs
        // via a domain-event listener.
        RoleModulePermission::observe(RoleModulePermissionObserver::class);
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

        // PR 5 will replace this binding with a real
        // SpatiePermissionGateway when spatie/laravel-permission is
        // installed and `access.spatie.enabled` is true.
        $this->app->singleton(ExternalPermissionGateway::class, NullExternalPermissionGateway::class);
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
}
