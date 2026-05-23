<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Console;

use Illuminate\Console\Command;
use ModularizeRbac\Core\Application\Ports\ExternalPermissionGateway;
use ModularizeRbac\Core\Application\Ports\RoleModulePermissionRepository;
use ModularizeRbac\Core\Domain\Role\GuardName;
use ModularizeRbac\Core\Domain\RoleModulePermission\RoleModulePermissionSynchronizer;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;
use ModularizeRbac\Laravel\Spatie\NullExternalPermissionGateway;

/**
 * `php artisan access:sync-spatie` — force a one-shot resync of
 * every role-module binding into Spatie's role_has_permissions
 * pivot.
 *
 * Use cases:
 *  - After upgrading from v1 where the legacy observer pathway
 *    diverged from the package's tables.
 *  - After manually editing `role_has_permissions` outside the
 *    package and wanting to reconcile.
 *  - After flipping `access.spatie.enabled` from false to true.
 *
 * No-ops cleanly when the bound gateway is the Null implementation
 * (Spatie not installed or sync explicitly disabled).
 */
final class SyncSpatieCommand extends Command
{
    /** @var string */
    protected $signature = 'access:sync-spatie {--dry-run : log the plan without applying it}';

    /** @var string */
    protected $description = 'Force-resync every role-module binding into Spatie role_has_permissions';

    public function handle(
        ExternalPermissionGateway $gateway,
        RoleModulePermissionRepository $bindings,
    ): int {
        if ($gateway instanceof NullExternalPermissionGateway) {
            $this->warn('Spatie integration is not active — nothing to sync.');
            $this->line('Enable via `access.spatie.enabled` and install spatie/laravel-permission.');

            return self::SUCCESS;
        }

        $sync = new RoleModulePermissionSynchronizer();
        $dryRun = (bool) $this->option('dry-run');
        $roles = RoleEloquent::query()->get();
        $touched = 0;
        $grants = 0;
        $revokes = 0;

        foreach ($roles as $role) {
            $guard = new GuardName((string) $role->guard_name);
            $current = $gateway->permissionsHeldBy(new \ModularizeRbac\Core\Domain\Shared\Uuid($role->id), $guard);

            foreach ($bindings->matrixFor(new \ModularizeRbac\Core\Domain\Shared\Uuid($role->id)) as $row) {
                $plan = $sync->diff($row->module->slug(), $row->permission, $current);
                if ($plan->isNoop()) {
                    continue;
                }
                $touched++;
                $grants += count($plan->toGrant);
                $revokes += count($plan->toRevoke);

                $this->line(sprintf(
                    '  role=%s module=%s grant=%d revoke=%d',
                    $role->id,
                    $row->module->slug()->value,
                    count($plan->toGrant),
                    count($plan->toRevoke),
                ));

                if (! $dryRun) {
                    $gateway->applyPlan(
                        new \ModularizeRbac\Core\Domain\Shared\Uuid($role->id),
                        $guard,
                        $plan->toGrant,
                        $plan->toRevoke,
                    );
                }
            }
        }

        $this->line('');
        $this->line(sprintf(
            '<info>%s</info> bindings touched, %d grants, %d revokes%s',
            $touched,
            $grants,
            $revokes,
            $dryRun ? ' (dry-run — nothing applied)' : '',
        ));

        return self::SUCCESS;
    }
}
