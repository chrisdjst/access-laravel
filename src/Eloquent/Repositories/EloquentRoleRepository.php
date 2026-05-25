<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use ModularizeRbac\Core\Application\Ports\RoleRepository;
use ModularizeRbac\Core\Application\Role\RoleFilter;
use ModularizeRbac\Core\Application\Shared\PaginatedResult;
use ModularizeRbac\Core\Application\Shared\Pagination;
use ModularizeRbac\Core\Domain\Role\GuardName;
use ModularizeRbac\Core\Domain\Role\Role as DomainRole;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Eloquent\Mappers\RoleMapper;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

final class EloquentRoleRepository implements RoleRepository
{
    public function __construct(private readonly RoleMapper $mapper)
    {
    }

    public function find(Uuid $id): ?DomainRole
    {
        // SoftDeletes trait already filters trashed from the default query.
        $model = RoleEloquent::query()->find($id->value);

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function findIncludingTrashed(Uuid $id): ?DomainRole
    {
        $model = RoleEloquent::withTrashed()->find($id->value);

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function search(?GuardName $guard, ?Uuid $tenantId): array
    {
        $query = RoleEloquent::query();
        if ($guard !== null) {
            $query->where('guard_name', $guard->value);
        }
        if ($tenantId !== null) {
            $query->where('organization_id', $tenantId->value);
        }
        $query->orderByDesc('level')->orderBy('name');

        $domain = [];
        foreach ($query->get() as $model) {
            $domain[] = $this->mapper->toDomain($model);
        }

        return $domain;
    }

    public function save(DomainRole $role): void
    {
        // Use withTrashed() so saves on soft-deleted roles (e.g. mid-
        // restore) reach the persisted row.
        $existing = RoleEloquent::withTrashed()->find($role->id->value);
        $model = $this->mapper->toModel($role, $existing);

        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;
    }

    public function delete(DomainRole $role): void
    {
        // The port contract preserves the legacy HARD delete here.
        // SoftDeletes::delete() would only set deleted_at — we want a
        // true row removal, so forceDelete() explicitly.
        RoleEloquent::withTrashed()->whereKey($role->id->value)->forceDelete();
    }

    public function softDelete(DomainRole $role): void
    {
        // The aggregate has already set deletedAt; persist it via save.
        $this->save($role);
    }

    public function restore(DomainRole $role): void
    {
        // The aggregate cleared deletedAt; persist via save (deleted_at
        // becomes NULL on the row).
        $this->save($role);
    }

    public function findByName(string $name, GuardName $guard, ?Uuid $tenantId): ?DomainRole
    {
        $query = RoleEloquent::query()
            ->where('name', $name)
            ->where('guard_name', $guard->value);

        if ($tenantId === null) {
            $query->whereNull('organization_id');
        } else {
            $query->where('organization_id', $tenantId->value);
        }

        $model = $query->first();

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function searchPaginated(RoleFilter $filter, Pagination $pagination): PaginatedResult
    {
        $query = RoleEloquent::query();

        if ($filter->guard !== null) {
            $query->where('guard_name', $filter->guard->value);
        }
        if ($filter->tenantPresent) {
            if ($filter->tenantId === null) {
                $query->whereNull('organization_id');
            } else {
                $query->where('organization_id', $filter->tenantId->value);
            }
        }
        if ($filter->isSystem !== null) {
            $query->where('is_system', $filter->isSystem);
        }
        if ($filter->levelMin !== null) {
            $query->where('level', '>=', $filter->levelMin);
        }
        if ($filter->levelMax !== null) {
            $query->where('level', '<=', $filter->levelMax);
        }
        if ($filter->hasParent !== null) {
            if ($filter->hasParent) {
                $query->whereNotNull('parent_role_id');
            } else {
                $query->whereNull('parent_role_id');
            }
        }

        $total = (int) (clone $query)->count();

        $models = $query
            ->orderByDesc('level')
            ->orderBy('name')
            ->offset($pagination->offset)
            ->limit($pagination->limit)
            ->get();

        $items = [];
        foreach ($models as $model) {
            $items[] = $this->mapper->toDomain($model);
        }

        return new PaginatedResult($items, $total, $pagination);
    }

    public function resolveAncestors(Uuid $roleId): array
    {
        // Single batched walk: every iteration asks for parent_role_id
        // of every role we've already discovered. Worst case is one
        // query per hierarchy level instead of one per role.
        // No recursive CTE here — SQLite's `WITH RECURSIVE` is fine in
        // 3.8.3+ but the per-driver branching costs more than it saves
        // for the depths the package targets (≤ 10).
        $ancestors = [];
        $visited = [$roleId->value => true];
        $frontier = [$roleId->value];

        while ($frontier !== []) {
            /** @var array<string, string|null> $rows */
            $rows = RoleEloquent::query()
                ->whereIn('id', $frontier)
                ->pluck('parent_role_id', 'id')
                ->all();

            $nextFrontier = [];
            foreach ($rows as $parentRaw) {
                if ($parentRaw === null) {
                    continue;
                }
                $parentStr = (string) $parentRaw;
                if (isset($visited[$parentStr])) {
                    continue; // cycle break OR sibling joining same parent
                }
                $visited[$parentStr] = true;
                $ancestors[] = new Uuid($parentStr);
                $nextFrontier[] = $parentStr;
            }
            $frontier = $nextFrontier;
        }

        return $ancestors;
    }
}
