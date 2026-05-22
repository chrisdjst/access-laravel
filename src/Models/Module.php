<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Models;

use Modularize\Access\Laravel\Concerns\HasTranslations;
use Modularize\Access\Laravel\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasTranslations, HasUuid, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'redirect',
        'icon',
        'root_module_id',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function rootModule(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_module_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'root_module_id')->orderBy('sort_order');
    }

    /**
     * @return HasOne<ModulePrice, $this>
     */
    public function price(): HasOne
    {
        return $this->hasOne(ModulePrice::class)->where('is_active', true);
    }

    /**
     * @return HasMany<RoleModulePermission, $this>
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RoleModulePermission::class);
    }
}
