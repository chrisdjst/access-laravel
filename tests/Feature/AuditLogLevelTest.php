<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use ModularizeRbac\Core\Application\Ports\AuditRepository;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Laravel\Audit\AuditingListener;

beforeEach(function (): void {
    \Illuminate\Support\Facades\Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): bool => true);

    // Make the audit repository explode so the listener trips the
    // catch block. Done via a tiny test double bound to the port.
    app()->bind(AuditRepository::class, function () {
        return new class implements AuditRepository {
            public function save(\ModularizeRbac\Core\Domain\Audit\AuditEntry $entry): void
            {
                throw new \RuntimeException('boom');
            }
            public function search(\ModularizeRbac\Core\Application\Audit\AuditQuery $q): array { return []; }
            public function count(\ModularizeRbac\Core\Application\Audit\AuditQuery $q): int { return 0; }
            public function deleteOlderThan(\DateTimeImmutable $cutoff): int { return 0; }
        };
    });

    // Re-bind the AuditingListener so it picks up the new repository
    app()->forgetInstance(AuditingListener::class);
});

it('logs audit failures at warning level by default', function (): void {
    config()->set('access.audit.log_failures', 'warning');

    Log::shouldReceive('log')
        ->once()
        ->with('warning', 'access: failed to record audit entry for domain event', \Mockery::any());

    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));
});

it('honors a custom log level when set in config', function (): void {
    config()->set('access.audit.log_failures', 'error');

    Log::shouldReceive('log')
        ->once()
        ->with('error', \Mockery::any(), \Mockery::any());

    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));
});

it('swallows the failure silently when config is set to false', function (): void {
    config()->set('access.audit.log_failures', false);

    Log::shouldReceive('log')->never();

    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));
});

it('falls back to warning when an unexpected value is configured', function (): void {
    config()->set('access.audit.log_failures', 12345); // not a string

    Log::shouldReceive('log')
        ->once()
        ->with('warning', \Mockery::any(), \Mockery::any());

    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));
});
