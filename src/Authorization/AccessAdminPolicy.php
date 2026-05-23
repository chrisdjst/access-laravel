<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Default policy for the package's `admin.*` abilities.
 *
 * The package's use-cases check abilities like `admin.modules.view`,
 * `admin.audit.view`, etc. through the
 * {@see \ModularizeRbac\Core\Application\Ports\Authorizer} port. Without
 * a wired policy each host has to declare these abilities one by one
 * via `Gate::define()`. This policy collapses them into a single
 * mapping: an authenticated user is granted `admin.X` iff their
 * `canAccess('admin.X')` returns true (i.e. they hold a role with a
 * binding for the corresponding admin module).
 *
 * Hosts that want a different mapping point `config('access.policies.admin')`
 * at their own class — the ServiceProvider will register that one
 * instead.
 */
final class AccessAdminPolicy
{
    /**
     * Laravel's Gate invokes `before()` before any per-method check.
     * Returning true short-circuits with allow, false denies, null
     * defers to other gates / policies.
     */
    public function before(?Authenticatable $user, string $ability): ?bool
    {
        if (! str_starts_with($ability, 'admin.')) {
            return null;
        }
        if ($user === null) {
            return false;
        }
        if (! method_exists($user, 'canAccess')) {
            return null;
        }

        return $user->canAccess($ability) ? true : null;
    }
}
