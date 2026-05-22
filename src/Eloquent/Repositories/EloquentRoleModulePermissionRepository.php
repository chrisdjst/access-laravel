<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Eloquent\Repositories;

use Modularize\Access\Application\Ports\RoleModulePermissionRepository;
use Modularize\Access\Domain\Module\ModulePermission as DomainModulePermission;
use Modularize\Access\Domain\RoleModulePermission\RoleModulePermission as DomainRMP;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Laravel\Eloquent\Mappers\ModulePermissionMapper;
use Modularize\Access\Laravel\Eloquent\Mappers\RoleModulePermissionMapper;
use Modularize\Access\Laravel\Models\ModulePermission as ModulePermissionEloquent;
use Modularize\Access\Laravel\Models\RoleModulePermission as RMPEloquent;

final class EloquentRoleModulePermissionRepository implements RoleModulePermissionRepository
{
    public function __construct(
        private readonly RoleModulePermissionMapper $bindings,
        private readonly ModulePermissionMapper $permissions,
    ) {
    }

    public function forRole(Uuid $roleId): array
    {
        $models = RMPEloquent::query()
            ->with('permission')
            ->where('role_id', $roleId->value)
            ->get();

        $rows = [];
        foreach ($models as $row) {
            if ($row->permission === null) {
                continue;
            }
            $rows[] = [
                'binding' => $this->bindings->toDomain($row),
                'permission' => $this->permissions->toDomain($row->permission),
            ];
        }

        return $rows;
    }

    public function findByRoleAndModule(Uuid $roleId, Uuid $moduleId): ?DomainRMP
    {
        $row = RMPEloquent::query()
            ->where('role_id', $roleId->value)
            ->where('module_id', $moduleId->value)
            ->first();

        return $row !== null ? $this->bindings->toDomain($row) : null;
    }

    public function save(DomainRMP $binding): void
    {
        $existing = RMPEloquent::query()->find($binding->id->value);
        $model = $this->bindings->toModel($binding, $existing);
        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;
    }

    public function saveModulePermission(DomainModulePermission $permission): void
    {
        $existing = ModulePermissionEloquent::query()->find($permission->id->value);
        $model = $this->permissions->toModel($permission, $existing);
        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;
    }

    public function delete(DomainRMP $binding): void
    {
        RMPEloquent::query()->whereKey($binding->id->value)->delete();
    }
}
