<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Persistence-only Eloquent model. Extends Spatie's Permission so
 * Spatie can resolve permissions by name; PR 5 makes that
 * inheritance conditional on the Spatie integration being enabled.
 */
class Permission extends SpatiePermission
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'guard_name',
        'module',
    ];
}
