<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Eloquent\Repositories;

use Modularize\Access\Application\Ports\ModuleRepository;
use Modularize\Access\Domain\Module\Module as DomainModule;
use Modularize\Access\Domain\Module\ModuleSlug;
use Modularize\Access\Domain\Shared\Uuid;
use Modularize\Access\Laravel\Eloquent\Mappers\ModuleMapper;
use Modularize\Access\Laravel\Models\Module as ModuleEloquent;

final class EloquentModuleRepository implements ModuleRepository
{
    public function __construct(private readonly ModuleMapper $mapper)
    {
    }

    public function find(Uuid $id): ?DomainModule
    {
        $model = ModuleEloquent::query()->withTrashed()->find($id->value);

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function findBySlug(ModuleSlug $slug): ?DomainModule
    {
        $model = ModuleEloquent::query()->where('slug', $slug->value)->first();

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function allActiveTree(): array
    {
        $models = ModuleEloquent::query()
            ->orderByRaw('CASE WHEN root_module_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('root_module_id')
            ->orderBy('sort_order')
            ->get();

        $domain = [];
        foreach ($models as $model) {
            $domain[] = $this->mapper->toDomain($model);
        }

        return $domain;
    }

    public function save(DomainModule $module): void
    {
        $existing = ModuleEloquent::query()->withTrashed()->find($module->id->value);
        $model = $this->mapper->toModel($module, $existing);

        // Soft-delete state is part of the aggregate; reflect it on
        // the Eloquent timestamp column. Eloquent's `delete()` would
        // re-run the lifecycle and re-emit observer events, which we
        // don't want here.
        $model->deleted_at = $module->deletedAt();

        $model->timestamps = false;
        $model->saveQuietly();
        $model->timestamps = true;
    }
}
