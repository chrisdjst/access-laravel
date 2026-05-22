<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Eloquent\Repositories;

use Modularize\Access\Application\Ports\RoleRepository;
use Modularize\Access\Domain\Role\GuardName;
use Modularize\Access\Domain\Role\Role as DomainRole;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Laravel\Eloquent\Mappers\RoleMapper;
use Modularize\Access\Laravel\Models\Role as RoleEloquent;

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
}
