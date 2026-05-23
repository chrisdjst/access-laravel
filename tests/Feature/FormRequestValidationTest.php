<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('SyncRoleModules accepts an empty modules array (drops all bindings)', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [],
    ])->assertOk();
});

it('SyncRoleModules returns 422 when modules.* is a scalar', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => ['not-an-object', 42],
    ])->assertStatus(422);
});

it('SyncRoleModules returns 422 when module_id is missing', function (): void {
    $role = $this->postJson('/api/admin/roles', [
        'name' => 'editor',
        'guard_name' => 'admin',
    ])->json('data');

    $this->putJson("/api/admin/roles/{$role['id']}/modules", [
        'modules' => [['is_reading_allowed' => true]],
    ])->assertStatus(422);
});

it('StoreLanguageRequest accepts any code by default', function (): void {
    $this->postJson('/api/admin/languages', [
        'code' => 'xx_YY',
        'name' => 'Xanadu',
    ])->assertCreated();
});

it('StoreLanguageRequest rejects code not in access.allowed_locales when set', function (): void {
    config()->set('access.allowed_locales', ['pt_BR', 'en']);

    $this->postJson('/api/admin/languages', [
        'code' => 'fr',
        'name' => 'Français',
    ])->assertStatus(422);

    $this->postJson('/api/admin/languages', [
        'code' => 'pt_BR',
        'name' => 'Português',
    ])->assertCreated();
});

it('UpdateLanguageRequest rejects code not in access.allowed_locales when set', function (): void {
    $pt = $this->postJson('/api/admin/languages', [
        'code' => 'pt_BR',
        'name' => 'Português',
    ])->json('data');

    config()->set('access.allowed_locales', ['pt_BR', 'en']);

    $this->putJson("/api/admin/languages/{$pt['id']}", [
        'code' => 'fr',
    ])->assertStatus(422);
});
