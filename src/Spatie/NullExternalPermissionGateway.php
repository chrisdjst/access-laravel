<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Spatie;

use ModularizeRbac\Core\Application\Ports\ExternalPermissionGateway;
use ModularizeRbac\Core\Domain\Permission\PermissionName;
use ModularizeRbac\Core\Domain\Role\GuardName;
use ModularizeRbac\Core\Domain\Shared\Uuid;

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
