<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use ModularizeRbac\Core\Application\Ports\TranslationRepository;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Core\Domain\Translation\Translation as DomainTranslation;
use ModularizeRbac\Laravel\Eloquent\Mappers\TranslationMapper;
use ModularizeRbac\Laravel\Models\Translation as TranslationEloquent;

final class EloquentTranslationRepository implements TranslationRepository
{
    public function __construct(private readonly TranslationMapper $mapper)
    {
    }

    public function forSubject(string $translatableType, Uuid $translatableId): array
    {
        $models = TranslationEloquent::query()
            ->where('translatable_type', $translatableType)
            ->where('translatable_id', $translatableId->value)
            ->get();

        $domain = [];
        foreach ($models as $model) {
            $domain[] = $this->mapper->toDomain($model);
        }

        return $domain;
    }

    public function save(DomainTranslation $translation): void
    {
        $existing = TranslationEloquent::query()
            ->where('translatable_type', $translation->translatableType)
            ->where('translatable_id', $translation->translatableId->value)
            ->where('language_id', $translation->languageId->value)
            ->where('field', $translation->field)
            ->first();

        $model = $this->mapper->toModel($translation, $existing);
        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;
    }

    public function deleteForSubjectField(string $translatableType, Uuid $translatableId, Uuid $languageId, string $field): void
    {
        TranslationEloquent::query()
            ->where('translatable_type', $translatableType)
            ->where('translatable_id', $translatableId->value)
            ->where('language_id', $languageId->value)
            ->where('field', $field)
            ->delete();
    }
}
