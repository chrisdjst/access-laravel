<?php

declare(strict_types=1);

use ModularizeRbac\Laravel\Models\Language;
use ModularizeRbac\Laravel\Models\Module;
use ModularizeRbac\Laravel\Models\ModulePermission;
use ModularizeRbac\Laravel\Models\Permission;
use ModularizeRbac\Laravel\Models\Role;

it('Module::factory() creates a saveable module', function (): void {
    $module = Module::factory()->create();

    expect($module->exists)->toBeTrue()
        ->and($module->id)->not->toBeEmpty()
        ->and($module->slug)->toStartWith('mod')
        ->and($module->is_active)->toBeTrue();
});

it('Module factory states change shape correctly', function (): void {
    $inactive = Module::factory()->inactive()->create();
    expect($inactive->is_active)->toBeFalse();

    $parent = Module::factory()->create();
    $child = Module::factory()->withParent($parent)->create();
    expect($child->root_module_id)->toBe($parent->id);

    $trashed = Module::factory()->trashed()->create();
    expect($trashed->deleted_at)->not->toBeNull();
});

it('Module::factory()->count(N) creates N modules', function (): void {
    $modules = Module::factory()->count(5)->create();
    expect($modules)->toHaveCount(5);
});

it('Role::factory() defaults to non-system admin guard', function (): void {
    $role = Role::factory()->create();
    expect($role->is_system)->toBeFalse()
        ->and($role->guard_name)->toBe('admin')
        ->and($role->level)->toBe(0)
        ->and($role->parent_role_id)->toBeNull();
});

it('Role factory states change shape correctly', function (): void {
    $sys = Role::factory()->system()->create();
    expect($sys->is_system)->toBeTrue()
        ->and($sys->level)->toBe(100);

    $parent = Role::factory()->create();
    $child = Role::factory()->withParent($parent)->create();
    expect($child->parent_role_id)->toBe($parent->id);

    $web = Role::factory()->forGuard('web')->create();
    expect($web->guard_name)->toBe('web');

    $tenant = '11111111-1111-1111-1111-111111111111';
    $tenantRole = Role::factory()->forTenant($tenant)->create();
    expect($tenantRole->organization_id)->toBe($tenant);
});

it('Permission factory creates a permission with module-prefixed name', function (): void {
    $perm = Permission::factory()->create();
    expect($perm->name)->toContain('.view')
        ->and($perm->module)->not->toBeEmpty()
        ->and($perm->guard_name)->toBe('admin');
});

it('Permission factory ->action(...) overrides the action suffix', function (): void {
    $perm = Permission::factory()->forModule('events')->action('create')->create();
    expect($perm->name)->toBe('events.create')
        ->and($perm->module)->toBe('events');
});

it('Language factory creates a language with a 2-letter code', function (): void {
    $lang = Language::factory()->create();
    expect(strlen($lang->code))->toBeGreaterThanOrEqual(2)
        ->and($lang->is_active)->toBeTrue()
        ->and($lang->is_default)->toBeFalse();
});

it('Language factory ->isDefault() sets the default flag', function (): void {
    $lang = Language::factory()->isDefault()->create();
    expect($lang->is_default)->toBeTrue();
});

it('ModulePermission factory defaults to view-only', function (): void {
    $perm = ModulePermission::factory()->create();
    expect($perm->is_reading_allowed)->toBeTrue()
        ->and($perm->is_writing_allowed)->toBeFalse()
        ->and($perm->is_delete_allowed)->toBeFalse();
});

it('ModulePermission factory ->allowAll() enables every flag', function (): void {
    $perm = ModulePermission::factory()->allowAll()->create();
    expect($perm->is_listing_allowed)->toBeTrue()
        ->and($perm->is_reading_allowed)->toBeTrue()
        ->and($perm->is_writing_allowed)->toBeTrue()
        ->and($perm->is_editing_allowed)->toBeTrue()
        ->and($perm->is_delete_allowed)->toBeTrue();
});
