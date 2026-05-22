<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Observers;

use Modularize\Access\Laravel\Models\ModulePermission;
use Modularize\Access\Laravel\Models\Permission;
use Modularize\Access\Laravel\Models\RoleModulePermission;

class RoleModulePermissionObserver
{
    /**
     * When a role is granted (or has its preset changed) a module, sync the
     * underlying Spatie permissions so that $user->can('events.view') etc.
     * keep working.
     */
    public function saved(RoleModulePermission $pivot): void
    {
        $pivot->loadMissing(['role', 'module', 'permission']);
        if (! $pivot->role || ! $pivot->module || ! $pivot->permission) {
            return;
        }

        $this->sync($pivot);
    }

    public function deleted(RoleModulePermission $pivot): void
    {
        $pivot->loadMissing(['role', 'module']);
        if (! $pivot->role || ! $pivot->module) {
            return;
        }

        // Revoke all permissions for this module from this role.
        $prefix = $pivot->module->slug.'.';
        $toRevoke = $pivot->role->permissions()
            ->where('name', 'like', $prefix.'%')
            ->pluck('name')
            ->all();

        foreach ($toRevoke as $name) {
            $pivot->role->revokePermissionTo($name);
        }
    }

    protected function sync(RoleModulePermission $pivot): void
    {
        $slug = $pivot->module->slug;
        $guard = $pivot->role->guard_name;
        $role = $pivot->role;

        $allowedActions = $pivot->permission->allowedActions();
        $managedActions = array_values(ModulePermission::FLAG_TO_ACTION); // view, list, create, update, delete

        $desired = [];
        foreach ($allowedActions as $action) {
            $name = $slug.'.'.$action;
            Permission::findOrCreate($name, $guard);
            $desired[] = $name;
        }

        // Only consider the 5 standard actions when syncing. Extras like
        // <slug>.manage / .sign / .approve / .import / .export are NOT
        // managed by this pivot and must be left alone.
        $managedNames = array_map(fn ($a) => $slug.'.'.$a, $managedActions);

        $current = $role->permissions()
            ->whereIn('name', $managedNames)
            ->pluck('name')
            ->all();

        $toRevoke = array_diff($current, $desired);
        foreach ($toRevoke as $name) {
            $role->revokePermissionTo($name);
        }

        $toGrant = array_diff($desired, $current);
        foreach ($toGrant as $name) {
            $role->givePermissionTo($name);
        }
    }
}
