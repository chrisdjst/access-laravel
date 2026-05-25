<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModule;
use ModularizeRbac\Core\Application\Module\CreateModule\CreateModuleInput;
use ModularizeRbac\Core\Domain\Shared\DomainEvent;
use ModularizeRbac\Laravel\Audit\AuditingListener;

/**
 * Test-only event used to feed a synthetic PII-rich payload through
 * the audit listener. Named class (not anonymous) so deriveEventName()
 * snake-cases the short name cleanly.
 */
final class PiiTestEventOccurred implements DomainEvent
{
    /** @param array<string, mixed> $details */
    public function __construct(public array $details)
    {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}

beforeEach(function (): void {
    Gate::before(fn (?\Illuminate\Contracts\Auth\Authenticatable $u, string $ability): bool => true);
});

function pumpPiiEvent(array $details): array
{
    DB::table('access_audit_log')->delete();
    app(AuditingListener::class)->onDomainEvent(new PiiTestEventOccurred($details));
    $row = DB::table('access_audit_log')->orderByDesc('occurred_at')->first();
    expect($row)->not->toBeNull('Listener failed to persist — check deriveEventName / serialization');

    return [
        'event_type' => $row->event_name,
        'payload' => json_decode((string) $row->payload, true),
    ];
}

it('redacts top-level password / token / email / cpf keys', function (): void {
    $result = pumpPiiEvent([
        'password' => 'plaintext',
        'api_token' => 'abc',
        'email' => 'a@b.com',
        'cpf' => '111.222.333-44',
        'safe_field' => 'visible',
    ]);

    expect($result['payload']['details']['password'])->toBe('[REDACTED]')
        ->and($result['payload']['details']['api_token'])->toBe('[REDACTED]')
        ->and($result['payload']['details']['email'])->toBe('[REDACTED]')
        ->and($result['payload']['details']['cpf'])->toBe('[REDACTED]')
        ->and($result['payload']['details']['safe_field'])->toBe('visible');
});

it('matches keys case-insensitively', function (): void {
    $result = pumpPiiEvent(['EMAIL' => 'a@b.com', 'Password' => 'pw']);

    expect($result['payload']['details']['EMAIL'])->toBe('[REDACTED]')
        ->and($result['payload']['details']['Password'])->toBe('[REDACTED]');
});

it('matches keys via substring (user_email, customer_password, ...)', function (): void {
    $result = pumpPiiEvent([
        'user_email' => 'a@b.com',
        'customer_password_hash' => 'x',
        'session_access_token' => 'y',
    ]);

    expect($result['payload']['details']['user_email'])->toBe('[REDACTED]')
        ->and($result['payload']['details']['customer_password_hash'])->toBe('[REDACTED]')
        ->and($result['payload']['details']['session_access_token'])->toBe('[REDACTED]');
});

it('walks into nested arrays', function (): void {
    $result = pumpPiiEvent([
        'metadata' => [
            'email' => 'nested@b.com',
            'safe' => 'visible',
            'deep' => ['password' => 'pw'],
        ],
    ]);

    expect($result['payload']['details']['metadata']['email'])->toBe('[REDACTED]')
        ->and($result['payload']['details']['metadata']['safe'])->toBe('visible')
        ->and($result['payload']['details']['metadata']['deep']['password'])->toBe('[REDACTED]');
});

it('honors host overrides of access.audit.redact_fields', function (): void {
    config()->set('access.audit.redact_fields', ['cnpj']);

    $result = pumpPiiEvent([
        'cnpj' => '12.345.678/0001-99',
        'password' => 'visible_because_overridden_list',
    ]);

    expect($result['payload']['details']['cnpj'])->toBe('[REDACTED]')
        ->and($result['payload']['details']['password'])->toBe('visible_because_overridden_list');
});

it('disables redaction entirely when list is empty', function (): void {
    config()->set('access.audit.redact_fields', []);

    $result = pumpPiiEvent(['password' => 'visible']);

    expect($result['payload']['details']['password'])->toBe('visible');
});

it('does not affect non-sensitive keys in real ModuleCreated events', function (): void {
    DB::table('access_audit_log')->delete();
    app(CreateModule::class)->execute(new CreateModuleInput('events', 'Events', null, null, null));

    $row = DB::table('access_audit_log')->orderByDesc('occurred_at')->first();
    $payload = json_decode((string) $row->payload, true);

    expect($row->event_name)->toBe('module.created')
        ->and($payload['slug'])->toBe('events');
});
