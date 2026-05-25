<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Ports\ModuleRepository;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModules;
use ModularizeRbac\Core\Application\Role\SyncRoleModules\SyncRoleModulesInput;
use ModularizeRbac\Laravel\Concerns\HasAccessPermissions;
use ModularizeRbac\Laravel\Events\Telemetry\AbilityResolved;
use ModularizeRbac\Laravel\Events\Telemetry\CacheLookup;
use ModularizeRbac\Laravel\Models\Role as RoleEloquent;

class TelemetryUser extends Authenticatable
{
    use HasAccessPermissions;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): bool => true);
});

it('emits AbilityResolved with source=direct when a binding directly grants', function (): void {
    Event::fake([AbilityResolved::class]);

    $user = new TelemetryUser();
    $user->id = (string) Str::uuid();
    $user->save();

    $role = new RoleEloquent();
    $role->id = (string) Str::uuid();
    $role->name = 'r';
    $role->guard_name = 'web';
    $role->level = 0;
    $role->is_system = false;
    $role->save();
    DB::table('role_user')->insert([
        'role_id' => $role->id,
        'user_id' => $user->id,
        'organization_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $events = app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));
    app(SyncRoleModules::class)->execute(new SyncRoleModulesInput(
        roleId: $role->id,
        modules: [['module_id' => $events->id, 'is_reading_allowed' => true]],
    ));

    expect($user->canAccess('events.view'))->toBeTrue();

    Event::assertDispatched(AbilityResolved::class, function (AbilityResolved $e): bool {
        return $e->ability === 'events.view'
            && $e->allowed === true
            && $e->source === 'direct'
            && $e->durationMicros >= 0;
    });
});

it('emits AbilityResolved with source=none when nothing grants', function (): void {
    Event::fake([AbilityResolved::class]);

    $user = new TelemetryUser();
    $user->id = (string) Str::uuid();
    $user->save();

    expect($user->canAccess('events.view'))->toBeFalse();

    Event::assertDispatched(AbilityResolved::class, function (AbilityResolved $e): bool {
        return $e->source === 'none' && $e->allowed === false;
    });
});

it('emits AbilityResolved with source=malformed on bad ability strings', function (): void {
    Event::fake([AbilityResolved::class]);

    $user = new TelemetryUser();
    $user->id = (string) Str::uuid();
    $user->save();

    $user->canAccess('view-dashboard');

    Event::assertDispatched(AbilityResolved::class, function (AbilityResolved $e): bool {
        return $e->source === 'malformed';
    });
});

it('emits CacheLookup hit=false on cold reads and hit=true on warm reads', function (): void {
    config()->set('access.cache.enabled', true);
    \Illuminate\Support\Facades\Cache::flush();

    Event::fake([CacheLookup::class]);

    $repo = app(ModuleRepository::class);

    $repo->allActiveTree(); // cold
    $repo->allActiveTree(); // warm

    Event::assertDispatched(CacheLookup::class, function (CacheLookup $e): bool {
        return $e->namespace === 'access:module' && $e->key === 'tree' && $e->hit === false;
    });
    Event::assertDispatched(CacheLookup::class, function (CacheLookup $e): bool {
        return $e->namespace === 'access:module' && $e->key === 'tree' && $e->hit === true;
    });
});
