<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Persistence;

use Illuminate\Database\ConnectionResolverInterface;
use ModularizeRbac\Core\Application\Ports\UnitOfWork;

/**
 * {@see UnitOfWork} adapter wrapping Laravel's DB transaction
 * primitive. Uses the connection resolver rather than the
 * `DB` facade so the bridge stays testable and supports apps that
 * pin a specific connection name (e.g. multi-tenant setups).
 */
final class LaravelUnitOfWork implements UnitOfWork
{
    public function __construct(
        private readonly ConnectionResolverInterface $connections,
        private readonly ?string $connectionName = null,
    ) {
    }

    public function transactional(callable $work): mixed
    {
        return $this->connections->connection($this->connectionName)->transaction($work);
    }
}
