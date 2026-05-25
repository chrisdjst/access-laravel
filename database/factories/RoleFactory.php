<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use ModularizeRbac\Laravel\Models\Role;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => 'role_'.Str::random(8),
            'display_name' => $this->faker->words(2, true),
            'guard_name' => 'admin',
            'organization_id' => null,
            'level' => 0,
            'is_system' => false,
            'parent_role_id' => null,
        ];
    }

    public function system(): self
    {
        return $this->state(fn () => ['is_system' => true, 'level' => 100]);
    }

    public function withParent(Role|string $parent): self
    {
        return $this->state(fn () => [
            'parent_role_id' => $parent instanceof Role ? $parent->id : $parent,
        ]);
    }

    public function forGuard(string $guard): self
    {
        return $this->state(fn () => ['guard_name' => $guard]);
    }

    public function forTenant(string $tenantId): self
    {
        return $this->state(fn () => ['organization_id' => $tenantId]);
    }
}
