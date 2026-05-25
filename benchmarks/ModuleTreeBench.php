<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Benchmarks;

use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Ports\ModuleRepository;

/**
 * Measures `ModuleRepository::allActiveTree()` over varying module
 * counts, with cache enabled vs disabled. Validates the v2.3.0
 * cache layer's payoff under the same workload.
 */
class ModuleTreeBench extends BenchTestCase
{
    private ModuleRepository $repo;

    public function setUp(array $params): void
    {
        $this->bootApp();

        \Illuminate\Support\Facades\Gate::before(
            fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): bool => true,
        );

        // Toggle cache per param set
        $cacheEnabled = (bool) ($params['cache'] ?? true);
        $this->app['config']->set('access.cache.enabled', $cacheEnabled);
        $this->app->forgetInstance(ModuleRepository::class);

        $moduleCount = (int) ($params['moduleCount'] ?? 10);

        $createModule = $this->app->make(CreateModule::class);
        for ($i = 0; $i < $moduleCount; $i++) {
            $createModule->execute(new CreateModuleInput("module{$i}", "Module {$i}", null, null, null));
        }

        $this->repo = $this->app->make(ModuleRepository::class);

        // Warm the cache (so we measure hot reads, not first miss)
        if ($cacheEnabled) {
            $this->repo->allActiveTree();
        }
    }

    /**
     * @ParamProviders({"provideParams"})
     * @Subject
     * @Revs(5)
     * @Iterations(3)
     * @BeforeMethods({"setUp"})
     */
    public function benchAllActiveTree(array $params): void
    {
        $this->repo->allActiveTree();
    }

    public function provideParams(): \Generator
    {
        yield 'modules_10_cold' => ['moduleCount' => 10, 'cache' => false];
        yield 'modules_10_hot' => ['moduleCount' => 10, 'cache' => true];
        yield 'modules_100_cold' => ['moduleCount' => 100, 'cache' => false];
        yield 'modules_100_hot' => ['moduleCount' => 100, 'cache' => true];
        yield 'modules_500_cold' => ['moduleCount' => 500, 'cache' => false];
        yield 'modules_500_hot' => ['moduleCount' => 500, 'cache' => true];
    }
}
