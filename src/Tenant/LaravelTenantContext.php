<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Tenant;

use Illuminate\Contracts\Container\Container;
use ModularizeRbac\Core\Application\Ports\TenantContext;
use ModularizeRbac\Core\Domain\Shared\Uuid;
use Throwable;

/**
 * Laravel adapter for the {@see TenantContext} port.
 *
 * The current tenant id is resolved from a container binding the
 * host's tenant-resolution middleware sets — by default, the
 * abstract key is `access.current_tenant_id` and the bound value is
 * a UUID string (or null). Hosts on a different convention can
 * override the abstract via `config('access.tenant_context_key')`.
 *
 * Single-tenant hosts simply never bind the key and `currentTenantId()`
 * returns null forever; use-cases that consult it then skip the
 * tenant filter.
 */
final class LaravelTenantContext implements TenantContext
{
    public function __construct(
        private readonly Container $container,
        private readonly string $bindKey = 'access.current_tenant_id',
    ) {
    }

    public function currentTenantId(): ?Uuid
    {
        if (! $this->container->bound($this->bindKey)) {
            return null;
        }

        $value = $this->container->make($this->bindKey);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new Uuid((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
