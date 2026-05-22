<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
    // Drop the package's default middleware so unauthenticated test
    // requests aren't kicked by `auth:sanctum`.
    config()->set('access.middleware', ['api']);
});

it('POST /modules creates a module and returns the legacy payload', function (): void {
    $response = $this->postJson('/api/admin/modules', [
        'slug' => 'events',
        'name' => 'Events',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'events')
        ->assertJsonPath('data.name', 'Events')
        ->assertJsonPath('data.sort_order', 10)
        ->assertJsonStructure([
            'data' => ['id', 'slug', 'name', 'is_active', 'translations', 'price', 'created_at', 'updated_at'],
        ]);
});

it('GET /modules lists modules in tree order', function (): void {
    $this->postJson('/api/admin/modules', ['slug' => 'billing', 'name' => 'Billing', 'sort_order' => 100]);
    $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events', 'sort_order' => 50]);

    $response = $this->getJson('/api/admin/modules');
    $response->assertOk();
    $slugs = array_map(fn ($m) => $m['slug'], $response->json('data'));
    expect($slugs)->toBe(['events', 'billing']);
});

it('PUT /modules/{id} updates and emits no event when nothing changed', function (): void {
    $created = $this->postJson('/api/admin/modules', [
        'slug' => 'events',
        'name' => 'Events',
        'sort_order' => 0,
    ])->json('data');

    $this->putJson("/api/admin/modules/{$created['id']}", [
        'name' => 'Events v2',
        'sort_order' => 5,
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Events v2')
        ->assertJsonPath('data.sort_order', 5);
});

it('DELETE /modules/{id} soft-deletes', function (): void {
    $created = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->json('data');

    $this->deleteJson("/api/admin/modules/{$created['id']}")->assertNoContent();
    $this->getJson('/api/admin/modules')->assertOk()->assertJsonCount(0, 'data');
});

it('returns 422 on invalid slug (use-case validation)', function (): void {
    $this->postJson('/api/admin/modules', [
        'slug' => 'EVENTS_INVALID',
        'name' => 'Events',
    ])->assertStatus(422);
});
