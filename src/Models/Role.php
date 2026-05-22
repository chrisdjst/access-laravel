<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Models;

use Modularize\Access\Laravel\Concerns\HasTranslations;
use Modularize\Access\Laravel\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasTranslations, HasUuid;

    protected $fillable = [
        'name',
        'display_name',
        'guard_name',
        'organization_id',
        'level',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'level' => 'integer',
        ];
    }

    /**
     * The owning tenant (organization / account / workspace) for this role.
     * Resolves the model class from `config('access.tenant_model')`; throws if
     * the host app has not configured it. Use `tenantOrNull()` for graceful
     * single-tenant setups.
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function tenant(): BelongsTo
    {
        $model = config('access.tenant_model');
        if (! $model || ! is_string($model)) {
            throw new LogicException(
                'access.tenant_model is not configured. Set it in config/access.php to the '
                .'fully-qualified class name of your tenant model (e.g. App\\Models\\Organization::class).'
            );
        }

        return $this->belongsTo($model, config('access.tenant_column', 'organization_id'));
    }

    /**
     * @return HasMany<RoleModulePermission, $this>
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RoleModulePermission::class, 'role_id');
    }
}
