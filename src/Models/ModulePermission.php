<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Models;

use Modularize\Access\Laravel\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ModulePermission extends Model
{
    use HasUuid;

    /**
     * Map from permission flags to Spatie permission actions.
     * Preserves the convention already used by policies (view/create/update/delete)
     * plus adds `list` as an additional distinct permission.
     */
    public const FLAG_TO_ACTION = [
        'is_listing_allowed' => 'list',
        'is_reading_allowed' => 'view',
        'is_writing_allowed' => 'create',
        'is_editing_allowed' => 'update',
        'is_delete_allowed' => 'delete',
    ];

    protected $fillable = [
        'name',
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

    /**
     * @return array<int,string> actions like ['view', 'create'] for flags set to true.
     */
    public function allowedActions(): array
    {
        $actions = [];
        foreach (self::FLAG_TO_ACTION as $flag => $action) {
            if ($this->{$flag}) {
                $actions[] = $action;
            }
        }

        return $actions;
    }
}
