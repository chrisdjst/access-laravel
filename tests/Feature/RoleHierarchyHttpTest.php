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

class HierarchyTestUser extends Authenticatable
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
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('POST /api/admin/roles accepts an optional parent_role_id and surfaces it on the response', function (): void {
    $parent = $this->postJson('/api/admin/roles', [
        'name' => 'parent_role',
        'guard_name' => 'admin',
    ])->json('data');

    $child = $this->postJson('/api/admin/roles', [
        'name' => 'child_role',
        'guard_name' => 'admin',
        'parent_role_id' => $parent['id'],
    ]);

    $child->assertCreated()
        ->assertJsonPath('data.parent_role_id', $parent['id']);
});

it('POST /api/admin/roles returns 422 when parent_role_id references an unknown role', function (): void {
    $this->postJson('/api/admin/roles', [
        'name' => 'orphan',
        'guard_name' => 'admin',
        'parent_role_id' => '99999999-9999-9999-9999-999999999999',
    ])->assertStatus(422);
});

it('GET /api/admin/roles/{id} exposes parent_role_id (null for root roles)', function (): void {
    $root = $this->postJson('/api/admin/roles', [
        'name' => 'standalone',
        'guard_name' => 'admin',
    ])->json('data');

    $this->getJson("/api/admin/roles/{$root['id']}")
        ->assertOk()
        ->assertJsonPath('data.parent_role_id', null);
});

it('a user with a child role inherits the parent role bindings on canAccess()', function (): void {
    $parent = new RoleEloquent();
    $parent->id = (string) Str::uuid();
    $parent->name = 'parent';
    $parent->display_name = 'Parent';
    $parent->guard_name = 'web';
    $parent->organization_id = null;
    $parent->level = 50;
    $parent->is_system = false;
    $parent->parent_role_id = null;
    $parent->save();

    $child = new RoleEloquent();
    $child->id = (string) Str::uuid();
    $child->name = 'child';
    $child->display_name = 'Child';
    $child->guard_name = 'web';
    $child->organization_id = null;
    $child->level = 30;
    $child->is_system = false;
    $child->parent_role_id = $parent->id;
    $child->save();

    $user = new HierarchyTestUser();
    $user->id = (string) Str::uuid();
    $user->save();

    // user holds only the CHILD role
    DB::table('role_user')->insert([
        'role_id' => $child->id,
        'user_id' => $user->id,
        'organization_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Parent role gets bound to events.view via SyncRoleModules
    $create = $this->app->make(CreateModule::class);
    $events = $create->execute(new CreateModuleInput('events', 'Events', null, null, null));
    $sync = $this->app->make(SyncRoleModules::class);
    $sync->execute(new SyncRoleModulesInput(
        roleId: $parent->id,
        modules: [['module_id' => $events->id, 'is_reading_allowed' => true]],
    ));

    // Child has no direct binding but inherits from parent
    expect($user->canAccess('events.view'))->toBeTrue();
});

it('canAccess walks the parent chain (grandparent grant inherited by grandchild)', function (): void {
    $grandparent = new RoleEloquent();
    $grandparent->id = (string) Str::uuid();
    $grandparent->name = 'grandparent';
    $grandparent->display_name = 'GP';
    $grandparent->guard_name = 'web';
    $grandparent->organization_id = null;
    $grandparent->level = 50;
    $grandparent->is_system = false;
    $grandparent->parent_role_id = null;
    $grandparent->save();

    $parent = new RoleEloquent();
    $parent->id = (string) Str::uuid();
    $parent->name = 'parent';
    $parent->display_name = 'P';
    $parent->guard_name = 'web';
    $parent->organization_id = null;
    $parent->level = 40;
    $parent->is_system = false;
    $parent->parent_role_id = $grandparent->id;
    $parent->save();

    $child = new RoleEloquent();
    $child->id = (string) Str::uuid();
    $child->name = 'child';
    $child->display_name = 'C';
    $child->guard_name = 'web';
    $child->organization_id = null;
    $child->level = 30;
    $child->is_system = false;
    $child->parent_role_id = $parent->id;
    $child->save();

    $user = new HierarchyTestUser();
    $user->id = (string) Str::uuid();
    $user->save();
    DB::table('role_user')->insert([
        'role_id' => $child->id,
        'user_id' => $user->id,
        'organization_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $create = $this->app->make(CreateModule::class);
    $billing = $create->execute(new CreateModuleInput('billing', 'Billing', null, null, null));
    $sync = $this->app->make(SyncRoleModules::class);
    $sync->execute(new SyncRoleModulesInput(
        roleId: $grandparent->id,
        modules: [['module_id' => $billing->id, 'is_reading_allowed' => true]],
    ));

    expect($user->canAccess('billing.view'))->toBeTrue();
});

it('canAccess returns false when neither the role nor any ancestor grants the action', function (): void {
    $parent = new RoleEloquent();
    $parent->id = (string) Str::uuid();
    $parent->name = 'parent2';
    $parent->display_name = 'P';
    $parent->guard_name = 'web';
    $parent->organization_id = null;
    $parent->level = 50;
    $parent->is_system = false;
    $parent->parent_role_id = null;
    $parent->save();

    $child = new RoleEloquent();
    $child->id = (string) Str::uuid();
    $child->name = 'child2';
    $child->display_name = 'C';
    $child->guard_name = 'web';
    $child->organization_id = null;
    $child->level = 30;
    $child->is_system = false;
    $child->parent_role_id = $parent->id;
    $child->save();

    $user = new HierarchyTestUser();
    $user->id = (string) Str::uuid();
    $user->save();
    DB::table('role_user')->insert([
        'role_id' => $child->id,
        'user_id' => $user->id,
        'organization_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // No bindings on either role
    expect($user->canAccess('events.view'))->toBeFalse();
});
