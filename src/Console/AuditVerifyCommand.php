<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Console;

use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Walks the (tenant_id, event_name) hash chains in `access_audit_log`
 * and reports any row whose `entry_hash` doesn't equal
 * `sha256(previous_hash || canonical(row))`.
 *
 * Rows with NULL hash columns (created before hash_chain was enabled
 * or while it was off) are skipped — the verify command only checks
 * the rows that opted in to the chain.
 *
 * Exit code 0 → chain intact. Exit code 1 → at least one break found
 * (CI/CD pipelines + ops dashboards can wire this into a periodic job).
 */
class AuditVerifyCommand extends Command
{
    protected $signature = 'access:audit:verify
        {--since= : Only verify rows with occurred_at >= this ISO-8601 timestamp}
        {--event= : Restrict to a single event_name partition}';

    protected $description = 'Verify the audit log hash chain. Exits 1 on any break.';

    public function handle(): int
    {
        $base = DB::table('access_audit_log')->whereNotNull('entry_hash');

        if (is_string($since = $this->option('since')) && $since !== '') {
            $base->where('occurred_at', '>=', $since);
        }
        if (is_string($event = $this->option('event')) && $event !== '') {
            $base->where('event_name', $event);
        }

        // Discover every (tenant_id, event_name) partition the chain
        // touches so we can validate each independently.
        $partitions = (clone $base)
            ->select('tenant_id', 'event_name')
            ->distinct()
            ->get();

        $breaks = [];
        $partitionsVerified = 0;

        foreach ($partitions as $p) {
            $rows = (clone $base)
                ->when(
                    $p->tenant_id === null,
                    fn ($q) => $q->whereNull('tenant_id'),
                    fn ($q) => $q->where('tenant_id', $p->tenant_id),
                )
                ->where('event_name', $p->event_name)
                ->get();

            // Walk the chain by following previous_hash → entry_hash.
            // ID ordering would be wrong because v4 UUIDs aren't time-
            // sortable; the chain links themselves ARE deterministic.
            /** @var array<string, object> $byPrev */
            $byPrev = [];
            $firstRow = null;
            foreach ($rows as $row) {
                if ($row->previous_hash === null) {
                    $firstRow = $row;
                } else {
                    $byPrev[(string) $row->previous_hash] = $row;
                }
            }

            if ($firstRow === null) {
                // Every row in this partition references a predecessor
                // — that means the head was deleted/tampered. Report
                // the whole partition as broken.
                foreach ($rows as $row) {
                    $breaks[] = ['id' => (string) $row->id, 'reason' => 'partition missing head'];
                }
                continue;
            }

            $expectedPrev = null;
            $cursor = $firstRow;
            $visited = 0;
            while ($cursor !== null) {
                $visited++;
                if (($cursor->previous_hash ?? null) !== $expectedPrev) {
                    $breaks[] = ['id' => (string) $cursor->id, 'reason' => 'previous_hash mismatch'];
                    break;
                }

                $occurredAt = $cursor->occurred_at instanceof DateTimeInterface
                    ? $cursor->occurred_at
                    : new \DateTimeImmutable((string) $cursor->occurred_at);

                $canonical = json_encode([
                    'id' => (string) $cursor->id,
                    'event' => $cursor->event_name,
                    'actor_id' => $cursor->actor_id,
                    'tenant_id' => $cursor->tenant_id,
                    'payload' => is_string($cursor->payload) ? json_decode($cursor->payload, true) : $cursor->payload,
                    'occurred_at' => $occurredAt->format(DateTimeInterface::ATOM),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $expectedHash = hash('sha256', ($expectedPrev ?? '').'|'.$canonical);
                if ($expectedHash !== $cursor->entry_hash) {
                    $breaks[] = ['id' => (string) $cursor->id, 'reason' => 'entry_hash mismatch'];
                    break;
                }

                $expectedPrev = (string) $cursor->entry_hash;
                $cursor = $byPrev[$expectedPrev] ?? null;
            }

            if ($visited !== count($rows)) {
                $breaks[] = ['id' => '(partition '.$p->event_name.')', 'reason' => sprintf('orphan rows after walk (%d / %d visited)', $visited, count($rows))];
            }

            $partitionsVerified++;
        }

        if ($breaks === []) {
            $this->info(sprintf(
                'audit chain verified: %d partitions, no breaks.',
                $partitionsVerified,
            ));

            return self::SUCCESS;
        }

        $this->error(sprintf('audit chain broken: %d entries failed verification.', count($breaks)));
        foreach ($breaks as $break) {
            $this->line(sprintf('  - %s (%s)', $break['id'], $break['reason']));
        }

        return self::FAILURE;
    }
}
