<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Eloquent\Mappers;

use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Domain\Translation\Translation as DomainTranslation;
use Modularize\Access\Laravel\Models\Translation as TranslationEloquent;

final class TranslationMapper
{
    public function toDomain(TranslationEloquent $model): DomainTranslation
    {
        return new DomainTranslation(
            id: new Uuid((string) $model->getKey()),
            translatableType: (string) $model->translatable_type,
            translatableId: new Uuid((string) $model->translatable_id),
            languageId: new Uuid((string) $model->language_id),
            field: (string) $model->field,
            value: (string) $model->value,
            createdAt: $model->created_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
        );
    }

    public function toModel(DomainTranslation $entity, ?TranslationEloquent $existing = null): TranslationEloquent
    {
        $model = $existing ?? new TranslationEloquent();
        $model->setAttribute($model->getKeyName(), $entity->id->value);
        $model->translatable_type = $entity->translatableType;
        $model->translatable_id = $entity->translatableId->value;
        $model->language_id = $entity->languageId->value;
        $model->field = $entity->field;
        $model->value = $entity->value();
        $model->created_at = $entity->createdAt();
        $model->updated_at = $entity->updatedAt();

        return $model;
    }
}
