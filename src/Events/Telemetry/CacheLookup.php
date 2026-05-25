<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Events\Telemetry;

/**
 * Dispatched on every read through `CachedLanguageRepository` or
 * `CachedModuleRepository`. Hosts subscribe to this event to track
 * cache hit ratios in production (Prometheus counter or Datadog
 * gauge driven by `hit/miss`).
 *
 * `namespace` is the cache-key prefix ('access:lang' or
 * 'access:module'), `key` is the suffix that was looked up
 * ('all', 'tree', `id:<uuid>`, `slug:<slug>`, etc.). `version`
 * matches the version-key bump counter — useful for spotting cache
 * thrash caused by a noisy invalidation event.
 */
final readonly class CacheLookup
{
    public function __construct(
        public string $namespace,
        public string $key,
        public bool $hit,
        public int $version,
    ) {
    }
}
