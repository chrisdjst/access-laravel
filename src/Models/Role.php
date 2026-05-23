<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Persistence-only Eloquent model. The business surface (display
 * name change, tenant scoping invariants) lives in
 * {@see \ModularizeRbac\Core\Domain\Role\Role}; this class is a row
 * holder that still extends Spatie's Role so Spatie can resolve
 * roles by name through its own infrastructure.
 *
 * PR 5 makes the Spatie dependency opt-in; if and when Spatie is
 * absent, this class will fall back to extending plain Eloquent
 * Model. The migration covering that swap is part of PR 5.
 */
class Role extends SpatieRole
{
    public $incrementing = false;

    protected $keyType = 'string';

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
