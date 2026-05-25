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
 * Measures the cost of `canAccess()` when the granting role lives at
 * the top of a `parent_role_id` chain — the user holds a leaf role
 * that inherits through 1, 5, or 10 ancestors.
 *
 * Pre-P3 this is N queries (one per chain step). Post-P3 it should
 * collapse to ~2 queries via batched whereIn / recursive CTE.
 */
class CanAccessWithHierarchyBench extends BenchTestCase
{
    /** @var BenchUserH&\Illuminate\Database\Eloquent\Model */
    private $user;

    public function setUp(array $params): void
    {
        $this->bootApp();

        \Illuminate\Support\Facades\Gate::before(
            fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): ?bool => str_starts_with($ability, 'admin.') ? true : null,
        );

        $depth = (int) ($params['depth'] ?? 1);

        $this->user = new BenchUserH();
        $this->user->id = (string) Str::uuid();
        $this->user->save();

        $createModule = $this->app->make(CreateModule::class);
        $module = $createModule->execute(new CreateModuleInput('events', 'Events', null, null, null));
        $syncRoleModules = $this->app->make(SyncRoleModules::class);

        // Build the chain: root -> mid_1 -> mid_2 -> ... -> leaf
        $previousId = null;
        $rootId = null;
        for ($i = 0; $i < $depth; $i++) {
            $role = new RoleEloquent();
            $role->id = (string) Str::uuid();
            $role->name = 'role_h_'.$i;
            $role->display_name = 'Role H '.$i;
            $role->guard_name = 'web';
            $role->level = 0;
            $role->is_system = false;
            $role->parent_role_id = $previousId;
            $role->save();
            if ($i === 0) {
                $rootId = $role->id;
            }
            $previousId = $role->id;
        }

        // Root has the binding; leaf inherits via the chain
        $syncRoleModules->execute(new SyncRoleModulesInput(
            roleId: $rootId,
            modules: [['module_id' => $module->id, 'is_reading_allowed' => true]],
        ));

        // User holds the LEAF role
        DB::table('role_user')->insert([
            'role_id' => $previousId,
            'user_id' => $this->user->id,
            'organization_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @ParamProviders({"provideDepth"})
     * @Subject
     * @Revs(5)
     * @Iterations(3)
     * @BeforeMethods({"setUp"})
     */
    public function benchCanAccessHierarchy(array $params): void
    {
        $this->user->canAccess('events.view');
    }

    public function provideDepth(): \Generator
    {
        yield 'depth_1' => ['depth' => 1];
        yield 'depth_5' => ['depth' => 5];
        yield 'depth_10' => ['depth' => 10];
    }
}

class BenchUserH extends Authenticatable
{
    use HasAccessPermissions;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'users';

    protected $guarded = [];
}
