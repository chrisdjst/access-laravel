<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use ModularizeRbac\Core\Application\Role\GetRolePermissionMatrix\GetRolePermissionMatrixOutput;

/**
 * Wraps {@see GetRolePermissionMatrixOutput} — role identity plus
 * the full per-module flag matrix — into a JSON payload. Used by
 * `GET /api/admin/roles/{role}/permission-matrix`.
 */
class RolePermissionMatrixResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var GetRolePermissionMatrixOutput $output */
        $output = $this->resource;
        $role = $output->role;

        return [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->displayName,
                'guard_name' => $role->guard,
                'level' => $role->level,
                'is_system' => $role->isSystem,
                'organization_id' => $role->tenantId,
                'created_at' => $role->createdAt->format(DateTimeInterface::ATOM),
                'updated_at' => $role->updatedAt->format(DateTimeInterface::ATOM),
            ],
            'modules' => array_map(static fn ($m) => [
                'module_id' => $m->moduleId,
                'module_permission_id' => $m->modulePermissionId,
                'slug' => $m->slug,
                'name' => $m->name,
                'flags' => [
                    'is_listing_allowed' => $m->isListingAllowed,
                    'is_reading_allowed' => $m->isReadingAllowed,
                    'is_writing_allowed' => $m->isWritingAllowed,
                    'is_editing_allowed' => $m->isEditingAllowed,
                    'is_delete_allowed' => $m->isDeleteAllowed,
                ],
            ], $output->modules),
        ];
    }
}
