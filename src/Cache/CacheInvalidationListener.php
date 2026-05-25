<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Cache;

use ModularizeRbac\Core\Domain\Events\LanguageDefaultChanged;
use ModularizeRbac\Core\Domain\Events\ModuleCreated;
use ModularizeRbac\Core\Domain\Events\ModuleDeleted;
use ModularizeRbac\Core\Domain\Events\ModuleUpdated;

/**
 * Subscribes to the domain events that mutate cached aggregates and
 * bumps the corresponding {@see CacheVersion}. This is defence in
 * depth on top of the decorator's own bump-on-save: if a host writes
 * directly via the Eloquent model (Tinker, raw queries, console
 * commands) and dispatches the event manually, the cache still
 * invalidates.
 *
 * Wired by {@see \ModularizeRbac\Laravel\AccessServiceProvider::registerCacheLayer()}.
 */
final class CacheInvalidationListener
{
    public function __construct(
        private readonly CacheVersion $languageVersion,
        private readonly CacheVersion $moduleVersion,
    ) {
    }

    public function onLanguageDefaultChanged(LanguageDefaultChanged $event): void
    {
        $this->languageVersion->bump();
    }

    public function onModuleCreated(ModuleCreated $event): void
    {
        $this->moduleVersion->bump();
    }

    public function onModuleUpdated(ModuleUpdated $event): void
    {
        $this->moduleVersion->bump();
    }

    public function onModuleDeleted(ModuleDeleted $event): void
    {
        $this->moduleVersion->bump();
    }
}
