<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Eloquent\Mappers;

use Modularize\Access\Domain\Module\ModulePermission as DomainModulePermission;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Laravel\Models\ModulePermission as ModulePermissionEloquent;

final class ModulePermissionMapper
{
    public function toDomain(ModulePermissionEloquent $model): DomainModulePermission
    {
        return new DomainModulePermission(
            id: new Uuid((string) $model->getKey()),
            isListingAllowed: (bool) $model->is_listing_allowed,
            isReadingAllowed: (bool) $model->is_reading_allowed,
            isWritingAllowed: (bool) $model->is_writing_allowed,
            isEditingAllowed: (bool) $model->is_editing_allowed,
            isDeleteAllowed: (bool) $model->is_delete_allowed,
            isActive: (bool) ($model->is_active ?? true),
            createdBy: $model->created_by !== null ? new Uuid((string) $model->created_by) : null,
            updatedBy: $model->updated_by !== null ? new Uuid((string) $model->updated_by) : null,
            createdAt: $model->created_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
        );
    }

    public function toModel(DomainModulePermission $entity, ?ModulePermissionEloquent $existing = null): ModulePermissionEloquent
    {
        $model = $existing ?? new ModulePermissionEloquent();
        $model->setAttribute($model->getKeyName(), $entity->id->value);
        $model->is_listing_allowed = $entity->isListingAllowed();
        $model->is_reading_allowed = $entity->isReadingAllowed();
        $model->is_writing_allowed = $entity->isWritingAllowed();
        $model->is_editing_allowed = $entity->isEditingAllowed();
        $model->is_delete_allowed = $entity->isDeleteAllowed();
        $model->is_active = $entity->isActive();
        $model->created_by = $entity->createdBy?->value;
        $model->updated_by = $entity->updatedBy()?->value;
        $model->created_at = $entity->createdAt();
        $model->updated_at = $entity->updatedAt();

        return $model;
    }
}
