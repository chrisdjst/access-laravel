<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Spatie;

use Illuminate\Database\ConnectionInterface;
use ModularizeRbac\Core\Application\Ports\ExternalPermissionGateway;
use ModularizeRbac\Core\Domain\Permission\PermissionName;
use ModularizeRbac\Core\Domain\Role\GuardName;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Models\Permission as PermissionEloquent;
use Throwable;

/**
 * Pivot-table sync gateway. Replicates the RBAC core's grant/revoke
 * plan into the `role_has_permissions` pivot so any host that uses
 * Spatie's `HasRoles` trait on its User model continues to resolve
 * `$user->can('events.view')` correctly.
 *
 * v2.0 rewrite: the gateway no longer relies on Spatie's model
 * inheritance or APIs. It writes the pivot rows directly via the
 * connection's query builder, identifying permissions by their
 * (name, guard) tuple in the package's own `permissions` table. This
 * means the gateway works as long as the schema this package owns is
 * present — Spatie itself is only required because that's the
 * ecosystem the pivot is meant to feed.
 *
 * Idempotency: `insertOrIgnore` for grants, conditional `delete` for
 * revokes — safe to call repeatedly with the same delta.
 */
final class SpatiePermissionGateway implements ExternalPermissionGateway
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function permissionsHeldBy(Uuid $roleId, GuardName $guard): array
    {
        $rows = $this->db->table('role_has_permissions')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('role_has_permissions.role_id', $roleId->value)
            ->where('permissions.guard_name', $guard->value)
            ->pluck('permissions.name');

        $names = [];
        foreach ($rows as $name) {
            try {
                $names[] = new PermissionName((string) $name);
            } catch (Throwable) {
                // Permissions outside the `{slug}.{action}` convention
                // (legacy seeders, host extras) are ignored — the
                // synchronizer only manages the canonical action set.
                continue;
            }
        }

        return $names;
    }

    public function applyPlan(Uuid $roleId, GuardName $guard, array $granted, array $revoked): void
    {
        foreach ($granted as $name) {
            $permission = PermissionEloquent::findOrCreate($name->value, $guard->value);
            $this->db->table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permission->id,
                'role_id' => $roleId->value,
            ]);
        }

        foreach ($revoked as $name) {
            $permission = PermissionEloquent::query()
                ->where('name', $name->value)
                ->where('guard_name', $guard->value)
                ->first();
            if ($permission === null) {
                continue;
            }
            $this->db->table('role_has_permissions')
                ->where('role_id', $roleId->value)
                ->where('permission_id', $permission->id)
                ->delete();
        }
    }
}
