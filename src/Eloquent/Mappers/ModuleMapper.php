<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Mappers;

use ModularizeRbac\Core\Domain\Module\Module as DomainModule;
use ModularizeRbac\Core\Domain\Module\ModuleSlug;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Models\Module as ModuleEloquent;

/**
 * Translates between the {@see DomainModule} aggregate and the
 * Eloquent {@see ModuleEloquent} row. Repositories use the mapper to
 * keep domain code free of Eloquent.
 */
final class ModuleMapper
{
    public function toDomain(ModuleEloquent $model): DomainModule
    {
        $deletedAt = $model->deleted_at;

        return DomainModule::reconstitute(
            id: new Uuid((string) $model->getKey()),
            slug: new ModuleSlug((string) $model->slug),
            name: (string) $model->name,
            redirect: $model->redirect !== null ? (string) $model->redirect : null,
            icon: $model->icon !== null ? (string) $model->icon : null,
            rootModuleId: $model->root_module_id !== null ? new Uuid((string) $model->root_module_id) : null,
            sortOrder: (int) $model->sort_order,
            isActive: (bool) $model->is_active,
            createdBy: $model->created_by !== null ? new Uuid((string) $model->created_by) : null,
            updatedBy: $model->updated_by !== null ? new Uuid((string) $model->updated_by) : null,
            createdAt: $model->created_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            deletedAt: $deletedAt?->toDateTimeImmutable(),
        );
    }

    /**
     * Apply a domain entity onto an Eloquent row. The `$existing`
     * row, when supplied, is mutated in place so timestamps and
     * connection state are preserved; otherwise a new row is built.
     */
    public function toModel(DomainModule $entity, ?ModuleEloquent $existing = null): ModuleEloquent
    {
        $model = $existing ?? new ModuleEloquent();
        $model->setAttribute($model->getKeyName(), $entity->id->value);
        $model->slug = $entity->slug()->value;
        $model->name = $entity->name();
        $model->redirect = $entity->redirect();
        $model->icon = $entity->icon();
        $model->root_module_id = $entity->rootModuleId()?->value;
        $model->sort_order = $entity->sortOrder();
        $model->is_active = $entity->isActive();
        $model->created_by = $entity->createdBy?->value;
        $model->updated_by = $entity->updatedBy()?->value;

        // Domain owns the timestamps; suppress Eloquent's automatic
        // touch by setting them explicitly. Soft-deleted state is
        // applied via Eloquent's restore/delete primitives by the
        // repository, not here.
        $model->created_at = $entity->createdAt();
        $model->updated_at = $entity->updatedAt();

        return $model;
    }
}
