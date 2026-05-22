<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModulePrice extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'module_id',
        'value',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
