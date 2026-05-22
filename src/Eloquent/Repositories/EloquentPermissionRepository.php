<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Eloquent\Repositories;

use Illuminate\Support\Str;
use Modularize\Access\Application\Ports\PermissionRepository;
use Modularize\Access\Domain\Permission\Permission as DomainPermission;
use Modularize\Access\Domain\Permission\PermissionName;
use Modularize\Access\Domain\Role\GuardName;
use Modularize\Access\Laravel\Eloquent\Mappers\PermissionMapper;
use Modularize\Access\Laravel\Models\Permission as PermissionEloquent;

final class EloquentPermissionRepository implements PermissionRepository
{
    public function __construct(private readonly PermissionMapper $mapper)
    {
    }

    public function findByName(PermissionName $name, GuardName $guard): ?DomainPermission
    {
        $model = PermissionEloquent::query()
            ->where('name', $name->value)
            ->where('guard_name', $guard->value)
            ->first();

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function findOrCreate(PermissionName $name, GuardName $guard): DomainPermission
    {
        $existing = $this->findByName($name, $guard);
        if ($existing !== null) {
            return $existing;
        }

        $model = new PermissionEloquent();
        $model->setAttribute($model->getKeyName(), (string) Str::uuid());
        $model->name = $name->value;
        $model->guard_name = $guard->value;
        $model->module = $name->moduleSlug->value;
        $model->save();

        return $this->mapper->toDomain($model);
    }
}
