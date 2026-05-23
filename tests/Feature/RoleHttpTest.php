<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modularize\Access\Laravel\Models\Role as RoleEloquent;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
    config()->set('access.middleware', ['api']);
});

function seedRole(): RoleEloquent
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

it('GET /roles lists roles', function (): void {
    seedRole();
    $this->getJson('/api/admin/roles')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('PUT /roles/{id} updates display_name', function (): void {
    $role = seedRole();
    $this->putJson("/api/admin/roles/{$role->id}", ['display_name' => 'Senior Editor'])
        ->assertOk()
        ->assertJsonPath('data.display_name', 'Senior Editor');
});

it('PUT /roles/{id}/modules syncs the permission matrix', function (): void {
    $role = seedRole();
    $module = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->json('data');

    $response = $this->putJson("/api/admin/roles/{$role->id}/modules", [
        'modules' => [
            ['module_id' => $module['id'], 'is_reading_allowed' => true, 'is_writing_allowed' => true],
        ],
    ]);
    $response->assertOk()
        ->assertJsonPath('data.modules.0.module_id', $module['id'])
        ->assertJsonPath('data.modules.0.flags.is_reading_allowed', true)
        ->assertJsonPath('data.modules.0.flags.is_writing_allowed', true)
        ->assertJsonPath('data.modules.0.flags.is_delete_allowed', false);
});
