<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Models;

use Modularize\Access\Laravel\Concerns\HasUuid;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUuid;

    protected $fillable = [
        'name',
        'guard_name',
        'module',
    ];
}
