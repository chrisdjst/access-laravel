<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('GET /api/admin/users/{id}/accessible-modules returns distinct modules the user can access', function (): void {
    // Seed two modules
    $events = $this->postJson('/api/admin/modules', [
        'slug' => 'events',
        'name' => 'Events',
        'sort_order' => 10,
    ])->json('data');
    $billing = $this->postJson('/api/admin/modules', [
        'slug' => 'billing',
        'name' => 'Billing',
        'sort_order' => 100,
    ])->json('data');

    // Seed a role and bind it to events (reading allowed)
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'web',
    ])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [
            ['module_id' => $events['id'], 'is_reading_allowed' => true],
            ['module_id' => $billing['id']], // no flags set → not accessible
        ],
    ])->assertOk();

    // Wire the role to a user via the pivot
    $userId = (string) Str::uuid();
    $role = RoleEloquent::query()->find($role['id']);
    DB::table('role_user')->insert([
        'role_id' => $role->id,
        'user_id' => $userId,
        'organization_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson("/api/admin/users/{$userId}/accessible-modules");
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'events');
});

it('returns an empty list when the user has no role assignments', function (): void {
    $userId = (string) Str::uuid();

    $this->getJson("/api/admin/users/{$userId}/accessible-modules")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
