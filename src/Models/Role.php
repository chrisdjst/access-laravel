<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Persistence-only Eloquent model for roles.
 *
 * v2.0 stopped extending {@see \Spatie\Permission\Models\Role}: the
 * package owns the schema and lifecycle. Hosts that still want
 * Spatie sync get it via the optional `SpatiePermissionGateway`,
 * which looks up by id rather than depending on the model
 * inheritance.
 *
 * Business surface lives in {@see \ModularizeRbac\Core\Domain\Role\Role};
 * this class is a row holder mapped to/from the domain entity by
 * {@see \ModularizeRbac\Laravel\Eloquent\Mappers\RoleMapper}.
 */
class Role extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'display_name',
        'guard_name',
        'organization_id',
        'level',
        'is_system',
        'parent_role_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'level' => 'integer',
        ];
    }

    /**
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

    /**
     * Direct permissions held by this role through the package's own
     * `role_module_permission` schema. Returns a query you can
     * iterate or count. Replaces what Spatie's `permissions` relation
     * used to do in v1.
     *
     * @return HasMany<RoleModulePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->rolePermissions();
    }

    /**
     * Users assigned to this role via the `role_user` pivot
     * introduced in v2.0 (PR V2.4 adds the migration). Host's user
     * class is read from `config('access.user_model')`.
     *
     * @return BelongsToMany<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function users(): BelongsToMany
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = (string) config('access.user_model', 'App\\Models\\User');

        return $this->belongsToMany($userModel, 'role_user', 'role_id', 'user_id');
    }
}
