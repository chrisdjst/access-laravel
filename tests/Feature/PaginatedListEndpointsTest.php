<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('GET /modules without params returns the full list with meta.count only', function (): void {
    $this->postJson('/api/admin/modules', ['slug' => 'a', 'name' => 'A'])->assertCreated();
    $this->postJson('/api/admin/modules', ['slug' => 'b', 'name' => 'B'])->assertCreated();

    $response = $this->getJson('/api/admin/modules');
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.count', 2)
        ->assertJsonMissingPath('meta.total')
        ->assertJsonMissingPath('meta.limit');
});

it('GET /modules?limit=&offset= returns a windowed slice with meta', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/admin/modules', ['slug' => 'mod'.$i, 'name' => 'M'.$i])->assertCreated();
    }

    $response = $this->getJson('/api/admin/modules?limit=2&offset=1');
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.limit', 2)
        ->assertJsonPath('meta.offset', 1);
});

it('GET /modules?is_active=false filters out active modules', function (): void {
    $this->postJson('/api/admin/modules', ['slug' => 'active', 'name' => 'A'])->assertCreated();
    $this->postJson('/api/admin/modules', ['slug' => 'inactive', 'name' => 'I', 'is_active' => false])->assertCreated();

    $response = $this->getJson('/api/admin/modules?is_active=false');
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'inactive')
        ->assertJsonPath('meta.total', 1);
});

it('GET /modules?slug_like= filters by case-insensitive substring', function (): void {
    $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'E'])->assertCreated();
    $this->postJson('/api/admin/modules', ['slug' => 'billing', 'name' => 'B'])->assertCreated();

    $response = $this->getJson('/api/admin/modules?slug_like=EVENT');
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'events');
});

it('GET /modules?limit=1001 returns 422', function (): void {
    $this->getJson('/api/admin/modules?limit=1001')->assertStatus(422);
});

it('GET /roles without pagination params returns full list with meta.count only', function (): void {
    $this->postJson('/api/admin/roles', ['name' => 'r1', 'guard_name' => 'admin'])->assertCreated();
    $this->postJson('/api/admin/roles', ['name' => 'r2', 'guard_name' => 'admin'])->assertCreated();

    $response = $this->getJson('/api/admin/roles');
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.count', 2)
        ->assertJsonMissingPath('meta.total');
});

it('GET /roles?limit= returns paginated envelope', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/admin/roles', ['name' => 'role'.$i, 'guard_name' => 'admin', 'level' => $i])->assertCreated();
    }

    $response = $this->getJson('/api/admin/roles?limit=2&offset=1');
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.limit', 2)
        ->assertJsonPath('meta.offset', 1);
});

it('GET /roles?level_min=&level_max= keeps only roles in the band', function (): void {
    $this->postJson('/api/admin/roles', ['name' => 'low', 'guard_name' => 'admin', 'level' => 5])->assertCreated();
    $this->postJson('/api/admin/roles', ['name' => 'mid', 'guard_name' => 'admin', 'level' => 50])->assertCreated();
    $this->postJson('/api/admin/roles', ['name' => 'hi', 'guard_name' => 'admin', 'level' => 100])->assertCreated();

    $response = $this->getJson('/api/admin/roles?level_min=10&level_max=90');
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'mid')
        ->assertJsonPath('meta.total', 1);
});

it('GET /roles?has_parent=true returns only roles with a parent_role_id', function (): void {
    $parent = $this->postJson('/api/admin/roles', ['name' => 'parent_p', 'guard_name' => 'admin'])->json('data');
    $this->postJson('/api/admin/roles', ['name' => 'child_p', 'guard_name' => 'admin', 'parent_role_id' => $parent['id']])->assertCreated();

    $response = $this->getJson('/api/admin/roles?has_parent=true');
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'child_p');
});

it('GET /roles?level_min=50&level_max=10 returns 422 (inverted band)', function (): void {
    $this->getJson('/api/admin/roles?level_min=50&level_max=10')->assertStatus(422);
});
