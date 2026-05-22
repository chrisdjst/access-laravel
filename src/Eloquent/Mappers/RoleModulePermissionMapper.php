<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Eloquent\Mappers;

use Modularize\Access\Domain\RoleModulePermission\RoleModulePermission as DomainRMP;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Laravel\Models\RoleModulePermission as RMPEloquent;

final class RoleModulePermissionMapper
{
    public function toDomain(RMPEloquent $model): DomainRMP
    {
        return new DomainRMP(
            id: new Uuid((string) $model->getKey()),
            roleId: new Uuid((string) $model->role_id),
            moduleId: new Uuid((string) $model->module_id),
            modulePermissionId: new Uuid((string) $model->module_permission_id),
            createdBy: $model->created_by !== null ? new Uuid((string) $model->created_by) : null,
            updatedBy: $model->updated_by !== null ? new Uuid((string) $model->updated_by) : null,
            createdAt: $model->created_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
        );
    }

    public function toModel(DomainRMP $entity, ?RMPEloquent $existing = null): RMPEloquent
    {
        $model = $existing ?? new RMPEloquent();
        $model->setAttribute($model->getKeyName(), $entity->id->value);
        $model->role_id = $entity->roleId->value;
        $model->module_id = $entity->moduleId->value;
        $model->module_permission_id = $entity->modulePermissionId()->value;
        $model->created_by = $entity->createdBy?->value;
        $model->updated_by = $entity->updatedBy()?->value;
        $model->created_at = $entity->createdAt();
        $model->updated_at = $entity->updatedAt();

        return $model;
    }
}
