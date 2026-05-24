<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use ModularizeRbac\Core\Application\Role\RoleOutput;

/**
 * Wraps a {@see RoleOutput} plus the enrichment fetched by the
 * controller — translations and the per-module permission matrix —
 * into the payload shape the lib has exposed since v0.1.0.
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /**
         * @var array{
         *     output: RoleOutput,
         *     translations: array<string, array<string, string>>,
         *     modules: list<array<string, mixed>>|null,
         * } $bundle
         */
        $bundle = $this->resource;
        $output = $bundle['output'];

        return [
            'id' => $output->id,
            'name' => $output->name,
            'display_name' => $output->displayName,
            'guard_name' => $output->guard,
            'level' => $output->level,
            'is_system' => $output->isSystem,
            'parent_role_id' => $output->parentRoleId,
            'organization_id' => $output->tenantId,
            'translations' => $bundle['translations'],
            'modules' => $bundle['modules'],
            'created_at' => $output->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $output->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
