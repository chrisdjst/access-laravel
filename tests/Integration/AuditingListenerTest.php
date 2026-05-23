<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
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

it('records an audit entry for every domain event a use-case emits', function (): void {
    /** @var CreateModule $create */
    $create = $this->app->make(CreateModule::class);
    $create->execute(new CreateModuleInput('events', 'Events', null, null, null, 10));

    $rows = DB::table('access_audit_log')->orderBy('occurred_at')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->event_name)->toBe('module.created');

    $payload = json_decode((string) $rows->first()->payload, true);
    expect($payload)->toBeArray()
        ->and($payload['slug'])->toBe('events');
});

it('records RolePermissionsChanged when SyncRoleModules grants new permissions', function (): void {
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
    $sync->execute(new SyncRoleModulesInput($role->id, [
        ['module_id' => $events->id, 'is_reading_allowed' => true],
    ]));

    $names = DB::table('access_audit_log')->pluck('event_name')->all();
    expect($names)->toContain('module.created')
        ->and($names)->toContain('role.permissions_changed');
});

it('writes no audit row when access.audit.enabled is false', function (): void {
    // Re-bind DomainEventDispatcher without the auditing listener
    config()->set('access.audit.enabled', false);
    $this->app->forgetInstance(\ModularizeRbac\Core\Application\Ports\DomainEventDispatcher::class);

    /** @var CreateModule $create */
    $create = $this->app->make(CreateModule::class);
    $create->execute(new CreateModuleInput('events', 'Events', null, null, null));

    expect(DB::table('access_audit_log')->count())->toBe(0);
});
