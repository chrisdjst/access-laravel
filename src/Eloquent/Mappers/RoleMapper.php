<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Mappers;

use ModularizeRbac\Core\Domain\Role\GuardName;
use ModularizeRbac\Core\Domain\Role\Role as DomainRole;
use ModularizeRbac\Core\Domain\Role\RoleLevel;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

final class RoleMapper
{
    public function toDomain(RoleEloquent $model): DomainRole
    {
        return DomainRole::reconstitute(
            id: new Uuid((string) $model->getKey()),
            name: (string) $model->name,
            displayName: $model->display_name !== null ? (string) $model->display_name : null,
            guard: new GuardName((string) $model->guard_name),
            tenantId: $model->organization_id !== null ? new Uuid((string) $model->organization_id) : null,
            level: new RoleLevel((int) ($model->level ?? 0)),
            isSystem: (bool) ($model->is_system ?? false),
            createdAt: $model->created_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            parentRoleId: $model->parent_role_id !== null ? new Uuid((string) $model->parent_role_id) : null,
            deletedAt: $model->deleted_at?->toDateTimeImmutable(),
        );
    }

    public function toModel(DomainRole $entity, ?RoleEloquent $existing = null): RoleEloquent
    {
        $model = $existing ?? new RoleEloquent();
        $model->setAttribute($model->getKeyName(), $entity->id->value);
        $model->name = $entity->name();
        $model->display_name = $entity->displayName();
        $model->guard_name = $entity->guard()->value;
        $model->organization_id = $entity->tenantId()?->value;
        $model->level = $entity->level()->value;
        $model->is_system = $entity->isSystem();
        $model->parent_role_id = $entity->parentRoleId()?->value;
        $model->created_at = $entity->createdAt();
        $model->updated_at = $entity->updatedAt();
        $model->deleted_at = $entity->deletedAt();

        return $model;
    }
}
