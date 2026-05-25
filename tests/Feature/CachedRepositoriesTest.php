<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Core\Application\Ports\ModuleRepository;
use ModularizeRbac\Laravel\Cache\CachedLanguageRepository;
use ModularizeRbac\Laravel\Cache\CachedModuleRepository;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
    Cache::flush();
});

it('binds Cached*Repository decorators when access.cache.enabled is true', function (): void {
    config()->set('access.cache.enabled', true);

    expect($this->app->make(ModuleRepository::class))->toBeInstanceOf(CachedModuleRepository::class)
        ->and($this->app->make(LanguageRepository::class))->toBeInstanceOf(CachedLanguageRepository::class);
});

it('binds the plain Eloquent adapters when access.cache.enabled is false', function (): void {
    config()->set('access.cache.enabled', false);
    // Forget any pre-resolved singleton from a prior test
    $this->app->forgetInstance(ModuleRepository::class);
    $this->app->forgetInstance(LanguageRepository::class);

    expect($this->app->make(ModuleRepository::class))
        ->toBeInstanceOf(\ModularizeRbac\Laravel\Eloquent\Repositories\EloquentModuleRepository::class)
        ->and($this->app->make(LanguageRepository::class))
        ->toBeInstanceOf(\ModularizeRbac\Laravel\Eloquent\Repositories\EloquentLanguageRepository::class);
});

it('serves repeated allActiveTree() reads from cache (no DB query after the warm-up)', function (): void {
    config()->set('access.cache.enabled', true);
    $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->assertCreated();

    $repo = $this->app->make(ModuleRepository::class);

    // Warm-up
    $first = $repo->allActiveTree();

    // Measure how many SELECT queries the second call costs
    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    $second = $repo->allActiveTree();

    expect($queryCount)->toBe(0)
        ->and(count($second))->toBe(count($first));
});

it('serves repeated language all() reads from cache', function (): void {
    config()->set('access.cache.enabled', true);
    $this->postJson('/api/admin/languages', ['code' => 'en', 'name' => 'English'])->assertCreated();

    $repo = $this->app->make(LanguageRepository::class);
    $first = $repo->all();

    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    $second = $repo->all();

    expect($queryCount)->toBe(0)
        ->and(count($second))->toBe(count($first));
});

it('invalidates the module cache when a new module is created via the use-case', function (): void {
    config()->set('access.cache.enabled', true);
    $repo = $this->app->make(ModuleRepository::class);

    // Warm the cache with an empty tree
    expect($repo->allActiveTree())->toBe([]);

    // Create a module — should bump the version on save (and on the
    // ModuleCreated event, defence-in-depth)
    $this->postJson('/api/admin/modules', ['slug' => 'events', 'name' => 'Events'])->assertCreated();

    // The next read MUST see the new module
    expect(count($repo->allActiveTree()))->toBe(1);
});

it('invalidates the language cache when a language is added', function (): void {
    config()->set('access.cache.enabled', true);
    $repo = $this->app->make(LanguageRepository::class);

    expect($repo->all())->toBe([]);

    $this->postJson('/api/admin/languages', ['code' => 'en', 'name' => 'English'])->assertCreated();

    expect(count($repo->all()))->toBe(1);
});

it('cached find() returns null for an unknown id and remembers the negative result', function (): void {
    config()->set('access.cache.enabled', true);
    $repo = $this->app->make(LanguageRepository::class);

    $unknown = new \ModularizeRbac\Core\Domain\Shared\Uuid('99999999-9999-9999-9999-999999999999');
    expect($repo->find($unknown))->toBeNull();

    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });
    expect($repo->find($unknown))->toBeNull();
    expect($queryCount)->toBe(0);
});
