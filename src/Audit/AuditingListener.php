<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Audit;

use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use ModularizeRbac\Core\Application\Ports\AuditRepository;
use ModularizeRbac\Core\Application\Ports\Authorizer;
use ModularizeRbac\Core\Application\Ports\TenantContext;
use ModularizeRbac\Core\Domain\Audit\AuditEntry;
use ModularizeRbac\Core\Domain\Audit\AuditEventName;
use ModularizeRbac\Core\Domain\Shared\Clock;
use ModularizeRbac\Core\Domain\Shared\DomainEvent;
use ModularizeRbac\Core\Domain\Shared\IdGenerator;
use ReflectionClass;
use Stringable;
use Throwable;

/**
 * Converts a domain event into an {@see AuditEntry} row and persists
 * it through the {@see AuditRepository} port. The
 * {@see \ModularizeRbac\Laravel\Events\LaravelEventDispatcher}
 * invokes this listener inline (before forwarding to Laravel's event
 * dispatcher) whenever auditing is enabled in config.
 *
 * Event name derivation: takes the short class name (e.g.
 * `ModuleCreated`) and converts to snake_case dotted form. The
 * canonical aggregate prefix lives in the event's namespace, so
 * `Modularize\Core\Domain\Events\ModuleCreated` becomes
 * `module.created`. Custom events from hosts/extensions that don't
 * follow this convention fall back to a snake_case rendering of
 * the class name as a single segment, e.g. `report_generated`.
 *
 * Payload extraction: reads the event's public readonly properties
 * via reflection, skipping `occurredAt` (already captured as a
 * dedicated column). Values are coerced to strings via __toString
 * when available; arrays / scalars are stored as-is.
 */
final class AuditingListener
{
    public function __construct(
        private readonly AuditRepository $repository,
        private readonly Authorizer $authorizer,
        private readonly TenantContext $tenantContext,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    public function onDomainEvent(DomainEvent $event): void
    {
        try {
            $name = $this->deriveEventName($event);
            $payload = $this->extractPayload($event);
            $entry = AuditEntry::record(
                id: $this->ids->nextUuid(),
                event: $name,
                actorId: $this->authorizer->actorId(),
                tenantId: $this->tenantContext->currentTenantId(),
                payload: $payload,
                clock: $this->clock,
            );
            $this->repository->save($entry);
        } catch (Throwable $e) {
            // The audit log is a side observability concern — never
            // let a serialization quirk or transient DB failure crash
            // the main domain flow. The level is configurable via
            // `access.audit.log_failures` so compliance-heavy hosts
            // can route these as `error` or `critical`; setting the
            // config to `false` silences them entirely.
            $level = config('access.audit.log_failures', 'warning');
            if ($level === false || $level === null) {
                return;
            }
            if (! is_string($level) || $level === '') {
                $level = 'warning';
            }
            Log::log($level, 'access: failed to record audit entry for domain event', [
                'event_class' => $event::class,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function deriveEventName(DomainEvent $event): AuditEventName
    {
        $short = (new ReflectionClass($event))->getShortName();
        $snake = strtolower((string) preg_replace('/(?<!^)([A-Z])/', '_$1', $short));

        // Try to split as aggregate + action (e.g. `module_created`
        // → `module.created`). Fall back to a single-segment name
        // if no underscore is present.
        $pos = strpos($snake, '_');
        if ($pos === false || $pos === 0 || $pos === strlen($snake) - 1) {
            return new AuditEventName($snake.'.recorded');
        }
        $head = substr($snake, 0, $pos);
        $tail = substr($snake, $pos + 1);

        return new AuditEventName($head.'.'.$tail);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(DomainEvent $event): array
    {
        $rc = new ReflectionClass($event);
        $payload = [];
        foreach ($rc->getProperties() as $property) {
            if (! $property->isPublic()) {
                continue;
            }
            $name = $property->getName();
            if ($name === 'occurredAt') {
                continue;
            }
            $value = $property->getValue($event);
            $payload[$name] = $this->serializeValue($value);
        }

        return $this->redactSensitive($payload);
    }

    /**
     * Walk an associative payload tree replacing values whose key
     * matches any pattern from `access.audit.redact_fields` with the
     * literal string `[REDACTED]`. Matching is case-insensitive
     * substring — `'email'` covers both top-level `email` keys AND
     * nested `user_email` / `customer_email` keys.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactSensitive(array $payload): array
    {
        $patterns = (array) config('access.audit.redact_fields', []);
        if ($patterns === []) {
            return $payload;
        }
        $normalized = array_map(static fn ($p) => strtolower((string) $p), $patterns);

        $walker = static function (mixed $value) use (&$walker, $normalized): mixed {
            if (! is_array($value)) {
                return $value;
            }
            $out = [];
            foreach ($value as $k => $v) {
                $lower = is_string($k) ? strtolower($k) : '';
                $matches = false;
                foreach ($normalized as $needle) {
                    if ($needle !== '' && str_contains($lower, $needle)) {
                        $matches = true;
                        break;
                    }
                }
                $out[$k] = $matches ? '[REDACTED]' : $walker($v);
            }

            return $out;
        };

        /** @var array<string, mixed> $result */
        $result = $walker($payload);

        return $result;
    }

    /**
     * Convert a single field value to something JSON-safe. Value
     * objects render via __toString when implementing Stringable;
     * arrays are walked recursively; everything else passes through.
     */
    private function serializeValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->serializeValue($v);
            }

            return $out;
        }

        // Anything else (objects without __toString) gets a class hint
        // — the caller's payload is meant to be small and serializable.
        return ['_class' => $value::class];
    }
}
