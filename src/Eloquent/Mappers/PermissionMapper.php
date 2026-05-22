<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Eloquent\Mappers;

use Modularize\Access\Domain\Module\ModuleSlug;
use Modularize\Access\Domain\Permission\Permission as DomainPermission;
use Modularize\Access\Domain\Permission\PermissionName;
use Modularize\Access\Domain\Role\GuardName;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Laravel\Models\Permission as PermissionEloquent;

final class PermissionMapper
{
    public function toDomain(PermissionEloquent $model): DomainPermission
    {
        $rawModule = $model->module;
        $moduleSlug = is_string($rawModule) && $rawModule !== ''
            ? new ModuleSlug($rawModule)
            : null;

        return new DomainPermission(
            id: new Uuid((string) $model->getKey()),
            name: new PermissionName((string) $model->name),
            guard: new GuardName((string) $model->guard_name),
            moduleSlug: $moduleSlug,
            createdAt: $model->created_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
        );
    }

    public function toModel(DomainPermission $entity, ?PermissionEloquent $existing = null): PermissionEloquent
    {
        $model = $existing ?? new PermissionEloquent();
        $model->setAttribute($model->getKeyName(), $entity->id->value);
        $model->name = $entity->name->value;
        $model->guard_name = $entity->guard->value;
        $model->module = $entity->moduleSlug?->value;
        $model->created_at = $entity->createdAt();
        $model->updated_at = $entity->updatedAt();

        return $model;
    }
}
