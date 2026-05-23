<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use ModularizeRbac\Core\Application\Audit\AuditEntryOutput;

class AuditEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var AuditEntryOutput $output */
        $output = $this->resource;

        return [
            'id' => $output->id,
            'event' => $output->event,
            'actor_id' => $output->actorId,
            'tenant_id' => $output->tenantId,
            'payload' => $output->payload,
            'occurred_at' => $output->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
