<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use ModularizeRbac\Core\Application\Ports\RoleRepository;
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
        $model = RoleEloquent::query()->find($id->value);

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
        $existing = RoleEloquent::query()->find($role->id->value);
        $model = $this->mapper->toModel($role, $existing);

        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;
    }

    public function delete(DomainRole $role): void
    {
        RoleEloquent::query()->whereKey($role->id->value)->delete();
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
