<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modularize\Access\Application\Module\CreateModule\CreateModule;
use Modularize\Access\Application\Module\CreateModule\CreateModuleInput;
use Modularize\Access\Application\Ports\ExternalPermissionGateway;
use Modularize\Access\Application\Role\SyncRoleModules\SyncRoleModules;
use Modularize\Access\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use Modularize\Access\Laravel\Models\Role as RoleEloquent;
use Modularize\Access\Laravel\Spatie\NullExternalPermissionGateway;
use Modularize\Access\Laravel\Spatie\SpatiePermissionGateway;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('uses SpatiePermissionGateway by default when Spatie is available', function (): void {
    $gateway = $this->app->make(ExternalPermissionGateway::class);
    expect($gateway)->toBeInstanceOf(SpatiePermissionGateway::class);
});

it('uses NullExternalPermissionGateway when access.spatie.enabled is false', function (): void {
    config()->set('access.spatie.enabled', false);
    $this->app->forgetInstance(ExternalPermissionGateway::class);

    $gateway = $this->app->make(ExternalPermissionGateway::class);
    expect($gateway)->toBeInstanceOf(NullExternalPermissionGateway::class);
});

it('replicates SyncRoleModules grants into Spatie role_has_permissions', function (): void {
    $role = new RoleEloquent();
    $role->id = (string) Str::uuid();
    $role->name = 'editor';
    $role->display_name = 'Editor';
    $role->guard_name = 'web';
    $role->organization_id = null;
    $role->level = 50;
    $role->is_system = false;
    $role->save();

    /** @var CreateModule $create */
    $create = $this->app->make(CreateModule::class);
    $events = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));

    /** @var SyncRoleModules $sync */
    $sync = $this->app->make(SyncRoleModules::class);
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [
            ['module_id' => $events->id, 'is_reading_allowed' => true, 'is_writing_allowed' => true],
        ],
    ));

    $role->refresh();
    $names = $role->permissions->pluck('name')->all();
    expect($names)->toContain('events.view')->toContain('events.create');
});

it('preserves non-managed Spatie permissions across a sync', function (): void {
    $role = new RoleEloquent();
    $role->id = (string) Str::uuid();
    $role->name = 'editor';
    $role->display_name = 'Editor';
    $role->guard_name = 'web';
    $role->organization_id = null;
    $role->level = 50;
    $role->is_system = false;
    $role->save();

    /** @var CreateModule $create */
    $create = $this->app->make(CreateModule::class);
    $events = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));

    // Seed an extra non-managed permission via Spatie directly
    \Modularize\Access\Laravel\Models\Permission::findOrCreate('events.approve', 'web');
    $role->givePermissionTo('events.approve');
    $role->refresh();

    /** @var SyncRoleModules $sync */
    $sync = $this->app->make(SyncRoleModules::class);
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [
            ['module_id' => $events->id, 'is_reading_allowed' => true],
        ],
    ));

    $role->refresh();
    $names = $role->permissions->pluck('name')->all();
    expect($names)->toContain('events.view')
        ->and($names)->toContain('events.approve');
});
