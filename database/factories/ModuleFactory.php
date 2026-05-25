<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use ModularizeRbac\Laravel\Models\Module;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'slug' => 'mod'.Str::random(8),
            'name' => $this->faker->words(2, true),
            'redirect' => null,
            'icon' => null,
            'root_module_id' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withParent(Module|string $parent): self
    {
        return $this->state(fn () => [
            'root_module_id' => $parent instanceof Module ? $parent->id : $parent,
        ]);
    }

    public function trashed(): self
    {
        return $this->state(fn () => ['deleted_at' => now()]);
    }
}
