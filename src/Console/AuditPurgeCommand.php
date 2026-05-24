<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Console;

use DateTimeImmutable;
use Illuminate\Console\Command;
use ModularizeRbac\Core\Application\Audit\AuditQuery;
use ModularizeRbac\Core\Application\Ports\AuditRepository;
use Throwable;

/**
 * `php artisan access:audit:purge` — bulk-removes audit entries
 * older than a cutoff. Intended to be scheduled by the host (e.g.
 * via Laravel's scheduler) for retention compliance.
 *
 *   php artisan access:audit:purge --older-than=90d
 *   php artisan access:audit:purge --older-than=2026-01-01
 *   php artisan access:audit:purge --older-than=30d --dry-run
 *
 * `--older-than` accepts either:
 *   - a relative interval like `90d`, `6m`, `1y` (suffix d/m/y), or
 *   - an absolute ISO-8601 date / datetime.
 *
 * The command never touches entries with `occurred_at >= cutoff`
 * (strict <). Returns the count of rows removed; `--dry-run` skips
 * the DELETE and reports what would have been removed instead.
 */
final class AuditPurgeCommand extends Command
{
    /** @var string */
    protected $signature = 'access:audit:purge
        {--older-than=90d : Cutoff — interval (Nd/Nm/Ny) or absolute ISO-8601 date}
        {--dry-run : Report how many rows match without deleting}';

    /** @var string */
    protected $description = 'Bulk-delete audit log entries older than the cutoff';

    public function handle(AuditRepository $repository): int
    {
        try {
            $cutoff = $this->resolveCutoff((string) $this->option('older-than'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $matching = $repository->count(new AuditQuery(
                until: $cutoff->modify('-1 second'),
                limit: 1000,
            ));
            $this->info(sprintf(
                '%d entries would be purged (older than %s) [dry-run]',
                $matching,
                $cutoff->format(\DateTimeInterface::ATOM),
            ));

            return self::SUCCESS;
        }

        $removed = $repository->deleteOlderThan($cutoff);
        $this->info(sprintf(
            '%d entries purged (older than %s)',
            $removed,
            $cutoff->format(\DateTimeInterface::ATOM),
        ));

        return self::SUCCESS;
    }

    /**
     * Parse an `--older-than` value into a concrete cutoff timestamp.
     */
    private function resolveCutoff(string $raw): DateTimeImmutable
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('--older-than cannot be empty.');
        }

        if (preg_match('/^(\d+)([dmy])$/', $trimmed, $matches) === 1) {
            $value = (int) $matches[1];
            $unit = match ($matches[2]) {
                'd' => 'days',
                'm' => 'months',
                'y' => 'years',
            };

            return (new DateTimeImmutable('now'))->modify("-{$value} {$unit}");
        }

        try {
            return new DateTimeImmutable($trimmed);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                "Could not parse --older-than value: {$raw}. Expected Nd/Nm/Ny or ISO-8601.",
                0,
                $e,
            );
        }
    }
}
