<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Cache;

use Illuminate\Contracts\Cache\Repository as CacheContract;
use ModularizeRbac\Core\Application\Module\ModuleFilter;
use ModularizeRbac\Core\Application\Ports\ModuleRepository;
use ModularizeRbac\Core\Application\Shared\PaginatedResult;
use ModularizeRbac\Core\Application\Shared\Pagination;
use ModularizeRbac\Core\Domain\Module\Module;
use ModularizeRbac\Core\Domain\Module\ModuleSlug;
use ModularizeRbac\Core\Domain\Shared\Uuid;

/**
 * Decorator that fronts a {@see ModuleRepository} with a
 * {@see CacheContract}-backed read cache. See {@see CachedLanguageRepository}
 * for the version-key invalidation rationale.
 */
final class CachedModuleRepository implements ModuleRepository
{
    public function __construct(
        private readonly ModuleRepository $inner,
        private readonly CacheContract $cache,
        private readonly CacheVersion $version,
        private readonly int $ttl,
    ) {
    }

    public function find(Uuid $id): ?Module
    {
        return $this->remember("id:{$id->value}", fn () => $this->inner->find($id));
    }

    public function findBySlug(ModuleSlug $slug): ?Module
    {
        return $this->remember("slug:{$slug->value}", fn () => $this->inner->findBySlug($slug));
    }

    public function allActiveTree(): array
    {
        $cached = $this->cache->get($this->version->key('tree'));
        if (is_array($cached)) {
            return $cached;
        }
        $fresh = $this->inner->allActiveTree();
        $this->cache->put($this->version->key('tree'), $fresh, $this->ttl);

        return $fresh;
    }

    public function save(Module $module): void
    {
        $this->inner->save($module);
        $this->version->bump();
    }

    public function searchPaginated(ModuleFilter $filter, Pagination $pagination): PaginatedResult
    {
        // Paginated/filtered search results would be expensive to cache
        // correctly across the filter combinatorial space — defer to
        // the inner repository. Reads here go straight to the DB but
        // single-row + tree reads still benefit from the cache layer.
        return $this->inner->searchPaginated($filter, $pagination);
    }

    /**
     * @param  callable(): ?Module  $loader
     */
    private function remember(string $suffix, callable $loader): ?Module
    {
        $key = $this->version->key($suffix);
        // See CachedLanguageRepository::remember() for the wrapper rationale.
        $wrapped = $this->cache->get($key);
        if (is_array($wrapped) && array_key_exists(0, $wrapped)) {
            $value = $wrapped[0];

            return $value instanceof Module ? $value : null;
        }
        $fresh = $loader();
        $this->cache->put($key, [$fresh], $this->ttl);

        return $fresh;
    }
}
