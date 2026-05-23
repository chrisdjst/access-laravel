<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use ModularizeRbac\Core\Application\Ports\AuditRepository;
use ModularizeRbac\Core\Application\Ports\ExternalPermissionGateway;
use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Laravel\Spatie\NullExternalPermissionGateway;
use ModularizeRbac\Laravel\Spatie\SpatiePermissionGateway;

/**
 * `php artisan access:diagnose` — non-destructive health check.
 *
 * Verifies:
 *  - Required tables are present (migrations applied).
 *  - At least one Language exists and exactly one is the default.
 *  - The ExternalPermissionGateway binding matches what
 *    `config('access.spatie.enabled')` implies given whether
 *    Spatie is installed.
 *  - The audit table is reachable (smoke read).
 *
 * Returns a non-zero exit code when any check fails — useful as a
 * pre-deploy step in CI / staging.
 */
final class DiagnoseCommand extends Command
{
    /** @var string */
    protected $signature = 'access:diagnose';

    /** @var string */
    protected $description = 'Health check for the modularize-rbac/laravel installation';

    public function __construct(
        private readonly Container $container,
        private readonly ConnectionInterface $db,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $failed = false;

        $this->line('<info>access:diagnose</info>');
        $this->line('');

        $required = [
            'roles', 'permissions', 'role_has_permissions',
            'modules', 'module_permissions', 'role_module_permission',
            'languages', 'translations', 'module_prices',
            'role_user', 'access_audit_log',
        ];
        foreach ($required as $table) {
            if ($this->db->getSchemaBuilder()->hasTable($table)) {
                $this->line(sprintf('  [<fg=green>OK</>] table %s', $table));
                continue;
            }
            $this->line(sprintf('  [<fg=red>MISSING</>] table %s — run `php artisan migrate`', $table));
            $failed = true;
        }

        $this->line('');

        $languages = $this->container->make(LanguageRepository::class)->all();
        $defaults = array_filter($languages, fn ($l) => $l->isDefault());
        if (count($languages) === 0) {
            $this->line('  [<fg=yellow>WARN</>] no Language rows yet — translations will fall back to raw values');
        } elseif (count($defaults) === 0) {
            $this->line('  [<fg=red>FAIL</>] '.count($languages).' Language rows but none marked default');
            $failed = true;
        } elseif (count($defaults) > 1) {
            $this->line('  [<fg=red>FAIL</>] more than one Language marked default — fix manually');
            $failed = true;
        } else {
            /** @var \ModularizeRbac\Core\Domain\Translation\Language $default */
            $default = array_values($defaults)[0];
            $this->line(sprintf('  [<fg=green>OK</>] default language: %s', $default->code()->value));
        }

        $this->line('');

        $spatieInstalled = class_exists(\Spatie\Permission\PermissionRegistrar::class);
        $configured = config('access.spatie.enabled');
        $gateway = $this->container->make(ExternalPermissionGateway::class);

        $this->line(sprintf('  Spatie installed:     %s', $spatieInstalled ? '<fg=green>yes</>' : '<fg=yellow>no</>'));
        $this->line(sprintf('  access.spatie.enabled: %s', match (true) {
            $configured === null => '<fg=cyan>null (auto)</>',
            $configured === true => '<fg=green>true</>',
            default => '<fg=yellow>false</>',
        }));
        $this->line(sprintf('  Bound gateway:        %s', $gateway::class));

        if ($configured === true && ! $spatieInstalled) {
            $this->line('  [<fg=red>FAIL</>] access.spatie.enabled is forced on but Spatie is not installed');
            $failed = true;
        } elseif ($gateway instanceof SpatiePermissionGateway && ! $spatieInstalled) {
            $this->line('  [<fg=red>FAIL</>] SpatiePermissionGateway bound without Spatie present');
            $failed = true;
        } elseif ($gateway instanceof NullExternalPermissionGateway && $spatieInstalled && $configured !== false) {
            $this->line('  [<fg=yellow>WARN</>] Spatie is installed but the Null gateway is bound — sync inactive');
        } else {
            $this->line('  [<fg=green>OK</>] gateway / Spatie wiring is consistent');
        }

        $this->line('');

        $auditEnabled = (bool) config('access.audit.enabled', true);
        if ($auditEnabled) {
            try {
                $repo = $this->container->make(AuditRepository::class);
                $repo->count(new \ModularizeRbac\Core\Application\Audit\AuditQuery());
                $this->line('  [<fg=green>OK</>] audit log reachable');
            } catch (\Throwable $e) {
                $this->line(sprintf('  [<fg=red>FAIL</>] audit log unreachable: %s', $e->getMessage()));
                $failed = true;
            }
        } else {
            $this->line('  [<fg=yellow>INFO</>] audit log disabled via access.audit.enabled = false');
        }

        $this->line('');
        $this->line($failed ? '<fg=red>diagnose failed</>' : '<fg=green>all checks passed</>');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
