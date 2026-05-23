<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use ModularizeRbac\Core\Application\Ports\RoleModulePermissionRepository;
use ModularizeRbac\Core\Application\Role\GetRolePermissionMatrix\RolePermissionMatrixRow;
use ModularizeRbac\Core\Domain\Module\ModulePermission as DomainModulePermission;
use ModularizeRbac\Core\Domain\RoleModulePermission\RoleModulePermission as DomainRMP;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Eloquent\Mappers\ModuleMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\ModulePermissionMapper;
use ModularizeRbac\Laravel\Eloquent\Mappers\RoleModulePermissionMapper;
use ModularizeRbac\Laravel\Models\ModulePermission as ModulePermissionEloquent;
use ModularizeRbac\Laravel\Models\RoleModulePermission as RMPEloquent;

final class EloquentRoleModulePermissionRepository implements RoleModulePermissionRepository
{
    public function __construct(
        private readonly RoleModulePermissionMapper $bindings,
        private readonly ModulePermissionMapper $permissions,
        private readonly ModuleMapper $modules,
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

    public function matrixFor(Uuid $roleId): array
    {
        $models = RMPEloquent::query()
            ->with(['permission', 'module'])
            ->where('role_id', $roleId->value)
            ->get();

        $rows = [];
        foreach ($models as $row) {
            if ($row->permission === null || $row->module === null) {
                continue;
            }
            $rows[] = new RolePermissionMatrixRow(
                binding: $this->bindings->toDomain($row),
                permission: $this->permissions->toDomain($row->permission),
                module: $this->modules->toDomain($row->module),
            );
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
