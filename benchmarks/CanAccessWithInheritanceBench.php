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
 * Measures `canAccess()` with `access.inheritance.enabled = true`.
 * The bridge currently loads ALL modules from DB on every call to
 * build the parent map — this benchmark exposes that cost across
 * 10, 100, 500 modules.
 */
class CanAccessWithInheritanceBench extends BenchTestCase
{
    /** @var BenchUserI&\Illuminate\Database\Eloquent\Model */
    private $user;

    public function setUp(array $params): void
    {
        $this->bootApp();
        $this->app['config']->set('access.inheritance.enabled', true);
        $this->app['config']->set('access.cache.enabled', false); // measure raw cost

        \Illuminate\Support\Facades\Gate::before(
            fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): ?bool => str_starts_with($ability, 'admin.') ? true : null,
        );

        $moduleCount = (int) ($params['moduleCount'] ?? 10);

        $this->user = new BenchUserI();
        $this->user->id = (string) Str::uuid();
        $this->user->save();

        $createModule = $this->app->make(CreateModule::class);
        $syncRoleModules = $this->app->make(SyncRoleModules::class);

        // Tree: 1 root + (moduleCount-1) flat children of the root
        $root = $createModule->execute(new CreateModuleInput('events', 'Events', null, null, null));
        for ($i = 1; $i < $moduleCount; $i++) {
            $createModule->execute(new CreateModuleInput("events.child{$i}", "Child {$i}", null, null, $root->id));
        }

        $role = new RoleEloquent();
        $role->id = (string) Str::uuid();
        $role->name = 'r';
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

        // Root has view; descendants inherit
        $syncRoleModules->execute(new SyncRoleModulesInput(
            roleId: $role->id,
            modules: [['module_id' => $root->id, 'is_reading_allowed' => true]],
        ));
    }

    /**
     * @ParamProviders({"provideModuleCount"})
     * @Subject
     * @Revs(5)
     * @Iterations(3)
     * @BeforeMethods({"setUp"})
     */
    public function benchCanAccessInheritance(array $params): void
    {
        // Hit a child slug so the resolver actually walks
        $this->user->canAccess('events.child1.view');
    }

    public function provideModuleCount(): \Generator
    {
        yield 'modules_10' => ['moduleCount' => 10];
        yield 'modules_100' => ['moduleCount' => 100];
        yield 'modules_500' => ['moduleCount' => 500];
    }
}

class BenchUserI extends Authenticatable
{
    use HasAccessPermissions;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'users';

    protected $guarded = [];
}
