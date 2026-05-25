<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use ModularizeRbac\Laravel\Models\Language;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'code' => $this->faker->unique()->languageCode(),
            'name' => $this->faker->randomElement(['English', 'Português', 'Español', 'Français', 'Deutsch']),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function isDefault(): self
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
