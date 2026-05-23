<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Mappers;

use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Core\Domain\Translation\Language as DomainLanguage;
use ModularizeRbac\Core\Domain\Translation\LanguageCode;
use ModularizeRbac\Laravel\Models\Language as LanguageEloquent;

final class LanguageMapper
{
    public function toDomain(LanguageEloquent $model): DomainLanguage
    {
        return new DomainLanguage(
            id: new Uuid((string) $model->getKey()),
            code: new LanguageCode((string) $model->code),
            name: (string) $model->name,
            isDefault: (bool) $model->is_default,
            isActive: (bool) $model->is_active,
            createdAt: $model->created_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
        );
    }

    public function toModel(DomainLanguage $entity, ?LanguageEloquent $existing = null): LanguageEloquent
    {
        $model = $existing ?? new LanguageEloquent();
        $model->setAttribute($model->getKeyName(), $entity->id->value);
        $model->code = $entity->code()->value;
        $model->name = $entity->name();
        $model->is_default = $entity->isDefault();
        $model->is_active = $entity->isActive();
        $model->created_at = $entity->createdAt();
        $model->updated_at = $entity->updatedAt();

        return $model;
    }
}
