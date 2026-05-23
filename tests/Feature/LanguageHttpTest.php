<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
    config()->set('access.middleware', ['api']);
});

it('POST /languages creates a language', function (): void {
    $response = $this->postJson('/api/admin/languages', [
        'code' => 'pt_BR',
        'name' => 'Português',
        'is_default' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'pt_BR')
        ->assertJsonPath('data.is_default', true);
});

it('PUT /languages/{id}/default swaps the default', function (): void {
    $pt = $this->postJson('/api/admin/languages', ['code' => 'pt_BR', 'name' => 'PT', 'is_default' => true])->json('data');
    $en = $this->postJson('/api/admin/languages', ['code' => 'en', 'name' => 'EN'])->json('data');

    $this->putJson("/api/admin/languages/{$en['id']}/default")
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    $this->getJson("/api/admin/languages/{$pt['id']}")
        ->assertOk()
        ->assertJsonPath('data.is_default', false);
});

it('rejects deletion of the default language with 422', function (): void {
    $pt = $this->postJson('/api/admin/languages', ['code' => 'pt_BR', 'name' => 'PT', 'is_default' => true])->json('data');

    // Anonymous-friendly authorization bypass is in place; the
    // use-case still rejects because the language is the default.
    $this->deleteJson("/api/admin/languages/{$pt['id']}")->assertStatus(422);
});

it('deletes a non-default language', function (): void {
    $this->postJson('/api/admin/languages', ['code' => 'pt_BR', 'name' => 'PT', 'is_default' => true]);
    $en = $this->postJson('/api/admin/languages', ['code' => 'en', 'name' => 'EN'])->json('data');

    $this->deleteJson("/api/admin/languages/{$en['id']}")->assertNoContent();
});
