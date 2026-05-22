<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Eloquent\Mappers;

use Modularize\Access\Domain\Role\GuardName;
use Modularize\Access\Domain\Role\Role as DomainRole;
use Modularize\Access\Domain\Role\RoleLevel;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Laravel\Models\Role as RoleEloquent;

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
        $model->created_at = $entity->createdAt();
        $model->updated_at = $entity->updatedAt();

        return $model;
    }
}
