<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence-only Eloquent model. The flag→action mapping moved to
 * {@see \Modularize\Access\Domain\Module\ModulePermission::FLAG_TO_ACTION}
 * and the `allowedActions()` derivation lives in
 * {@see \Modularize\Access\Domain\RoleModulePermission\PermissionFlagResolver}.
 */
class ModulePermission extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'is_reading_allowed',
        'is_writing_allowed',
        'is_editing_allowed',
        'is_delete_allowed',
        'is_listing_allowed',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_reading_allowed' => 'boolean',
            'is_writing_allowed' => 'boolean',
            'is_editing_allowed' => 'boolean',
            'is_delete_allowed' => 'boolean',
            'is_listing_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
