<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Benchmarks;

use ModularizeRbac\Core\Application\Module\BulkCreateModules\BulkCreateModules;
use ModularizeRbac\Core\Application\Module\BulkCreateModules\BulkCreateModulesInput;

/**
 * Measures `BulkCreateModules` use-case over batch sizes 10/100/500
 * with audit enabled vs disabled. Surfaces the cost of the inline
 * AuditingListener writes that fire once per created module.
 */
class BulkCreateModulesBench extends BenchTestCase
{
    /** @var list<array<string, mixed>> */
    private array $payload = [];

    public function setUp(array $params): void
    {
        $this->bootApp();

        \Illuminate\Support\Facades\Gate::before(
            fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): bool => true,
        );

        $auditEnabled = (bool) ($params['audit'] ?? true);
        $this->app['config']->set('access.audit.enabled', $auditEnabled);

        // Reset the DomainEventDispatcher binding so the new audit
        // flag takes effect (the singleton captured the previous value)
        $this->app->forgetInstance(\ModularizeRbac\Core\Application\Ports\DomainEventDispatcher::class);

        $count = (int) ($params['count'] ?? 10);
        $this->payload = [];
        for ($i = 0; $i < $count; $i++) {
            $this->payload[] = ['slug' => "mod{$i}", 'name' => "Mod {$i}"];
        }
    }

    /**
     * @ParamProviders({"provideParams"})
     * @Subject
     * @Revs(1)
     * @Iterations(3)
     * @BeforeMethods({"setUp"})
     */
    public function benchBulkCreate(array $params): void
    {
        // Wipe so subjects don't trip the unique slug constraint between revs.
        // (revs=1 above keeps this to one execution per subject iteration.)
        \Illuminate\Support\Facades\DB::table('modules')->delete();
        \Illuminate\Support\Facades\DB::table('access_audit_log')->delete();

        $useCase = $this->app->make(BulkCreateModules::class);
        $useCase->execute(new BulkCreateModulesInput($this->payload));
    }

    public function provideParams(): \Generator
    {
        yield 'count_10_audit_on' => ['count' => 10, 'audit' => true];
        yield 'count_10_audit_off' => ['count' => 10, 'audit' => false];
        yield 'count_100_audit_on' => ['count' => 100, 'audit' => true];
        yield 'count_100_audit_off' => ['count' => 100, 'audit' => false];
    }
}
