<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Application\Module\ListModules\ListModules;
use ModularizeRbac\Core\Application\Module\ShowModule\ShowModule;
use ModularizeRbac\Laravel\Models\Module as ModuleEloquent;

beforeEach(function (): void {
    // Anonymous-friendly bypass — production hosts wire real policies;
    // these tests verify the use-case + Eloquent wiring, not authz.
    // Type-hint as nullable so the gate accepts guest callers.
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

it('persists a module via CreateModule use-case and reads it back', function (): void {
    /** @var CreateModule $create */
    $create = $this->app->make(CreateModule::class);
    $out = $create->execute(new CreateModuleInput(
        slug: 'events',
        name: 'Events',
        redirect: '/events',
        icon: 'calendar',
        rootModuleId: null,
        sortOrder: 10,
    ));

    expect($out->slug)->toBe('events');

    // Round-trips through Eloquent
    $row = ModuleEloquent::query()->where('slug', 'events')->first();
    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Events')
        ->and($row->sort_order)->toBe(10);

    /** @var ShowModule $show */
    $show = $this->app->make(ShowModule::class);
    $back = $show->execute($out->id);
    expect($back->slug)->toBe('events')->and($back->name)->toBe('Events');

    /** @var ListModules $list */
    $list = $this->app->make(ListModules::class);
    expect($list->execute())->toHaveCount(1);
});

it('rejects duplicate slugs at the use-case layer (no DB constraint hit)', function (): void {
    /** @var CreateModule $create */
    $create = $this->app->make(CreateModule::class);
    $create->execute(new CreateModuleInput('events', 'Events', null, null, null));

    expect(fn () => $create->execute(new CreateModuleInput('events', 'Other', null, null, null)))
        ->toThrow(\ModularizeRbac\Core\Exceptions\InvalidInput::class);
});
