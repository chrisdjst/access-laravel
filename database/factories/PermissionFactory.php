<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use ModularizeRbac\Laravel\Models\Permission;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = 'mod'.Str::random(6);

        return [
            'id' => (string) Str::uuid(),
            'name' => $slug.'.view',
            'guard_name' => 'admin',
            'module' => $slug,
        ];
    }

    public function forModule(string $slug): self
    {
        return $this->state(fn () => [
            'module' => $slug,
            'name' => $slug.'.view',
        ]);
    }

    public function action(string $action): self
    {
        return $this->state(function (array $attrs) use ($action): array {
            $slug = $attrs['module'] ?? 'mod'.Str::random(6);

            return [
                'module' => $slug,
                'name' => $slug.'.'.$action,
            ];
        });
    }
}
