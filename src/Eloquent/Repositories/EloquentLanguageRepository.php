<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Core\Domain\Translation\Language as DomainLanguage;
use ModularizeRbac\Core\Domain\Translation\LanguageCode;
use ModularizeRbac\Laravel\Eloquent\Mappers\LanguageMapper;
use ModularizeRbac\Laravel\Models\Language as LanguageEloquent;

final class EloquentLanguageRepository implements LanguageRepository
{
    public function __construct(private readonly LanguageMapper $mapper)
    {
    }

    public function find(Uuid $id): ?DomainLanguage
    {
        $model = LanguageEloquent::query()->find($id->value);

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function findByCode(LanguageCode $code): ?DomainLanguage
    {
        $model = LanguageEloquent::query()->where('code', $code->value)->first();

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function default(): ?DomainLanguage
    {
        $model = LanguageEloquent::query()->where('is_default', true)->first();

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function all(): array
    {
        $domain = [];
        foreach (LanguageEloquent::query()->orderBy('code')->get() as $model) {
            $domain[] = $this->mapper->toDomain($model);
        }

        return $domain;
    }

    public function save(DomainLanguage $language): void
    {
        $existing = LanguageEloquent::query()->find($language->id->value);
        $model = $this->mapper->toModel($language, $existing);

        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;
    }

    public function delete(DomainLanguage $language): void
    {
        LanguageEloquent::query()->whereKey($language->id->value)->delete();
    }
}
