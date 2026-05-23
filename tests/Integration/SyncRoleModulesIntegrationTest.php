<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;
use ModularizeRbac\Laravel\Models\RoleModulePermission as RMP;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('persists role-module bindings via SyncRoleModules use-case', function (): void {

    // Seed a role directly via Eloquent so we have a stable target
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
    $billing = $create->execute(new CreateModuleInput('billing', 'Billing', null, null, null));

    /** @var SyncRoleModules $sync */
    $sync = $this->app->make(SyncRoleModules::class);
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [
            ['module_id' => $events->id, 'is_reading_allowed' => true, 'is_writing_allowed' => true],
            ['module_id' => $billing->id, 'is_reading_allowed' => true],
        ],
    ));

    expect(RMP::query()->where('role_id', $role->id)->count())->toBe(2);

    // Drop billing in a second sync
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $events->id, 'is_reading_allowed' => true]],
    ));

    expect(RMP::query()->where('role_id', $role->id)->count())->toBe(1);
    expect(RMP::query()->where('role_id', $role->id)->where('module_id', $billing->id)->exists())->toBeFalse();
});
