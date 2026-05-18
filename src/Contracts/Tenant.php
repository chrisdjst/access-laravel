<?php

declare(strict_types=1);

namespace Casamento\Rbac\Contracts;

/**
 * Marker interface for tenant-owning models (Organization, Account,
 * Workspace, etc.) that scope role ownership. Host apps that need
 * multi-tenancy should implement this on their tenant model and point
 * `config('rbac.tenant_model')` at it.
 *
 * The package does not require any methods today — implementing this
 * interface is enough to declare intent. Future versions may add
 * methods (e.g. `tenantKey()`) without breaking single-tenant setups.
 */
interface Tenant
{
}
