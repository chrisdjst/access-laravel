<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
