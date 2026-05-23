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

class PolicyTestUser extends Authenticatable
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
            $table->timestamps();
        });
    }
    // Bypass admin-side authz for the setup pathway (create module,
    // sync role), but DON'T bypass `admin.modules.view` — that's the
    // ability under test.
    Gate::before(function (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): ?bool {
        if (in_array($ability, ['admin.modules.create', 'admin.roles.update'], true)) {
            return true;
        }

        return null;
    });
});

it('default policy allows admin.modules.view when the user has the binding', function (): void {
    $user = new PolicyTestUser();
    $user->id = (string) Str::uuid();
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

    // Seed an `admin.modules` module the user can view.
    $adminModule = $this->app->make(CreateModule::class)->execute(
        new CreateModuleInput('admin.modules', 'Admin Modules', null, null, null)
    );
    $this->app->make(SyncRoleModules::class)->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $adminModule->id, 'is_reading_allowed' => true]],
    ));

    expect(Gate::forUser($user)->allows('admin.modules.view'))->toBeTrue();
});

it('default policy denies anonymous admin.* requests', function (): void {
    expect(Gate::forUser(null)->allows('admin.modules.view'))->toBeFalse();
});

it('default policy lets non-admin abilities fall through', function (): void {
    // No user, no binding — but `events.view` is not an admin.* ability,
    // so the policy returns null and Gate's default (deny) takes over.
    expect(Gate::forUser(null)->allows('events.view'))->toBeFalse();
});
