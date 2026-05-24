<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Laravel\Concerns\HasAccessPermissions;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

class InheritanceUser extends Authenticatable
{
    use HasAccessPermissions;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    Gate::before(function (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): ?bool {
        return str_starts_with($ability, 'admin.') ? true : null;
    });
});

function setupInheritanceUser(): array
{
    $user = new InheritanceUser();
    $user->id = (string) Str::uuid();
    $user->name = 'Inheritance Test';
    $user->save();

    $role = new RoleEloquent();
    $role->id = (string) Str::uuid();
    $role->name = 'editor';
    $role->display_name = 'Editor';
    $role->guard_name = 'web';
    $role->organization_id = null;
    $role->level = 50;
    $role->is_system = false;
    $role->save();

    DB::table('role_user')->insert([
        'role_id' => $role->id,
        'user_id' => $user->id,
        'organization_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$user, $role];
}

it('with inheritance disabled, a child module ability is NOT inherited from its parent', function (): void {
    config()->set('access.inheritance.enabled', false);

    [$user, $role] = setupInheritanceUser();
    $create = $this->app->make(CreateModule::class);
    $sync = $this->app->make(SyncRoleModules::class);

    $parent = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));
    $child = $create->execute(new CreateModuleInput('events.weddings', 'Weddings', null, null, $parent->id));

    // Role only has binding on the parent
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $parent->id, 'is_reading_allowed' => true]],
    ));

    expect($user->canAccess('events.view'))->toBeTrue()
        ->and($user->canAccess('events.weddings.view'))->toBeFalse();
});

it('with inheritance enabled, a child module ability IS inherited from its parent', function (): void {
    config()->set('access.inheritance.enabled', true);

    [$user, $role] = setupInheritanceUser();
    $create = $this->app->make(CreateModule::class);
    $sync = $this->app->make(SyncRoleModules::class);

    $parent = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));
    $child = $create->execute(new CreateModuleInput('events.weddings', 'Weddings', null, null, $parent->id));

    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $parent->id, 'is_reading_allowed' => true]],
    ));

    expect($user->canAccess('events.view'))->toBeTrue()
        ->and($user->canAccess('events.weddings.view'))->toBeTrue();
});

it('with inheritance enabled, an unrelated module ability is still denied', function (): void {
    config()->set('access.inheritance.enabled', true);

    [$user, $role] = setupInheritanceUser();
    $create = $this->app->make(CreateModule::class);
    $sync = $this->app->make(SyncRoleModules::class);

    $parent = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));
    $create->execute(new CreateModuleInput('billing', 'Billing', null, null, null));

    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $parent->id, 'is_reading_allowed' => true]],
    ));

    expect($user->canAccess('billing.view'))->toBeFalse();
});

it('with inheritance enabled, a child binding still wins when the parent has no binding', function (): void {
    config()->set('access.inheritance.enabled', true);

    [$user, $role] = setupInheritanceUser();
    $create = $this->app->make(CreateModule::class);
    $sync = $this->app->make(SyncRoleModules::class);

    $parent = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));
    $child = $create->execute(new CreateModuleInput('events.weddings', 'Weddings', null, null, $parent->id));

    // Bind only the child
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $child->id, 'is_reading_allowed' => true]],
    ));

    expect($user->canAccess('events.weddings.view'))->toBeTrue()
        ->and($user->canAccess('events.view'))->toBeFalse();
});

it('with inheritance enabled, a child action NOT granted by the parent is still denied', function (): void {
    config()->set('access.inheritance.enabled', true);

    [$user, $role] = setupInheritanceUser();
    $create = $this->app->make(CreateModule::class);
    $sync = $this->app->make(SyncRoleModules::class);

    $parent = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));
    $child = $create->execute(new CreateModuleInput('events.weddings', 'Weddings', null, null, $parent->id));

    // Parent has view but not delete
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $parent->id, 'is_reading_allowed' => true]],
    ));

    expect($user->canAccess('events.weddings.view'))->toBeTrue()
        ->and($user->canAccess('events.weddings.delete'))->toBeFalse();
});

it('with inheritance enabled, malformed ability strings are still rejected', function (): void {
    config()->set('access.inheritance.enabled', true);

    [$user] = setupInheritanceUser();

    expect($user->canAccess(''))->toBeFalse()
        ->and($user->canAccess('events'))->toBeFalse()
        ->and($user->canAccess('events.'))->toBeFalse();
});
