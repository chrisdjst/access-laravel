<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use Illuminate\Database\ConnectionInterface;
use ModularizeRbac\Core\Application\Ports\UserRoleResolver;
use ModularizeRbac\Core\Domain\Shared\Uuid;

/**
 * {@see UserRoleResolver} adapter that reads the `role_user` pivot
 * introduced by the v2.0 migration. Returns the distinct list of
 * role ids the user holds, ignoring tenant scoping at this layer —
 * tenant filtering is done by callers consulting the
 * {@see \ModularizeRbac\Core\Application\Ports\TenantContext} port.
 */
final class EloquentUserRoleResolver implements UserRoleResolver
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function roleIdsFor(Uuid $userId): array
    {
        $rows = $this->db->table('role_user')
            ->where('user_id', $userId->value)
            ->distinct()
            ->pluck('role_id');

        $ids = [];
        foreach ($rows as $value) {
            $ids[] = new Uuid((string) $value);
        }

        return $ids;
    }
}
