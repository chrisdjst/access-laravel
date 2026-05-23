<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use ModularizeRbac\Core\Application\Module\ModuleOutput;

/**
 * Lean module projection for the user-accessible-modules endpoint.
 * Skips translations and price (call /modules/{id} for the full
 * representation); shells typically only need slug + name + sort
 * order + parent to render a nav tree.
 */
class AccessibleModuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var ModuleOutput $output */
        $output = $this->resource;

        return [
            'id' => $output->id,
            'slug' => $output->slug,
            'name' => $output->name,
            'redirect' => $output->redirect,
            'icon' => $output->icon,
            'root_module_id' => $output->rootModuleId,
            'sort_order' => $output->sortOrder,
            'is_active' => $output->isActive,
        ];
    }
}
