<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('access:diagnose passes on a fresh testbench schema', function (): void {
    $this->artisan('access:diagnose')
        ->expectsOutputToContain('all checks passed')
        ->assertSuccessful();
});

it('access:audit prints "No audit entries" when log is empty', function (): void {
    $this->artisan('access:audit')
        ->expectsOutputToContain('No audit entries match the filters.')
        ->assertSuccessful();
});

it('access:audit lists entries after a use-case dispatches events', function (): void {
    $this->app->make(CreateModule::class)->execute(
        new CreateModuleInput('events', 'Events', null, null, null)
    );

    $this->artisan('access:audit', ['--limit' => 10])
        ->expectsOutputToContain('module.created')
        ->assertSuccessful();
});

it('access:audit --format=json emits NDJSON', function (): void {
    $this->app->make(CreateModule::class)->execute(
        new CreateModuleInput('events', 'Events', null, null, null)
    );

    $this->artisan('access:audit', ['--format' => 'json'])
        ->expectsOutputToContain('module.created')
        ->assertSuccessful();
});

it('access:audit returns failure on an invalid limit', function (): void {
    $this->artisan('access:audit', ['--limit' => 0])
        ->assertExitCode(1);
});

it('access:sync-spatie warns when the gateway is Null', function (): void {
    config()->set('access.spatie.enabled', false);
    $this->app->forgetInstance(\ModularizeRbac\Core\Application\Ports\ExternalPermissionGateway::class);

    $this->artisan('access:sync-spatie')
        ->expectsOutputToContain('not active')
        ->assertSuccessful();
});

it('access:sync-spatie --dry-run inspects bindings without applying', function (): void {
    if (! class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
        $this->markTestSkipped('spatie/laravel-permission not installed');
    }

    $role = new RoleEloquent();
    $role->id = (string) Str::uuid();
    $role->name = 'editor';
    $role->display_name = 'Editor';
    $role->guard_name = 'web';
    $role->organization_id = null;
    $role->level = 50;
    $role->is_system = false;
    $role->save();

    $events = $this->app->make(CreateModule::class)->execute(
        new CreateModuleInput('events', 'Events', null, null, null)
    );
    $this->app->make(SyncRoleModules::class)->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [
            ['module_id' => $events->id, 'is_reading_allowed' => true],
        ],
    ));

    $this->artisan('access:sync-spatie', ['--dry-run' => true])
        ->expectsOutputToContain('dry-run')
        ->assertSuccessful();
});
