<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use ModularizeRbac\Core\Application\Language\LanguageOutput;

class LanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var LanguageOutput $output */
        $output = $this->resource;

        return [
            'id' => $output->id,
            'code' => $output->code,
            'name' => $output->name,
            'is_default' => $output->isDefault,
            'is_active' => $output->isActive,
            'created_at' => $output->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $output->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
