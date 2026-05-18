<?php

declare(strict_types=1);

namespace Casamento\Rbac\Http\Resources;

use Casamento\Rbac\Models\Module;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Module
 */
class ModuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'icon' => $this->icon,
            'redirect' => $this->redirect,
            'root_module_id' => $this->root_module_id,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'translations' => $this->whenLoaded(
                'translations',
                fn () => $this->translations
                    ->groupBy('field')
                    ->map(fn ($rows) => $rows->mapWithKeys(fn ($t) => [$t->language?->code => $t->value]))
            ),
            'price' => $this->whenLoaded('price', fn () => $this->price ? [
                'value' => (float) $this->price->value,
                'currency' => $this->price->currency,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
