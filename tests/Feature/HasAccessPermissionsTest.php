<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Laravel\Concerns\HasAccessPermissions;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

/**
 * A throwaway Eloquent user for testing the HasAccessPermissions
 * trait wiring without bringing a full host Laravel app.
 */
class TestUser extends Authenticatable
{
    use HasAccessPermissions;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function (): void {
    // Spin up a minimal `users` table — the host normally owns this.
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }
    // Bypass admin.* abilities required by use-cases — these tests
    // are about HasAccessPermissions, not authz on the setup path.
    // Important: this Gate::before must NOT swallow `events.*` /
    // `billing.*` lookups, since those are exactly what the trait
    // should resolve. We only short-circuit `admin.*` here.
    \Illuminate\Support\Facades\Gate::before(function (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): ?bool {
        if (str_starts_with($ability, 'admin.')) {
            return true;
        }

        return null;
    });
});

function makeUserWithRole(): array
{
    $user = new TestUser();
    $user->id = (string) Str::uuid();
    $user->name = 'Test';
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

it('rbacRoles() returns the roles assigned via role_user pivot', function (): void {
    [$user, $role] = makeUserWithRole();

    $userRoles = $user->rbacRoles()->pluck('roles.id')->all();
    expect($userRoles)->toBe([$role->id]);
});

it('canAccess returns false when user has no roles', function (): void {
    $user = new TestUser();
    $user->id = (string) Str::uuid();
    $user->save();

    expect($user->canAccess('events.view'))->toBeFalse();
});

it('canAccess returns true when role binding grants the action', function (): void {
    [$user, $role] = makeUserWithRole();
    $create = $this->app->make(CreateModule::class);
    $events = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));

    $sync = $this->app->make(SyncRoleModules::class);
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [
            ['module_id' => $events->id, 'is_reading_allowed' => true, 'is_writing_allowed' => true],
        ],
    ));

    expect($user->canAccess('events.view'))->toBeTrue()
        ->and($user->canAccess('events.create'))->toBeTrue()
        ->and($user->canAccess('events.delete'))->toBeFalse()
        ->and($user->canAccess('events.list'))->toBeFalse();
});

it('canAccess returns false for unknown module slug', function (): void {
    [$user, $role] = makeUserWithRole();
    $create = $this->app->make(CreateModule::class);
    $events = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));
    $sync = $this->app->make(SyncRoleModules::class);
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $events->id, 'is_reading_allowed' => true]],
    ));

    expect($user->canAccess('billing.view'))->toBeFalse();
});

it('canAccess returns false for malformed ability strings', function (): void {
    [$user] = makeUserWithRole();

    expect($user->canAccess(''))->toBeFalse()
        ->and($user->canAccess('events'))->toBeFalse()
        ->and($user->canAccess('events.'))->toBeFalse();
});

it('Gate::before delegates to canAccess so $user->can() works', function (): void {
    [$user, $role] = makeUserWithRole();
    $create = $this->app->make(CreateModule::class);
    $events = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));
    $sync = $this->app->make(SyncRoleModules::class);
    $sync->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $events->id, 'is_reading_allowed' => true]],
    ));

    // Use Laravel's Gate facade resolving the User via auth(); for the
    // test we call directly through forUser.
    expect(\Illuminate\Support\Facades\Gate::forUser($user)->allows('events.view'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Gate::forUser($user)->allows('events.delete'))->toBeFalse();
});
