<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('GET /api/admin/audit lists audit entries newest-first', function (): void {
    $create = $this->app->make(CreateModule::class);
    $create->execute(new CreateModuleInput('events', 'Events', null, null, null));
    $create->execute(new CreateModuleInput('billing', 'Billing', null, null, null));

    $response = $this->getJson('/api/admin/audit');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.event', 'module.created')
        ->assertJsonPath('meta.total', 2);
});

it('GET /api/admin/audit?event=module.created filters by event', function (): void {
    $create = $this->app->make(CreateModule::class);
    $create->execute(new CreateModuleInput('events', 'Events', null, null, null));

    $response = $this->getJson('/api/admin/audit?event=module.created');
    $response->assertOk()->assertJsonCount(1, 'data');

    $response = $this->getJson('/api/admin/audit?event=role.permissions_changed');
    $response->assertOk()->assertJsonCount(0, 'data');
});

it('GET /api/admin/audit honors limit + offset', function (): void {
    $create = $this->app->make(CreateModule::class);
    $create->execute(new CreateModuleInput('a', 'A', null, null, null));
    $create->execute(new CreateModuleInput('b', 'B', null, null, null));
    $create->execute(new CreateModuleInput('c', 'C', null, null, null));

    $response = $this->getJson('/api/admin/audit?limit=2&offset=1');
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.limit', 2)
        ->assertJsonPath('meta.offset', 1);
});

it('returns 422 on invalid limit', function (): void {
    $this->getJson('/api/admin/audit?limit=9999')->assertStatus(422);
});
