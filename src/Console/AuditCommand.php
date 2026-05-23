<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Console;

use Illuminate\Console\Command;
use ModularizeRbac\Core\Application\Audit\AuditQuery;
use ModularizeRbac\Core\Application\Audit\AuditEntryOutput;
use ModularizeRbac\Core\Application\Ports\AuditRepository;
use ModularizeRbac\Core\Domain\Audit\AuditEventName;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Core\Exceptions\InvalidInput;

/**
 * `php artisan access:audit` — dump audit entries with filters.
 *
 * Bypasses the HTTP/Authorizer boundary (CLI is implicitly trusted)
 * but reuses the same {@see AuditRepository} port. Output formats:
 *   --format=table (default) — prettified Pest-style table
 *   --format=json — newline-delimited JSON for piping
 */
final class AuditCommand extends Command
{
    /** @var string */
    protected $signature = 'access:audit
        {--event= : Filter by event name (e.g. module.created)}
        {--actor= : Filter by actor UUID}
        {--tenant= : Filter by tenant UUID}
        {--since= : ISO-8601 lower bound}
        {--until= : ISO-8601 upper bound}
        {--limit=50 : Max rows to return (1..1000)}
        {--offset=0 : Pagination offset}
        {--format=table : table | json}';

    /** @var string */
    protected $description = 'Dump entries from access_audit_log with optional filters';

    public function handle(AuditRepository $repository): int
    {
        try {
            $query = new AuditQuery(
                event: $this->option('event') ? new AuditEventName((string) $this->option('event')) : null,
                actorId: $this->option('actor') ? new Uuid((string) $this->option('actor')) : null,
                tenantId: $this->option('tenant') ? new Uuid((string) $this->option('tenant')) : null,
                since: $this->parseDate('since'),
                until: $this->parseDate('until'),
                limit: (int) $this->option('limit'),
                offset: (int) $this->option('offset'),
            );
        } catch (InvalidInput $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $entries = $repository->search($query);
        $total = $repository->count($query);

        if ($this->option('format') === 'json') {
            foreach ($entries as $entry) {
                $out = AuditEntryOutput::fromEntity($entry);
                $this->line(json_encode([
                    'id' => $out->id,
                    'event' => $out->event,
                    'actor_id' => $out->actorId,
                    'tenant_id' => $out->tenantId,
                    'payload' => $out->payload,
                    'occurred_at' => $out->occurredAt->format(\DateTimeInterface::ATOM),
                ], JSON_THROW_ON_ERROR));
            }

            return self::SUCCESS;
        }

        if ($entries === []) {
            $this->line('<comment>No audit entries match the filters.</comment>');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                'occurred_at' => $entry->occurredAt->format('Y-m-d H:i:s'),
                'event' => $entry->event->value,
                'actor' => $entry->actorId?->value ?? '-',
                'tenant' => $entry->tenantId?->value ?? '-',
                'payload' => $this->shortPayload($entry->payload),
            ];
        }
        $this->table(['occurred_at', 'event', 'actor', 'tenant', 'payload'], $rows);
        $this->line(sprintf('<info>%d shown / %d total</info>', count($entries), $total));

        return self::SUCCESS;
    }

    private function parseDate(string $option): ?\DateTimeImmutable
    {
        $raw = $this->option($option);
        if (! $raw) {
            return null;
        }

        return new \DateTimeImmutable((string) $raw);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shortPayload(array $payload): string
    {
        $rendered = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! is_string($rendered)) {
            return '';
        }
        if (strlen($rendered) <= 60) {
            return $rendered;
        }

        return substr($rendered, 0, 57).'...';
    }
}
