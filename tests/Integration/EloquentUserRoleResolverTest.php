<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Ports\UserRoleResolver;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

function seedRoleForResolverTest(string $name = 'editor'): RoleEloquent
{
    $role = new RoleEloquent();
    $role->id = (string) Str::uuid();
    $role->name = $name;
    $role->display_name = ucfirst($name);
    $role->guard_name = 'web';
    $role->organization_id = null;
    $role->level = 50;
    $role->is_system = false;
    $role->save();

    return $role;
}

it('returns empty list when user has no role assignments', function (): void {
    /** @var UserRoleResolver $resolver */
    $resolver = $this->app->make(UserRoleResolver::class);

    $ids = $resolver->roleIdsFor(new Uuid('11111111-1111-1111-1111-111111111111'));

    expect($ids)->toBe([]);
});

it('returns assigned role ids reading from role_user pivot', function (): void {
    $editor = seedRoleForResolverTest('editor');
    $viewer = seedRoleForResolverTest('viewer');
    $userId = (string) Str::uuid();

    DB::table('role_user')->insert([
        ['role_id' => $editor->id, 'user_id' => $userId, 'organization_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ['role_id' => $viewer->id, 'user_id' => $userId, 'organization_id' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    /** @var UserRoleResolver $resolver */
    $resolver = $this->app->make(UserRoleResolver::class);

    $ids = $resolver->roleIdsFor(new Uuid($userId));
    $values = array_map(fn ($id) => $id->value, $ids);

    expect($values)->toHaveCount(2)
        ->and($values)->toContain($editor->id)
        ->and($values)->toContain($viewer->id);
});

it('returns distinct role ids even when pivot has multiple tenant rows for the same role', function (): void {
    $editor = seedRoleForResolverTest('editor');
    $userId = (string) Str::uuid();
    $tenantA = (string) Str::uuid();
    $tenantB = (string) Str::uuid();

    DB::table('role_user')->insert([
        ['role_id' => $editor->id, 'user_id' => $userId, 'organization_id' => $tenantA, 'created_at' => now(), 'updated_at' => now()],
        ['role_id' => $editor->id, 'user_id' => $userId, 'organization_id' => $tenantB, 'created_at' => now(), 'updated_at' => now()],
    ]);

    /** @var UserRoleResolver $resolver */
    $resolver = $this->app->make(UserRoleResolver::class);

    $ids = $resolver->roleIdsFor(new Uuid($userId));

    expect($ids)->toHaveCount(1)
        ->and($ids[0]->value)->toBe($editor->id);
});

it('does not leak assignments belonging to other users', function (): void {
    $editor = seedRoleForResolverTest('editor');
    $aliceId = (string) Str::uuid();
    $bobId = (string) Str::uuid();

    DB::table('role_user')->insert([
        'role_id' => $editor->id,
        'user_id' => $aliceId,
        'organization_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    /** @var UserRoleResolver $resolver */
    $resolver = $this->app->make(UserRoleResolver::class);

    expect($resolver->roleIdsFor(new Uuid($aliceId)))->toHaveCount(1)
        ->and($resolver->roleIdsFor(new Uuid($bobId)))->toBe([]);
});
