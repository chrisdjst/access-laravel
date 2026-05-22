<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Resources;

use Modularize\Access\Laravel\Models\Role;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $translations = [];
        if ($this->relationLoaded('translations')) {
            foreach ($this->translations as $t) {
                $code = $t->language?->code;
                if (! $code) {
                    continue;
                }
                $translations[$t->field][$code] = $t->value;
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'guard_name' => $this->guard_name,
            'level' => $this->level,
            'is_system' => $this->is_system,
            'organization_id' => $this->organization_id,
            'translations' => $translations,
            'modules' => $this->whenLoaded(
                'rolePermissions',
                fn () => $this->rolePermissions->map(fn ($p) => [
                    'module_id' => $p->module_id,
                    'module_permission_id' => $p->module_permission_id,
                    'flags' => $p->permission ? [
                        'is_reading_allowed' => (bool) $p->permission->is_reading_allowed,
                        'is_writing_allowed' => (bool) $p->permission->is_writing_allowed,
                        'is_editing_allowed' => (bool) $p->permission->is_editing_allowed,
                        'is_delete_allowed' => (bool) $p->permission->is_delete_allowed,
                        'is_listing_allowed' => (bool) $p->permission->is_listing_allowed,
                    ] : null,
                ])
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
