<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Eloquent\Repositories;

use ModularizeRbac\Core\Application\Audit\AuditQuery;
use ModularizeRbac\Core\Application\Ports\AuditRepository;
use ModularizeRbac\Core\Domain\Audit\AuditEntry as DomainAuditEntry;
use ModularizeRbac\Laravel\Eloquent\Mappers\AuditEntryMapper;
use ModularizeRbac\Laravel\Models\AuditEntry as AuditEntryEloquent;

final class EloquentAuditRepository implements AuditRepository
{
    public function __construct(private readonly AuditEntryMapper $mapper)
    {
    }

    public function save(DomainAuditEntry $entry): void
    {
        // Append-only: never updates a pre-existing row.
        $model = $this->mapper->toModel($entry);
        $model->save();
    }

    public function search(AuditQuery $query): array
    {
        $builder = $this->applyFilters(AuditEntryEloquent::query(), $query);

        $models = $builder
            ->orderByDesc('occurred_at')
            ->limit($query->limit)
            ->offset($query->offset)
            ->get();

        $rows = [];
        foreach ($models as $model) {
            $rows[] = $this->mapper->toDomain($model);
        }

        return $rows;
    }

    public function count(AuditQuery $query): int
    {
        return $this->applyFilters(AuditEntryEloquent::query(), $query)->count();
    }

    private function applyFilters(
        \Illuminate\Database\Eloquent\Builder $builder,
        AuditQuery $query,
    ): \Illuminate\Database\Eloquent\Builder {
        if ($query->event !== null) {
            $builder->where('event_name', $query->event->value);
        }
        if ($query->actorId !== null) {
            $builder->where('actor_id', $query->actorId->value);
        }
        if ($query->tenantId !== null) {
            $builder->where('tenant_id', $query->tenantId->value);
        }
        if ($query->since !== null) {
            $builder->where('occurred_at', '>=', $query->since);
        }
        if ($query->until !== null) {
            $builder->where('occurred_at', '<=', $query->until);
        }

        return $builder;
    }
}
