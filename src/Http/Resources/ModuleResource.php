<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Modularize\Access\Application\Module\ModuleOutput;

/**
 * Wraps a {@see ModuleOutput} (plus enrichment fetched by the
 * controller — translations grouped by field × locale and the active
 * price row) into the API payload shape the lib has exposed since
 * v0.1.0. The Resource has no business logic.
 */
class ModuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var array{output: ModuleOutput, translations: array<string, array<string, string>>, price: array{value: float, currency: string}|null} $bundle */
        $bundle = $this->resource;
        $output = $bundle['output'];

        return [
            'id' => $output->id,
            'slug' => $output->slug,
            'name' => $output->name,
            'icon' => $output->icon,
            'redirect' => $output->redirect,
            'root_module_id' => $output->rootModuleId,
            'sort_order' => $output->sortOrder,
            'is_active' => $output->isActive,
            'translations' => $bundle['translations'],
            'price' => $bundle['price'],
            'created_at' => $output->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $output->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
