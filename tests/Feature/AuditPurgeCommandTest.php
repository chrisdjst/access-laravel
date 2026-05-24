<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
});

function seedAuditAt(string $iso, string $event = 'module.created'): void
{
    DB::table('access_audit_log')->insert([
        'id' => (string) Str::uuid(),
        'event_name' => $event,
        'actor_id' => null,
        'tenant_id' => null,
        'payload' => json_encode([]),
        'occurred_at' => $iso,
    ]);
}

it('access:audit:purge --older-than=Nd removes entries older than N days', function (): void {
    seedAuditAt(now()->subDays(100)->toDateTimeString()); // old
    seedAuditAt(now()->subDays(50)->toDateTimeString());  // old
    seedAuditAt(now()->subDays(10)->toDateTimeString());  // kept

    $this->artisan('access:audit:purge', ['--older-than' => '30d'])
        ->expectsOutputToContain('2 entries purged')
        ->assertSuccessful();

    expect(DB::table('access_audit_log')->count())->toBe(1);
});

it('access:audit:purge --older-than accepts ISO-8601 dates', function (): void {
    seedAuditAt('2026-01-01 00:00:00');
    seedAuditAt('2026-06-01 00:00:00');

    $this->artisan('access:audit:purge', ['--older-than' => '2026-03-01'])
        ->expectsOutputToContain('1')
        ->assertSuccessful();

    expect(DB::table('access_audit_log')->count())->toBe(1);
});

it('access:audit:purge --dry-run reports without deleting', function (): void {
    seedAuditAt(now()->subDays(100)->toDateTimeString());
    seedAuditAt(now()->subDays(50)->toDateTimeString());

    $this->artisan('access:audit:purge', ['--older-than' => '30d', '--dry-run' => true])
        ->expectsOutputToContain('would be purged')
        ->assertSuccessful();

    expect(DB::table('access_audit_log')->count())->toBe(2);
});

it('access:audit:purge accepts Nm (months) and Ny (years) suffixes', function (): void {
    seedAuditAt(now()->subYears(2)->toDateTimeString());
    seedAuditAt(now()->subMonths(8)->toDateTimeString());
    seedAuditAt(now()->subMonths(3)->toDateTimeString()); // kept after 6m

    $this->artisan('access:audit:purge', ['--older-than' => '6m'])
        ->assertSuccessful();

    expect(DB::table('access_audit_log')->count())->toBe(1);
});

it('access:audit:purge fails on a malformed cutoff', function (): void {
    $this->artisan('access:audit:purge', ['--older-than' => 'tomorrow-ish'])
        ->expectsOutputToContain('Could not parse')
        ->assertExitCode(1);
});

it('access:audit:purge reports 0 when no rows match', function (): void {
    seedAuditAt(now()->subDays(5)->toDateTimeString());

    $this->artisan('access:audit:purge', ['--older-than' => '30d'])
        ->expectsOutputToContain('0')
        ->assertSuccessful();

    expect(DB::table('access_audit_log')->count())->toBe(1);
});
