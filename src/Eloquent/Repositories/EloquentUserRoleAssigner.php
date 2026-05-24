<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use Illuminate\Database\ConnectionInterface;
use ModularizeRbac\Core\Application\Ports\UserRoleAssigner;
use ModularizeRbac\Core\Domain\Shared\Uuid;

/**
 * Eloquent-backed {@see UserRoleAssigner}. Inserts into the
 * `role_user` pivot introduced by the v2.0 migration, treating the
 * (role_id, user_id, organization_id) tuple as the natural key.
 *
 * Idempotent: an existing row with the same tuple is left untouched
 * instead of duplicated.
 */
final class EloquentUserRoleAssigner implements UserRoleAssigner
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function assign(Uuid $roleId, Uuid $userId, ?Uuid $tenantId = null): void
    {
        $query = $this->db->table('role_user')
            ->where('role_id', $roleId->value)
            ->where('user_id', $userId->value);

        if ($tenantId === null) {
            $query->whereNull('organization_id');
        } else {
            $query->where('organization_id', $tenantId->value);
        }

        if ($query->exists()) {
            return;
        }

        $now = (string) now();
        $this->db->table('role_user')->insert([
            'role_id' => $roleId->value,
            'user_id' => $userId->value,
            'organization_id' => $tenantId?->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
