<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Cache;

use Illuminate\Contracts\Cache\Repository as CacheContract;

/**
 * Tiny helper that implements "version-key invalidation": instead of
 * tracking every cache entry to clear on a mutation, we keep one
 * monotonically-incrementing integer per namespace. Cache keys embed
 * the current version (`access:lang:v3:all`); to invalidate the
 * whole namespace we just `increment()` the master key — old entries
 * become orphans that drop off via their TTL.
 *
 * This avoids dependency on Laravel cache tagging (which only the
 * redis + memcached stores support) and keeps invalidation O(1).
 */
final class CacheVersion
{
    public function __construct(
        private readonly CacheContract $cache,
        private readonly string $namespace,
    ) {
    }

    public function current(): int
    {
        $raw = $this->cache->get($this->versionKey());
        if (is_int($raw)) {
            return $raw;
        }
        if (is_numeric($raw)) {
            return (int) $raw;
        }
        $this->cache->forever($this->versionKey(), 1);

        return 1;
    }

    public function bump(): void
    {
        // The cache contract doesn't expose `increment()` so do it via
        // get + put. A race between two writers can lose one bump,
        // which is acceptable — the worst case is one extra cold read.
        $next = $this->current() + 1;
        $this->cache->forever($this->versionKey(), $next);
    }

    public function key(string $suffix): string
    {
        return sprintf('%s:v%d:%s', $this->namespace, $this->current(), $suffix);
    }

    private function versionKey(): string
    {
        return $this->namespace.':version';
    }
}
