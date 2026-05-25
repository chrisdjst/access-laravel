<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $user, string $ability): bool => true);
    // Each test starts with a fresh limiter store so attempt counters
    // don't leak across the suite.
    RateLimiter::clear('access-bulk:127.0.0.1');
    RateLimiter::clear('access-bulk:anon');
});

it('returns 429 after exceeding the configured bulk limit', function (): void {
    config()->set('access.rate_limit.bulk', '3,1');
    // Force a fresh limiter instance picking up the new config
    RateLimiter::clear('access-bulk:127.0.0.1');

    // Re-register the limiter so it reads the new config value
    \ModularizeRbac\Laravel\AccessServiceProvider::class;
    app(\ModularizeRbac\Laravel\AccessServiceProvider::class, [
        'app' => app(),
    ])->callBootingCallbacks ?? null;

    // First 3 attempts allowed
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/admin/modules/bulk', [
            'modules' => [['slug' => "m{$i}", 'name' => "M{$i}"]],
        ])->assertCreated();
    }

    // 4th attempt blocked
    $this->postJson('/api/admin/modules/bulk', [
        'modules' => [['slug' => 'blocked', 'name' => 'Blocked']],
    ])->assertStatus(429);
});

it('the throttle covers POST /roles/{id}/clone too', function (): void {
    config()->set('access.rate_limit.bulk', '2,1');
    RateLimiter::clear('access-bulk:127.0.0.1');

    $source = $this->postJson('/api/admin/roles', ['name' => 'src', 'guard_name' => 'admin'])->json('data');

    $this->postJson("/api/admin/roles/{$source['id']}/clone", ['name' => 'c1'])->assertCreated();
    $this->postJson("/api/admin/roles/{$source['id']}/clone", ['name' => 'c2'])->assertCreated();
    $this->postJson("/api/admin/roles/{$source['id']}/clone", ['name' => 'c3'])->assertStatus(429);
});

it('non-bulk endpoints are NOT throttled by the same limiter', function (): void {
    config()->set('access.rate_limit.bulk', '2,1');
    RateLimiter::clear('access-bulk:127.0.0.1');

    // Hit the limit on a bulk endpoint
    $this->postJson('/api/admin/modules/bulk', ['modules' => [['slug' => 'a', 'name' => 'A']]])->assertCreated();
    $this->postJson('/api/admin/modules/bulk', ['modules' => [['slug' => 'b', 'name' => 'B']]])->assertCreated();
    $this->postJson('/api/admin/modules/bulk', ['modules' => [['slug' => 'c', 'name' => 'C']]])->assertStatus(429);

    // Regular CRUD endpoint still works
    $this->postJson('/api/admin/modules', ['slug' => 'regular', 'name' => 'R'])->assertCreated();
});
