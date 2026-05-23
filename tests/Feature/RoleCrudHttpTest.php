<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('POST /api/admin/roles creates a role and returns 201', function (): void {
    $response = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'display_name' => 'Editor',
        'guard_name' => 'admin',
        'level' => 50,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'editor')
        ->assertJsonPath('data.display_name', 'Editor')
        ->assertJsonPath('data.guard_name', 'admin')
        ->assertJsonPath('data.level', 50)
        ->assertJsonPath('data.is_system', false);
});

it('POST /api/admin/roles returns 422 on duplicate name within same guard', function (): void {
    $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->assertCreated();

    $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->assertStatus(422);
});

it('POST /api/admin/roles returns 422 on malformed name', function (): void {
    $this->postJson('/api/admin/roles', [
        'name' => 'Has Spaces',
        'guard_name' => 'admin',
    ])->assertStatus(422);
});

it('DELETE /api/admin/roles/{id} removes a role without bindings', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->deleteJson("/api/admin/roles/{$role['id']}")->assertNoContent();

    $this->getJson("/api/admin/roles/{$role['id']}")->assertStatus(404);
});

it('DELETE /api/admin/roles/{id} returns 422 when role still has bindings', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $module = $this->postJson('/api/admin/modules', [
        'slug' => 'events',
        'name' => 'Events',
    ])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [
            ['module_id' => $module['id'], 'is_reading_allowed' => true],
        ],
    ])->assertOk();

    $this->deleteJson("/api/admin/roles/{$role['id']}")->assertStatus(422);
});

it('DELETE /api/admin/roles/{id} returns 422 on a system role', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'super-admin',
        'guard_name' => 'admin',
        'level' => 100,
        'is_system' => true,
    ])->json('data');

    $this->deleteJson("/api/admin/roles/{$role['id']}")->assertStatus(422);
});

it('GET /api/admin/roles/{id}/permission-matrix returns role + per-module flags', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $events = $this->postJson('/api/admin/modules', [
        'slug' => 'events',
        'name' => 'Events',
        'sort_order' => 10,
    ])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [
            ['module_id' => $events['id'], 'is_reading_allowed' => true, 'is_writing_allowed' => true],
        ],
    ])->assertOk();

    $response = $this->getJson("/api/admin/roles/{$role['id']}/permission-matrix");
    $response->assertOk()
        ->assertJsonPath('data.role.name', 'editor')
        ->assertJsonPath('data.modules.0.slug', 'events')
        ->assertJsonPath('data.modules.0.flags.is_reading_allowed', true)
        ->assertJsonPath('data.modules.0.flags.is_writing_allowed', true)
        ->assertJsonPath('data.modules.0.flags.is_delete_allowed', false);
});
