<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleModulePermission extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'role_module_permission';

    protected $fillable = [
        'role_id',
        'module_id',
        'module_permission_id',
        'created_by',
        'updated_by',
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsTo<ModulePermission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(ModulePermission::class, 'module_permission_id');
    }
}
