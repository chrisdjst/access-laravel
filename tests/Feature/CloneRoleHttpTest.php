<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('POST /api/admin/roles/{source}/clone creates a copy of the source role', function (): void {
    $source = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'display_name' => 'Editor',
        'guard_name' => 'admin',
        'level' => 50,
    ])->json('data');

    $response = $this->postJson("/api/admin/roles/{$source['id']}/clone", [
        'name' => 'editor_v2',
        'display_name' => 'Editor v2',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'editor_v2')
        ->assertJsonPath('data.display_name', 'Editor v2')
        ->assertJsonPath('data.guard_name', 'admin')
        ->assertJsonPath('data.level', 50)
        ->assertJsonPath('data.is_system', false);

    expect($response->json('data.id'))->not->toBe($source['id']);
});

it('clone inherits the source display_name when one is not provided', function (): void {
    $source = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'display_name' => 'Editor',
        'guard_name' => 'admin',
    ])->json('data');

    $response = $this->postJson("/api/admin/roles/{$source['id']}/clone", [
        'name' => 'editor_copy',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.display_name', 'Editor');
});

it('clone mirrors the source module bindings onto the new role', function (): void {
    $source = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $events = $this->postJson('/api/admin/modules', [
        'slug' => 'events',
        'name' => 'Events',
    ])->json('data');

    $billing = $this->postJson('/api/admin/modules', [
        'slug' => 'billing',
        'name' => 'Billing',
    ])->json('data');

    $this->putJson("/api/admin/roles/{$source['id']}/modules", [
        'modules' => [
            ['module_id' => $events['id'], 'is_reading_allowed' => true, 'is_writing_allowed' => true],
            ['module_id' => $billing['id'], 'is_reading_allowed' => true],
        ],
    ])->assertOk();

    $clone = $this->postJson("/api/admin/roles/{$source['id']}/clone", [
        'name' => 'editor_v2',
    ])->json('data');

    $matrix = $this->getJson("/api/admin/roles/{$clone['id']}/permission-matrix");
    $matrix->assertOk();

    $modules = collect($matrix->json('data.modules'))->keyBy('slug');
    expect($modules->keys()->all())->toContain('events', 'billing')
        ->and($modules['events']['flags']['is_reading_allowed'])->toBeTrue()
        ->and($modules['events']['flags']['is_writing_allowed'])->toBeTrue()
        ->and($modules['billing']['flags']['is_reading_allowed'])->toBeTrue()
        ->and($modules['billing']['flags']['is_writing_allowed'])->toBeFalse();
});

it('POST clone returns 422 when the new name collides with an existing role', function (): void {
    $source = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->postJson('/api/admin/roles', [
        'name' => 'taken',
        'guard_name' => 'admin',
    ])->assertCreated();

    $this->postJson("/api/admin/roles/{$source['id']}/clone", [
        'name' => 'taken',
    ])->assertStatus(422);
});

it('POST clone returns 422 on malformed name', function (): void {
    $source = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->postJson("/api/admin/roles/{$source['id']}/clone", [
        'name' => 'Has Spaces',
    ])->assertStatus(422);
});

it('POST clone returns 404 for an unknown source role', function (): void {
    $this->postJson('/api/admin/roles/99999999-9999-9999-9999-999999999999/clone', [
        'name' => 'whatever',
    ])->assertStatus(404);
});

it('clone strips the is_system flag even if the source is a system role', function (): void {
    $source = $this->postJson('/api/admin/roles', [
        'name' => 'super-admin',
        'guard_name' => 'admin',
        'level' => 100,
        'is_system' => true,
    ])->json('data');

    $clone = $this->postJson("/api/admin/roles/{$source['id']}/clone", [
        'name' => 'super_admin_copy',
    ])->assertCreated()->json('data');

    expect($clone['is_system'])->toBeFalse();
});

it('clone of a role without bindings returns an empty matrix', function (): void {
    $source = $this->postJson('/api/admin/roles', [
        'name' => 'empty_role',
        'guard_name' => 'admin',
    ])->json('data');

    $clone = $this->postJson("/api/admin/roles/{$source['id']}/clone", [
        'name' => 'empty_role_copy',
    ])->assertCreated()->json('data');

    $matrix = $this->getJson("/api/admin/roles/{$clone['id']}/permission-matrix");
    $matrix->assertOk()
        ->assertJsonPath('data.modules', []);
});
