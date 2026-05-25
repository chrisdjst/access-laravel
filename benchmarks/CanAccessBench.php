<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Benchmarks;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Laravel\Concerns\HasAccessPermissions;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

/**
 * Benchmarks {@see HasAccessPermissions::canAccess()} across different
 * role-count scenarios. Establishes the cost of the hot read path that
 * runs on every `$user->can('...')` call in the host app.
 *
 * Parametrized over 1, 5, 10 directly-assigned roles. No hierarchy
 * (parent_role_id is null) — see {@see CanAccessWithHierarchyBench}
 * for the hierarchical variant.
 */
class CanAccessBench extends BenchTestCase
{
    /** @var BenchUser&\Illuminate\Database\Eloquent\Model */
    private $user;

    public function setUp(array $params): void
    {
        $this->bootApp();

        \Illuminate\Support\Facades\Gate::before(
            fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): ?bool => str_starts_with($ability, 'admin.') ? true : null,
        );

        $roleCount = (int) ($params['roleCount'] ?? 1);

        $this->user = new BenchUser();
        $this->user->id = (string) Str::uuid();
        $this->user->save();

        $createModule = $this->app->make(CreateModule::class);
        $syncRoleModules = $this->app->make(SyncRoleModules::class);

        $module = $createModule->execute(new CreateModuleInput('events', 'Events', null, null, null));

        for ($i = 0; $i < $roleCount; $i++) {
            $role = new RoleEloquent();
            $role->id = (string) Str::uuid();
            $role->name = 'role_'.$i;
            $role->display_name = 'Role '.$i;
            $role->guard_name = 'web';
            $role->level = 0;
            $role->is_system = false;
            $role->save();

            DB::table('role_user')->insert([
                'role_id' => $role->id,
                'user_id' => $this->user->id,
                'organization_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Only the last role gets the binding — measures the cost
            // of iterating roles to find the granting one
            if ($i === $roleCount - 1) {
                $syncRoleModules->execute(new SyncRoleModulesInput(
                    roleId: $role->id,
                    modules: [['module_id' => $module->id, 'is_reading_allowed' => true]],
                ));
            }
        }
    }

    /**
     * @ParamProviders({"provideRoleCount"})
     * @Subject
     * @Revs(5)
     * @Iterations(3)
     * @BeforeMethods({"setUp"})
     */
    public function benchCanAccess(array $params): void
    {
        $this->user->canAccess('events.view');
    }

    public function provideRoleCount(): \Generator
    {
        yield 'roles_1' => ['roleCount' => 1];
        yield 'roles_5' => ['roleCount' => 5];
        yield 'roles_10' => ['roleCount' => 10];
    }
}

/**
 * Minimal user model for the benchmark — needs the trait to expose
 * canAccess() on a real Eloquent record so DB queries are exercised.
 */
class BenchUser extends Authenticatable
{
    use HasAccessPermissions;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'users';

    protected $guarded = [];
}
