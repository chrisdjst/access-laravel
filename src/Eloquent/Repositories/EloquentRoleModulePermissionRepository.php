<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Ports\Authorizer;
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
        private readonly Authorizer $authorizer,
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
        $beforeId = $existing?->module_permission_id !== null ? (string) $existing->module_permission_id : null;

        $model = $this->bindings->toModel($binding, $existing);
        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;

        $changeType = $existing === null ? 'create' : 'update';
        $afterId = (string) $binding->modulePermissionId()->value;

        // Skip the history row when nothing actually changed (idempotent save).
        if ($changeType === 'update' && $beforeId === $afterId) {
            return;
        }

        $this->recordHistory(
            bindingId: $binding->id->value,
            roleId: $binding->roleId->value,
            moduleId: $binding->moduleId->value,
            beforeId: $beforeId,
            afterId: $afterId,
            changeType: $changeType,
        );
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
        $existing = RMPEloquent::query()->find($binding->id->value);
        $beforeId = $existing?->module_permission_id !== null ? (string) $existing->module_permission_id : null;

        RMPEloquent::query()->whereKey($binding->id->value)->delete();

        $this->recordHistory(
            bindingId: $binding->id->value,
            roleId: $binding->roleId->value,
            moduleId: $binding->moduleId->value,
            beforeId: $beforeId,
            afterId: null,
            changeType: 'delete',
        );
    }

    /**
     * Append a snapshot to role_module_permission_history. Wrapped in
     * try/catch so a missing history table (host that hasn't run the
     * v2.8 migration yet) doesn't crash the main write path.
     */
    private function recordHistory(
        string $bindingId,
        string $roleId,
        string $moduleId,
        ?string $beforeId,
        ?string $afterId,
        string $changeType,
    ): void {
        try {
            $now = (string) now();
            DB::table('role_module_permission_history')->insert([
                'id' => (string) Str::uuid(),
                'binding_id' => $bindingId,
                'role_id' => $roleId,
                'module_id' => $moduleId,
                'module_permission_id_before' => $beforeId,
                'module_permission_id_after' => $afterId,
                'change_type' => $changeType,
                'actor_id' => $this->authorizer->actorId()?->value,
                'changed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable) {
            // History is observability-only; the main domain flow
            // must keep working even if the table is missing.
        }
    }
}
