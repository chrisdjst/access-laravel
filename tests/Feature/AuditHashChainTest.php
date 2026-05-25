<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): bool => true);
    // Reset every table this suite touches so unique constraints
    // (modules.slug) don't trip when tests share schema state.
    DB::table('access_audit_log')->delete();
    DB::table('role_module_permission')->delete();
    DB::table('modules')->delete();
});

it('leaves hash columns NULL when hash_chain.enabled is false (default)', function (): void {
    config()->set('access.audit.hash_chain.enabled', false);

    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));

    $row = DB::table('access_audit_log')->first();
    expect($row->entry_hash)->toBeNull()
        ->and($row->previous_hash)->toBeNull();
});

it('writes entry_hash + null previous_hash for the first row of a partition', function (): void {
    config()->set('access.audit.hash_chain.enabled', true);

    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));

    $row = DB::table('access_audit_log')->where('event_name', 'module.created')->first();
    expect($row->entry_hash)->not->toBeNull()
        ->and(strlen($row->entry_hash))->toBe(64) // sha256 hex
        ->and($row->previous_hash)->toBeNull();
});

it('chains subsequent rows of the same partition', function (): void {
    config()->set('access.audit.hash_chain.enabled', true);

    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));
    app(CreateModule::class)->execute(new CreateModuleInput('billing', 'Billing', null, null, null));

    $rows = DB::table('access_audit_log')
        ->where('event_name', 'module.created')
        ->get();

    // occurred_at often shares the same second across two synchronous
    // calls in the same test, so deterministic ordering by it is
    // unreliable. Identify the first vs second by previous_hash being
    // null vs not — the first row is whichever has no predecessor.
    $first = $rows->firstWhere(fn ($r) => $r->previous_hash === null);
    $second = $rows->firstWhere(fn ($r) => $r->previous_hash !== null);

    expect($rows)->toHaveCount(2)
        ->and($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second->previous_hash)->toBe($first->entry_hash);
});

it('access:audit:verify exits 0 on an intact chain', function (): void {
    config()->set('access.audit.hash_chain.enabled', true);

    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));
    app(CreateModule::class)->execute(new CreateModuleInput('billing', 'Billing', null, null, null));

    $this->artisan('access:audit:verify')->assertExitCode(0);
});

it('access:audit:verify exits 1 after a tampered payload', function (): void {
    config()->set('access.audit.hash_chain.enabled', true);

    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));

    // Tamper with the row WITHOUT recomputing the hash
    DB::table('access_audit_log')->update(['payload' => json_encode(['slug' => 'tampered'])]);

    $this->artisan('access:audit:verify')->assertExitCode(1);
});

it('access:audit:verify skips rows with NULL entry_hash silently', function (): void {
    // Mix: first write with chain off, second with chain on
    config()->set('access.audit.hash_chain.enabled', false);
    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));

    config()->set('access.audit.hash_chain.enabled', true);
    app(CreateModule::class)->execute(new CreateModuleInput('billing', 'Billing', null, null, null));

    $this->artisan('access:audit:verify')->assertExitCode(0);
});
