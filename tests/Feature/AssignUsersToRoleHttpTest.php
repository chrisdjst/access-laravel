<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('POST /api/admin/roles/{role}/users/bulk binds every user_id to the role', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $u1 = '22222222-2222-2222-2222-222222222222';
    $u2 = '33333333-3333-3333-3333-333333333333';

    $this->postJson("/api/admin/roles/{$role['id']}/users/bulk", [
        'user_ids' => [$u1, $u2],
    ])->assertOk()->assertJsonPath('data.id', $role['id']);

    $assignments = DB::table('role_user')->where('role_id', $role['id'])->get();
    expect($assignments)->toHaveCount(2);
    $userIds = $assignments->pluck('user_id')->all();
    expect($userIds)->toContain($u1, $u2);
});

it('POST users/bulk is idempotent — re-running with the same payload does not duplicate rows', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $u1 = '22222222-2222-2222-2222-222222222222';

    $this->postJson("/api/admin/roles/{$role['id']}/users/bulk", [
        'user_ids' => [$u1],
    ])->assertOk();

    $this->postJson("/api/admin/roles/{$role['id']}/users/bulk", [
        'user_ids' => [$u1],
    ])->assertOk();

    $rows = DB::table('role_user')
        ->where('role_id', $role['id'])
        ->where('user_id', $u1)
        ->count();
    expect($rows)->toBe(1);
});

it('POST users/bulk forwards organization_id when provided', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $u1 = '22222222-2222-2222-2222-222222222222';
    $tenant = '44444444-4444-4444-4444-444444444444';

    $this->postJson("/api/admin/roles/{$role['id']}/users/bulk", [
        'user_ids' => [$u1],
        'organization_id' => $tenant,
    ])->assertOk();

    $row = DB::table('role_user')
        ->where('role_id', $role['id'])
        ->where('user_id', $u1)
        ->first();
    expect($row->organization_id)->toBe($tenant);
});

it('POST users/bulk returns 404 for an unknown role', function (): void {
    $this->postJson('/api/admin/roles/99999999-9999-9999-9999-999999999999/users/bulk', [
        'user_ids' => ['22222222-2222-2222-2222-222222222222'],
    ])->assertStatus(404);
});

it('POST users/bulk returns 422 on empty user_ids', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->postJson("/api/admin/roles/{$role['id']}/users/bulk", [
        'user_ids' => [],
    ])->assertStatus(422);
});

it('POST users/bulk returns 422 when a user_id is not a UUID', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->postJson("/api/admin/roles/{$role['id']}/users/bulk", [
        'user_ids' => ['not-a-uuid'],
    ])->assertStatus(422);
});
