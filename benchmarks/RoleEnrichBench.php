<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Benchmarks;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

/**
 * Measures `GET /api/admin/roles` end-to-end across role counts.
 * The current controller calls `enrich()` once per role, which hits
 * `LanguageRepository::all()` and `RoleModulePermissionRepository::forRole()`
 * inside the loop — classic N+1 territory pre-cache.
 */
class RoleEnrichBench extends BenchTestCase
{
    public function setUp(array $params): void
    {
        $this->bootApp();

        Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): bool => true);

        $roleCount = (int) ($params['roleCount'] ?? 10);

        $createModule = $this->app->make(CreateModule::class);
        $module = $createModule->execute(new CreateModuleInput('events', 'Events', null, null, null));
        $syncRoleModules = $this->app->make(SyncRoleModules::class);

        for ($i = 0; $i < $roleCount; $i++) {
            $role = new RoleEloquent();
            $role->id = (string) Str::uuid();
            $role->name = 'role_'.$i;
            $role->display_name = 'Role '.$i;
            $role->guard_name = 'web';
            $role->level = 0;
            $role->is_system = false;
            $role->save();

            $syncRoleModules->execute(new SyncRoleModulesInput(
                roleId: $role->id,
                modules: [['module_id' => $module->id, 'is_reading_allowed' => true]],
            ));
        }
    }

    /**
     * @ParamProviders({"provideRoleCount"})
     * @Subject
     * @Revs(3)
     * @Iterations(3)
     * @BeforeMethods({"setUp"})
     */
    public function benchListRoles(array $params): void
    {
        $response = $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
            ->handle(\Illuminate\Http\Request::create('/api/admin/roles', 'GET'));
        // Ensure response is consumed so we don't measure partial work
        $response->getContent();
    }

    public function provideRoleCount(): \Generator
    {
        yield 'roles_10' => ['roleCount' => 10];
        yield 'roles_50' => ['roleCount' => 50];
        yield 'roles_200' => ['roleCount' => 200];
    }
}
