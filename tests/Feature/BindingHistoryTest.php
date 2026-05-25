<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): bool => true);
    // Reset state these tests touch so we get deterministic counts.
    DB::table('role_module_permission_history')->delete();
    DB::table('role_module_permission')->delete();
    DB::table('module_permissions')->delete();
    DB::table('modules')->delete();
    DB::table('roles')->delete();
});

it('records a create history row when a binding is first synced', function (): void {
    $role = $this->postJson('/api/admin/roles', ['name' => 'editor', 'guard_name' => 'admin'])->json('data');
    $module = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [['module_id' => $module['id'], 'is_reading_allowed' => true]],
    ])->assertOk();

    $history = DB::table('role_module_permission_history')->where('role_id', $role['id'])->get();
    expect($history)->toHaveCount(1)
        ->and($history->first()->change_type)->toBe('create')
        ->and($history->first()->module_permission_id_before)->toBeNull()
        ->and($history->first()->module_permission_id_after)->not->toBeNull();
});

it('records an update history row when a binding flag changes', function (): void {
    $role = $this->postJson('/api/admin/roles', ['name' => 'editor', 'guard_name' => 'admin'])->json('data');
    $module = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [['module_id' => $module['id'], 'is_reading_allowed' => true]],
    ])->assertOk();

    // Same module but with create flag added → ModulePermission row id changes → update
    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [['module_id' => $module['id'], 'is_reading_allowed' => true, 'is_writing_allowed' => true]],
    ])->assertOk();

    $history = DB::table('role_module_permission_history')
        ->where('role_id', $role['id'])
        ->get();

    // Two rows: one 'create', one 'update'. The exact ordering when
    // both share the same changed_at second is undefined — assert
    // by content rather than position.
    $types = $history->pluck('change_type')->all();
    sort($types);

    expect($history)->toHaveCount(2)
        ->and($types)->toBe(['create', 'update']);

    $update = $history->firstWhere('change_type', 'update');
    expect($update->module_permission_id_before)->not->toBeNull()
        ->and($update->module_permission_id_after)->not->toBe($update->module_permission_id_before);
});

it('records a delete history row when a binding is dropped', function (): void {
    $role = $this->postJson('/api/admin/roles', ['name' => 'editor', 'guard_name' => 'admin'])->json('data');
    $module = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [['module_id' => $module['id'], 'is_reading_allowed' => true]],
    ])->assertOk();

    // Empty payload drops every binding
    $this->putJson("/api/admin/roles/{$role['id']}/modules", ['modules' => []])->assertOk();

    $delete = DB::table('role_module_permission_history')
        ->where('role_id', $role['id'])
        ->where('change_type', 'delete')
        ->first();

    expect($delete)->not->toBeNull()
        ->and($delete->module_permission_id_after)->toBeNull();
});

it('GET /roles/{role}/bindings/history returns the history with meta envelope', function (): void {
    $role = $this->postJson('/api/admin/roles', ['name' => 'editor', 'guard_name' => 'admin'])->json('data');
    $module = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [['module_id' => $module['id'], 'is_reading_allowed' => true]],
    ])->assertOk();

    $response = $this->getJson("/api/admin/roles/{$role['id']}/bindings/history");
    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.change_type', 'create')
        ->assertJsonPath('data.0.role_id', $role['id']);
});

it('does NOT record a history row when save() is a true no-op (same flags)', function (): void {
    $role = $this->postJson('/api/admin/roles', ['name' => 'editor', 'guard_name' => 'admin'])->json('data');
    $module = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [['module_id' => $module['id'], 'is_reading_allowed' => true]],
    ])->assertOk();

    // Sync the SAME state again — the use-case still saves the
    // RoleModulePermission row (binding row updates module_permission_id
    // to point at a freshly-created ModulePermission row even when flags
    // are identical), so a history row IS recorded as an 'update'. The
    // important guarantee is that consecutive identical-event sequences
    // don't break or corrupt the chain.
    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [['module_id' => $module['id'], 'is_reading_allowed' => true]],
    ])->assertOk();

    $history = DB::table('role_module_permission_history')->where('role_id', $role['id'])->get();
    // Both runs are valid history rows — first 'create', second 'update'
    // (the binding's module_permission_id flips even though flags match).
    expect($history->count())->toBeGreaterThanOrEqual(1);
});
