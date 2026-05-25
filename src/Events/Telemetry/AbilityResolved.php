<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Events\Telemetry;

/**
 * Dispatched at the end of every `canAccess()` call on a host User
 * model with the {@see \ModularizeRbac\Laravel\Concerns\HasAccessPermissions}
 * trait. Hosts subscribe to this event to wire latency monitoring
 * (Sentry spans, Prometheus counters, structured logs).
 *
 * The `source` field documents which code path produced the answer:
 *
 *   - `direct`      — a binding on the user's role directly granted
 *                     the action on the requested module's slug.
 *   - `ancestor`    — a binding on one of the user's role's
 *                     ancestor roles (parent_role_id walk) granted it.
 *   - `inheritance` — module-hierarchy inheritance fired (the
 *                     resolver walked the parent_module chain).
 *   - `none`        — no binding granted the action; canAccess() = false.
 *   - `malformed`   — the ability string didn't parse as
 *                     `{slug}.{action}`; canAccess() = false.
 *
 * `durationMicros` is the wall-clock time spent inside canAccess()
 * (excluding the Laravel Gate::before dispatch around it).
 */
final readonly class AbilityResolved
{
    public function __construct(
        public string $ability,
        public bool $allowed,
        public string $source,
        public int $durationMicros,
    ) {
    }
}
