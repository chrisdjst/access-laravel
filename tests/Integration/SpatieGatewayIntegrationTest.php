<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Ports\ExternalPermissionGateway;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;
use ModularizeRbac\Laravel\Spatie\NullExternalPermissionGateway;
use ModularizeRbac\Laravel\Spatie\SpatiePermissionGateway;

beforeEach(function (): void {
    // The Spatie integration is opt-in in v2 — skip the whole file
    // when spatie/laravel-permission isn't on the classpath.
    if (! class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
        $this->markTestSkipped('spatie/laravel-permission not installed — Spatie gateway tests skipped.');
    }
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

function pivotPermissionNames(string $roleId): array
{
    return DB::table('role_has_permissions')
        ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
        ->where('role_has_permissions.role_id', $roleId)
        ->pluck('permissions.name')
        ->all();
}

function seedTestRole(): RoleEloquent
{
    $role = new RoleEloquent();
    $role->id = (string) Str::uuid();
    $role->name = 'editor';
    $role->display_name = 'Editor';
    $role->guard_name = 'web';
    $role->organization_id = null;
    $role->level = 50;
    $role->is_system = false;
    $role->save();

    return $role;
}

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

it('replicates SyncRoleModules grants into the role_has_permissions pivot', function (): void {
    $role = seedTestRole();

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

    expect(pivotPermissionNames($role->id))->toContain('events.view')->toContain('events.create');
});

it('preserves non-managed pivot permissions across a sync', function (): void {
    $role = seedTestRole();

    /** @var CreateModule $create */
    $create = $this->app->make(CreateModule::class);
    $events = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));

    // Seed an extra non-managed permission directly into the package
    // schema + Spatie pivot — v2: no Spatie API calls needed.
    $manualPermission = \ModularizeRbac\Laravel\Models\Permission::findOrCreate('events.approve', 'web');
    DB::table('role_has_permissions')->insert([
        'permission_id' => $manualPermission->id,
        'role_id' => $role->id,
    ]);

    /** @var SyncRoleModules $sync */
    $sync = $this->app->make(SyncRoleModules::class);
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [
            ['module_id' => $events->id, 'is_reading_allowed' => true],
        ],
    ));

    $names = pivotPermissionNames($role->id);
    expect($names)->toContain('events.view')
        ->and($names)->toContain('events.approve');
});
