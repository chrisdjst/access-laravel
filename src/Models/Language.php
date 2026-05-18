<?php

declare(strict_types=1);

namespace Casamento\Rbac\Models;

use Casamento\Rbac\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasUuid;

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

    public static function default(): ?self
    {
        return self::query()->where('is_default', true)->first();
    }
}
