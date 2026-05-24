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
        $ancestors = [];
        $visited = [$roleId->value => true];
        $currentId = $roleId->value;

        while (true) {
            $parentId = RoleEloquent::query()
                ->whereKey($currentId)
                ->value('parent_role_id');

            if ($parentId === null) {
                break;
            }
            $parentStr = (string) $parentId;
            if (isset($visited[$parentStr])) {
                break; // cycle break
            }
            $visited[$parentStr] = true;

            // Confirm the parent still exists; orphan pointer stops the walk.
            if (! RoleEloquent::query()->whereKey($parentStr)->exists()) {
                break;
            }

            $ancestors[] = new Uuid($parentStr);
            $currentId = $parentStr;
        }

        return $ancestors;
    }
}
