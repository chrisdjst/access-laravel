<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Ports\PermissionRepository;
use ModularizeRbac\Core\Domain\Permission\Permission as DomainPermission;
use ModularizeRbac\Core\Domain\Permission\PermissionName;
use ModularizeRbac\Core\Domain\Role\GuardName;
use ModularizeRbac\Laravel\Eloquent\Mappers\PermissionMapper;
use ModularizeRbac\Laravel\Models\Permission as PermissionEloquent;

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
