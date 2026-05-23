<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Spatie;

use Modularize\Access\Application\Ports\ExternalPermissionGateway;
use Modularize\Access\Domain\Permission\PermissionName;
use Modularize\Access\Domain\Role\GuardName;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Laravel\Models\Permission as PermissionEloquent;
use Modularize\Access\Laravel\Models\Role as RoleEloquent;

/**
 * {@see ExternalPermissionGateway} adapter that replicates the
 * RBAC core's grant/revoke plan into Spatie's `role_has_permissions`
 * table so legacy `$user->can('events.view')` checks keep working.
 *
 * The class is only wired by the ServiceProvider when
 * `spatie/laravel-permission` is installed and
 * `config('access.spatie.enabled')` is true. Hosts that don't use
 * Spatie get the {@see NullExternalPermissionGateway} instead.
 *
 * Idempotency: Spatie's own `givePermissionTo` / `revokePermissionTo`
 * are idempotent on already-(granted|revoked) permissions, so
 * `applyPlan()` is safe to call multiple times with the same delta.
 */
final class SpatiePermissionGateway implements ExternalPermissionGateway
{
    public function permissionsHeldBy(Uuid $roleId, GuardName $guard): array
    {
        $role = RoleEloquent::query()->find($roleId->value);
        if ($role === null) {
            return [];
        }

        $names = [];
        foreach ($role->permissions as $permission) {
            $name = (string) $permission->name;
            try {
                $names[] = new PermissionName($name);
            } catch (\Throwable) {
                // Spatie permissions outside our `{slug}.{action}`
                // convention (legacy seeders, host extras) are
                // ignored here — the synchronizer only manages the
                // canonical action set anyway.
                continue;
            }
        }

        return $names;
    }

    public function applyPlan(Uuid $roleId, GuardName $guard, array $granted, array $revoked): void
    {
        $role = RoleEloquent::query()->find($roleId->value);
        if ($role === null) {
            return;
        }

        foreach ($granted as $name) {
            PermissionEloquent::findOrCreate($name->value, $guard->value);
            $role->givePermissionTo($name->value);
        }
        foreach ($revoked as $name) {
            $role->revokePermissionTo($name->value);
        }
    }
}
