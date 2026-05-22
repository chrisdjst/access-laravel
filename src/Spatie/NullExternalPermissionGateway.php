<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Spatie;

use Modularize\Access\Application\Ports\ExternalPermissionGateway;
use Modularize\Access\Domain\Permission\PermissionName;
use Modularize\Access\Domain\Role\GuardName;
use Modularize\Access\Domain\Shared\Uuid;

/**
 * Null-object implementation of {@see ExternalPermissionGateway} —
 * useful for hosts that do not (yet) integrate with Spatie's
 * permission table. Reports "role holds no external permissions" and
 * silently ignores grant/revoke plans.
 *
 * PR 5 introduces a real `SpatiePermissionGateway` and the
 * ServiceProvider switches the binding based on whether
 * `spatie/laravel-permission` is installed and enabled.
 */
final class NullExternalPermissionGateway implements ExternalPermissionGateway
{
    public function permissionsHeldBy(Uuid $roleId, GuardName $guard): array
    {
        return [];
    }

    public function applyPlan(Uuid $roleId, GuardName $guard, array $granted, array $revoked): void
    {
        // intentional noop
        unset($roleId, $guard, $granted, $revoked);
    }
}
