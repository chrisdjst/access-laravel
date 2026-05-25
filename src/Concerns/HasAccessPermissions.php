<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ModularizeRbac\Core\Domain\Module\ModulePermission as DomainModulePermission;
use ModularizeRbac\Core\Domain\Module\ModuleSlug;
use ModularizeRbac\Core\Domain\Permission\PermissionName;
use ModularizeRbac\Core\Domain\RoleModulePermission\PermissionInheritanceResolver;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Authorization\ModuleHierarchyIndex;
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
     *
     * When `config('access.inheritance.enabled')` is true, an
     * ability that has no direct binding falls back to the module's
     * ancestor chain via {@see PermissionInheritanceResolver}.
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

        $directRoleIds = $this->rbacRoles()->pluck('roles.id')->all();
        if ($directRoleIds === []) {
            return false;
        }

        $roleIds = $this->expandRoleIdsWithAncestors($directRoleIds);

        $bindings = RoleModulePermission::query()
            ->with(['module', 'permission'])
            ->whereIn('role_id', $roleIds)
            ->get();

        if ((bool) config('access.inheritance.enabled', false)) {
            return $this->resolveWithInheritance($name, $bindings);
        }

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

    /**
     * Walk the module hierarchy via {@see PermissionInheritanceResolver}.
     * Pre-loads the full module table into slug→ModuleSlug indices so
     * the resolver's parent lookup is O(1) per step.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, RoleModulePermission>  $bindings
     */
    private function resolveWithInheritance(PermissionName $name, $bindings): bool
    {
        // Bindings grouped by the module SLUG they target. The
        // resolver consults flag sets per slug; each role binding
        // contributes one flag set under its module's slug.
        $flagsBySlug = [];
        foreach ($bindings as $binding) {
            if ($binding->module === null || $binding->permission === null) {
                continue;
            }
            $slug = (string) $binding->module->slug;
            $flagsBySlug[$slug][] = $this->toDomainFlags($binding->permission);
        }

        // Parent lookup: go through the per-request scoped index,
        // which itself reads `ModuleRepository::allActiveTree()` —
        // cache-fronted by CachedModuleRepository (v2.3.0). First
        // call within a request pays a memoize cost; subsequent
        // canAccess() calls are O(1) per resolver step.
        /** @var ModuleHierarchyIndex $index */
        $index = app(ModuleHierarchyIndex::class);

        $resolver = new PermissionInheritanceResolver();

        return $resolver->isAllowed(
            $name,
            flagsForSlug: static fn (ModuleSlug $s): array => $flagsBySlug[$s->value] ?? [],
            parentOfSlug: static fn (ModuleSlug $s): ?ModuleSlug => $index->parentOf($s),
        );
    }

    /**
     * Construct a domain ModulePermission carrying just the boolean
     * flags the resolver inspects. Other fields (timestamps, audit
     * ids) don't influence the inheritance answer, so dummies are
     * passed.
     */
    private function toDomainFlags(ModulePermission $row): DomainModulePermission
    {
        return DomainModulePermission::create(
            id: new Uuid((string) $row->id),
            isListingAllowed: (bool) $row->is_listing_allowed,
            isReadingAllowed: (bool) $row->is_reading_allowed,
            isWritingAllowed: (bool) $row->is_writing_allowed,
            isEditingAllowed: (bool) $row->is_editing_allowed,
            isDeleteAllowed: (bool) $row->is_delete_allowed,
            createdBy: null,
            clock: new \ModularizeRbac\Laravel\Persistence\SystemClock(),
        );
    }

    /**
     * Expand the user's directly-assigned role ids by walking each
     * role's `parent_role_id` chain. Ancestor bindings are honored
     * the same as direct bindings — a role inherits the matrix of
     * every ancestor.
     *
     * Walks defensively: cycles short-circuit on the visited set,
     * orphan pointers stop the walk silently.
     *
     * @param  list<mixed>  $directRoleIds
     * @return list<string>
     */
    private function expandRoleIdsWithAncestors(array $directRoleIds): array
    {
        $visited = [];
        $result = [];
        foreach ($directRoleIds as $id) {
            $strId = (string) $id;
            if (! isset($visited[$strId])) {
                $visited[$strId] = true;
                $result[] = $strId;
            }
        }

        // Walk the parent chain in BATCHES: each round queries every
        // role added in the previous round (or the direct list on the
        // first round) in a single `whereIn`. The loop terminates when
        // no new parents appear — bounded by the max hierarchy depth,
        // so worst-case is `depth` queries instead of `nRoles*depth`
        // individual lookups.
        $frontier = $result;
        while ($frontier !== []) {
            $parents = \ModularizeRbac\Laravel\Models\Role::query()
                ->whereIn('id', $frontier)
                ->whereNotNull('parent_role_id')
                ->pluck('parent_role_id', 'id')
                ->all();

            $nextFrontier = [];
            foreach ($parents as $parentId) {
                $parentStr = (string) $parentId;
                if (isset($visited[$parentStr])) {
                    continue; // cycle break + dedupe across siblings
                }
                $visited[$parentStr] = true;
                $result[] = $parentStr;
                $nextFrontier[] = $parentStr;
            }
            $frontier = $nextFrontier;
        }

        return $result;
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
