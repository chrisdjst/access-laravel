<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel;

use Modularize\Access\Laravel\Models\RoleModulePermission;
use Modularize\Access\Laravel\Observers\RoleModulePermissionObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/access.php', 'access');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/access.php' => config_path('access.php'),
        ], 'access-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerRoutes();

        RoleModulePermission::observe(RoleModulePermissionObserver::class);
    }

    protected function registerRoutes(): void
    {
        Route::prefix((string) config('access.route_prefix', 'admin'))
            ->middleware((array) config('access.middleware', ['auth:sanctum']))
            ->group(__DIR__.'/../routes/api.php');
    }
}
