<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Cache;

use Illuminate\Contracts\Cache\Repository as CacheContract;
use ModularizeRbac\Core\Application\Ports\LanguageRepository;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use ModularizeRbac\Core\Domain\Translation\Language;
use ModularizeRbac\Core\Domain\Translation\LanguageCode;

/**
 * Decorator that fronts a {@see LanguageRepository} with a
 * {@see CacheContract}-backed read cache. Read methods consult the
 * cache first; writes pass through to the inner repository and bump
 * the version key so subsequent reads see fresh data.
 *
 * Negative results (`null` returns) are also cached — clearing them
 * on `save()` is the version bump's job.
 *
 * Implementations are framework-agnostic: this class lives in the
 * bridge because the cache contract is host-supplied, but the inner
 * repo it decorates is the core-defined port — no coupling leaks.
 */
final class CachedLanguageRepository implements LanguageRepository
{
    public function __construct(
        private readonly LanguageRepository $inner,
        private readonly CacheContract $cache,
        private readonly CacheVersion $version,
        private readonly int $ttl,
    ) {
    }

    public function find(Uuid $id): ?Language
    {
        return $this->remember("id:{$id->value}", fn () => $this->inner->find($id));
    }

    public function findByCode(LanguageCode $code): ?Language
    {
        return $this->remember("code:{$code->value}", fn () => $this->inner->findByCode($code));
    }

    public function default(): ?Language
    {
        return $this->remember('default', fn () => $this->inner->default());
    }

    public function all(): array
    {
        $cached = $this->cache->get($this->version->key('all'));
        if (is_array($cached)) {
            return $cached;
        }
        $fresh = $this->inner->all();
        $this->cache->put($this->version->key('all'), $fresh, $this->ttl);

        return $fresh;
    }

    public function save(Language $language): void
    {
        $this->inner->save($language);
        $this->version->bump();
    }

    public function delete(Language $language): void
    {
        $this->inner->delete($language);
        $this->version->bump();
    }

    /**
     * @param  callable(): ?Language  $loader
     */
    private function remember(string $suffix, callable $loader): ?Language
    {
        $key = $this->version->key($suffix);
        // Wrap the value so a cached `null` survives Cache::has()
        // semantics (which returns false for null entries on some
        // stores). The wrapper is a 1-element array: [0 => value].
        $wrapped = $this->cache->get($key);
        if (is_array($wrapped) && array_key_exists(0, $wrapped)) {
            $value = $wrapped[0];

            return $value instanceof Language ? $value : null;
        }
        $fresh = $loader();
        $this->cache->put($key, [$fresh], $this->ttl);

        return $fresh;
    }
}
