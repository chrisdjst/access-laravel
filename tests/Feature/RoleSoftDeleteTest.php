<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use ModularizeRbac\Laravel\Models\Role as RoleModel;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): bool => true);
});

it('DELETE /api/admin/roles/{role} now soft-deletes — row stays + GET 404', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->deleteJson("/api/admin/roles/{$role['id']}")->assertNoContent();

    // Subsequent GET treats it as missing
    $this->getJson("/api/admin/roles/{$role['id']}")->assertStatus(404);

    // But the row still exists, marked deleted
    expect(RoleModel::withTrashed()->find($role['id']))->not->toBeNull()
        ->and(RoleModel::withTrashed()->find($role['id'])->trashed())->toBeTrue();
});

it('GET /api/admin/roles excludes soft-deleted roles from listings', function (): void {
    $keep = $this->postJson('/api/admin/roles', ['name' => 'kept', 'guard_name' => 'admin'])->json('data');
    $drop = $this->postJson('/api/admin/roles', ['name' => 'dropped', 'guard_name' => 'admin'])->json('data');

    $this->deleteJson("/api/admin/roles/{$drop['id']}")->assertNoContent();

    $response = $this->getJson('/api/admin/roles')->json('data');
    $names = array_map(fn ($r) => $r['name'], $response);

    expect($names)->toContain('kept')
        ->and($names)->not->toContain('dropped');
});

it('POST /api/admin/roles/{role}/restore reverses the soft delete', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->deleteJson("/api/admin/roles/{$role['id']}")->assertNoContent();
    $this->postJson("/api/admin/roles/{$role['id']}/restore")
        ->assertOk()
        ->assertJsonPath('data.name', 'editor');

    expect(RoleModel::find($role['id']))->not->toBeNull()
        ->and(RoleModel::find($role['id'])->trashed())->toBeFalse();
});

it('POST /restore returns 422 when the role is not soft-deleted', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->postJson("/api/admin/roles/{$role['id']}/restore")->assertStatus(422);
});

it('POST /restore returns 404 for an unknown id', function (): void {
    $this->postJson('/api/admin/roles/99999999-9999-9999-9999-999999999999/restore')
        ->assertStatus(404);
});
