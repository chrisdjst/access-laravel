<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use ModularizeRbac\Laravel\Database\Factories\ModuleFactory;

/**
 * Persistence-only Eloquent model. Business invariants and lifecycle
 * events live in {@see \ModularizeRbac\Core\Domain\Module\Module}; this
 * class is a thin row holder mapped to and from the domain entity by
 * {@see \ModularizeRbac\Laravel\Eloquent\Mappers\ModuleMapper}.
 *
 * Identifier generation lives in the
 * {@see \ModularizeRbac\Core\Domain\Shared\IdGenerator} port — the
 * Eloquent layer no longer auto-generates UUIDs on `creating`.
 */
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

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

    protected static function newFactory(): ModuleFactory
    {
        return ModuleFactory::new();
    }
}
