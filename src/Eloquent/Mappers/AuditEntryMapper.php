<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Mappers;

use ModularizeRbac\Core\Domain\Audit\AuditEntry as DomainAuditEntry;
use ModularizeRbac\Core\Domain\Audit\AuditEventName;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Laravel\Models\AuditEntry as AuditEntryEloquent;

final class AuditEntryMapper
{
    public function toDomain(AuditEntryEloquent $model): DomainAuditEntry
    {
        $payload = $model->payload;
        if (! is_array($payload)) {
            $payload = [];
        }

        /** @var array<string, mixed> $payload */
        return new DomainAuditEntry(
            id: new Uuid((string) $model->getKey()),
            event: new AuditEventName((string) $model->event_name),
            actorId: $model->actor_id !== null ? new Uuid((string) $model->actor_id) : null,
            tenantId: $model->tenant_id !== null ? new Uuid((string) $model->tenant_id) : null,
            payload: $payload,
            occurredAt: $model->occurred_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
        );
    }

    public function toModel(DomainAuditEntry $entry, ?AuditEntryEloquent $existing = null): AuditEntryEloquent
    {
        $model = $existing ?? new AuditEntryEloquent();
        $model->setAttribute($model->getKeyName(), $entry->id->value);
        $model->event_name = $entry->event->value;
        $model->actor_id = $entry->actorId?->value;
        $model->tenant_id = $entry->tenantId?->value;
        $model->payload = $entry->payload;
        $model->occurred_at = $entry->occurredAt;

        return $model;
    }
}
