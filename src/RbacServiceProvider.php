<?php

declare(strict_types=1);

namespace Casamento\Rbac;

use Casamento\Rbac\Models\RoleModulePermission;
use Casamento\Rbac\Observers\RoleModulePermissionObserver;
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

        RoleModulePermission::observe(RoleModulePermissionObserver::class);
    }

    protected function registerRoutes(): void
    {
        Route::prefix((string) config('rbac.route_prefix', 'admin'))
            ->middleware((array) config('rbac.middleware', ['auth:sanctum']))
            ->group(__DIR__.'/../routes/api.php');
    }
}
