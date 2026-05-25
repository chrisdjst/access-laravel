<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Authorization;

use ModularizeRbac\Core\Application\Ports\ModuleRepository;
use ModularizeRbac\Core\Domain\Module\Module;
use ModularizeRbac\Core\Domain\Module\ModuleSlug;

/**
 * Per-request lookup index over the module hierarchy. Used by
 * {@see \ModularizeRbac\Laravel\Concerns\HasAccessPermissions} to
 * resolve `$user->can(...)` with `access.inheritance.enabled = true`
 * without paying the full module-table load on every check.
 *
 * Bound as a scoped singleton in {@see \ModularizeRbac\Laravel\AccessServiceProvider}
 * so a single instance is reused for every `canAccess()` call within
 * the same request, but the next request starts fresh (catching any
 * module that was added between requests).
 *
 * Underlying read goes through `ModuleRepository::allActiveTree()`,
 * which the v2.3.0 cache layer fronts — so the first call inside a
 * request hits the cache (microseconds), subsequent calls hit this
 * memo, and only a cold cache + a fresh request pays the DB cost.
 */
final class ModuleHierarchyIndex
{
    /** @var array<string, string>|null  slug-by-id */
    private ?array $slugById = null;

    /** @var array<string, ModuleSlug>|null  parent-slug-by-child-slug */
    private ?array $parentBySlug = null;

    public function __construct(private readonly ModuleRepository $modules)
    {
    }

    public function parentOf(ModuleSlug $slug): ?ModuleSlug
    {
        $this->ensureLoaded();

        return $this->parentBySlug[$slug->value] ?? null;
    }

    /**
     * Force the next call to rebuild the maps. Useful in tests that
     * mutate modules between checks within the same request scope.
     * Production code should NOT need to call this — the index is
     * scoped per request.
     */
    public function flush(): void
    {
        $this->slugById = null;
        $this->parentBySlug = null;
    }

    private function ensureLoaded(): void
    {
        if ($this->slugById !== null && $this->parentBySlug !== null) {
            return;
        }

        $slugById = [];
        $parentBySlug = [];

        foreach ($this->modules->allActiveTree() as $module) {
            assert($module instanceof Module);
            $slugById[$module->id->value] = $module->slug()->value;
        }
        foreach ($this->modules->allActiveTree() as $module) {
            assert($module instanceof Module);
            $parentId = $module->rootModuleId();
            if ($parentId === null) {
                continue;
            }
            $parentSlug = $slugById[$parentId->value] ?? null;
            if ($parentSlug === null) {
                continue;
            }
            $parentBySlug[$module->slug()->value] = new ModuleSlug($parentSlug);
        }

        $this->slugById = $slugById;
        $this->parentBySlug = $parentBySlug;
    }
}
