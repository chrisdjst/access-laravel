<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use ModularizeRbac\Laravel\Models\ModulePermission;

/**
 * @extends Factory<ModulePermission>
 */
class ModulePermissionFactory extends Factory
{
    protected $model = ModulePermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'is_listing_allowed' => false,
            'is_reading_allowed' => true,
            'is_writing_allowed' => false,
            'is_editing_allowed' => false,
            'is_delete_allowed' => false,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function allowAll(): self
    {
        return $this->state(fn () => [
            'is_listing_allowed' => true,
            'is_reading_allowed' => true,
            'is_writing_allowed' => true,
            'is_editing_allowed' => true,
            'is_delete_allowed' => true,
        ]);
    }

    public function readOnly(): self
    {
        return $this->state(fn () => [
            'is_listing_allowed' => true,
            'is_reading_allowed' => true,
            'is_writing_allowed' => false,
            'is_editing_allowed' => false,
            'is_delete_allowed' => false,
        ]);
    }
}
