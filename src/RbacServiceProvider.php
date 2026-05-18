<?php

declare(strict_types=1);

namespace Casamento\Rbac;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rbac.php', 'rbac');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/rbac.php' => config_path('rbac.php'),
        ], 'rbac-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerRoutes();

        // Models, observers, and Spatie overrides are wired in subsequent
        // PRs as files move into the package.
    }

    protected function registerRoutes(): void
    {
        Route::prefix((string) config('rbac.route_prefix', 'admin'))
            ->middleware((array) config('rbac.middleware', ['auth:sanctum']))
            ->group(__DIR__.'/../routes/api.php');
    }
}
