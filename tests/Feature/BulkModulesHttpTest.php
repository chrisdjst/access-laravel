<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('POST /api/admin/modules/bulk creates every entry and returns 201 with a data collection', function (): void {
    $response = $this->postJson('/api/admin/modules/bulk', [
        'modules' => [
            ['slug' => 'events', 'name' => 'Events', 'sort_order' => 10],
            ['slug' => 'billing', 'name' => 'Billing'],
            ['slug' => 'reports', 'name' => 'Reports', 'icon' => 'chart'],
        ],
    ]);

    $response->assertCreated();
    $slugs = array_map(fn ($m) => $m['slug'], $response->json('data'));
    expect($slugs)->toContain('events', 'billing', 'reports')
        ->and(count($response->json('data')))->toBe(3);
});

it('POST bulk rolls back the whole batch when one slug collides', function (): void {
    $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->assertCreated();

    $response = $this->postJson('/api/admin/modules/bulk', [
        'modules' => [
            ['slug' => 'billing', 'name' => 'Billing'],
            ['slug' => 'events', 'name' => 'Duplicate'],
            ['slug' => 'reports', 'name' => 'Reports'],
        ],
    ]);

    $response->assertStatus(422);

    $list = $this->getJson('/api/admin/modules')->json('data');
    $slugs = array_map(fn ($m) => $m['slug'], $list);
    expect($slugs)->toBe(['events']);
});

it('POST bulk returns 422 on empty payload', function (): void {
    $this->postJson('/api/admin/modules/bulk', ['modules' => []])->assertStatus(422);
});

it('POST bulk returns 422 on duplicate slug within payload', function (): void {
    $this->postJson('/api/admin/modules/bulk', [
        'modules' => [
            ['slug' => 'events', 'name' => 'Events 1'],
            ['slug' => 'events', 'name' => 'Events 2'],
        ],
    ])->assertStatus(422);
});

it('DELETE /api/admin/modules/bulk soft-deletes every id in the payload', function (): void {
    $a = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->json('data');
    $b = $this->postJson('/api/admin/modules', ['slug' => 'billing', 'name' => 'Billing'])->json('data');

    $this->deleteJson('/api/admin/modules/bulk', [
        'ids' => [$a['id'], $b['id']],
    ])->assertNoContent();

    // soft-deleted rows are filtered out of the active tree listing
    $list = $this->getJson('/api/admin/modules')->json('data');
    expect($list)->toBe([]);

    // ...but the rows still exist in storage, marked deleted
    $rowsLeft = DB::table('modules')->whereNotNull('deleted_at')->count();
    expect($rowsLeft)->toBe(2);
});

it('DELETE bulk rolls back when one id is missing', function (): void {
    $a = $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->json('data');

    $this->deleteJson('/api/admin/modules/bulk', [
        'ids' => [$a['id'], '99999999-9999-9999-9999-999999999999'],
    ])->assertStatus(404);

    // Original module still active
    $list = $this->getJson('/api/admin/modules')->json('data');
    expect($list)->toHaveCount(1)
        ->and($list[0]['slug'])->toBe('events');
});

it('DELETE bulk returns 422 on empty payload', function (): void {
    $this->deleteJson('/api/admin/modules/bulk', ['ids' => []])->assertStatus(422);
});
