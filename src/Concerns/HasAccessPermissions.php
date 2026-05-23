<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ModularizeRbac\Core\Domain\Permission\PermissionName;
use ModularizeRbac\Laravel\Models\ModulePermission;
use ModularizeRbac\Laravel\Models\Role;
use ModularizeRbac\Laravel\Models\RoleModulePermission;
use Throwable;

/**
 * Host User model adds this trait to gain `canAccess('events.view')`
 * + a `rbacRoles()` relation pointing at the package's `roles` table
 * via the `role_user` pivot. Together with the `Gate::before`
 * callback the {@see \ModularizeRbac\Laravel\AccessServiceProvider}
 * registers, `$user->can('events.view')` works without Spatie.
 *
 * Usage:
 *   use ModularizeRbac\Laravel\Concerns\HasAccessPermissions;
 *   class User extends Authenticatable {
 *       use HasAccessPermissions;
 *   }
 *
 * The trait is intentionally minimal — no role assignment helpers,
 * no permission-grant DSL. Use the package's HTTP API + use-cases
 * for those flows.
 *
 * @phpstan-require-extends Model
 */
trait HasAccessPermissions
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function rbacRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    /**
     * Does the user hold any role whose binding for the requested
     * permission's module covers the requested action? The ability
     * must be in canonical `{slug}.{action}` form — anything outside
     * the five managed actions (list/view/create/update/delete) is
     * not honored here.
     *
     * This is the function `Gate::before` delegates to so
     * `$user->can('events.view')` short-circuits before any policy
     * resolution.
     */
    public function canAccess(string $ability): bool
    {
        try {
            $name = new PermissionName($ability);
        } catch (Throwable) {
            // Not logged on purpose: this method is invoked by
            // `Gate::before` for EVERY `$user->can(...)` call across
            // the whole host app, including abilities that don't
            // follow the `{slug}.{action}` convention (e.g.
            // `view-dashboard`, `update-user-profile`). Treating them
            // as "this package doesn't grant that" — i.e. returning
            // false so Laravel's Gate continues — is the correct
            // semantic. Logging would flood the request log.
            return false;
        }

        $roleIds = $this->rbacRoles()->pluck('roles.id')->all();
        if ($roleIds === []) {
            return false;
        }

        $bindings = RoleModulePermission::query()
            ->with(['module', 'permission'])
            ->whereIn('role_id', $roleIds)
            ->get();

        foreach ($bindings as $binding) {
            if ($binding->module === null || $binding->permission === null) {
                continue;
            }
            if ($binding->module->slug !== $name->moduleSlug->value) {
                continue;
            }
            if ($this->actionAllowed($binding->permission, $name->action)) {
                return true;
            }
        }

        return false;
    }

    private function actionAllowed(ModulePermission $permission, string $action): bool
    {
        return match ($action) {
            'list' => (bool) $permission->is_listing_allowed,
            'view' => (bool) $permission->is_reading_allowed,
            'create' => (bool) $permission->is_writing_allowed,
            'update' => (bool) $permission->is_editing_allowed,
            'delete' => (bool) $permission->is_delete_allowed,
            default => false,
        };
    }
}
