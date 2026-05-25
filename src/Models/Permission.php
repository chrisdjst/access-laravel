<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ModularizeRbac\Laravel\Database\Factories\PermissionFactory;

/**
 * Persistence-only Eloquent model for permissions.
 *
 * v2.0 stopped extending {@see \Spatie\Permission\Models\Permission}.
 * The `(name, guard_name)` uniqueness contract is preserved by the
 * own schema; Spatie sync (when enabled) replicates rows into
 * Spatie's table separately via the gateway adapter.
 *
 * Auto-UUID on `creating` keeps Eloquent shortcuts like
 * `Permission::create([...])` working without the host having to
 * remember to set an id.
 */
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'permissions';

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

    /**
     * Look up or create a permission row by (name, guard). Reproduces
     * the legacy Spatie behavior so internal call sites in the
     * `SpatiePermissionGateway` keep working when Spatie is enabled.
     */
    public static function findOrCreate(string $name, ?string $guardName = null): self
    {
        $guard = $guardName ?? (string) config('access.guard_name', 'admin');

        /** @var self|null $existing */
        $existing = static::query()
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $model = new self();
        $model->name = $name;
        $model->guard_name = $guard;
        $model->save();

        return $model;
    }

    protected static function newFactory(): PermissionFactory
    {
        return PermissionFactory::new();
    }
}
