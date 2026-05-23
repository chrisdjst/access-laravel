<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Persistence-only Eloquent model. Extends Spatie's Permission so
 * Spatie's `findOrCreate` keeps working through the Permission
 * facade; v2.0 fully decouples this from Spatie.
 *
 * The `creating` boot hook generates the UUID when Spatie inserts a
 * row through `firstOrCreate` without our IdGenerator port — the
 * domain pathway sets ids explicitly via the mapper, this is only
 * for the Spatie-driven path.
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

    protected static function booted(): void
    {
        static::creating(function (Model $model): void {
            $key = $model->getKeyName();
            if (empty($model->{$key})) {
                $model->{$key} = (string) Str::uuid();
            }
        });
    }
}
