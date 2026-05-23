<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Persistence;

use Illuminate\Support\Str;
use ModularizeRbac\Core\Domain\Shared\IdGenerator;
use ModularizeRbac\Core\Domain\Shared\Uuid;

/**
 * {@see IdGenerator} adapter producing UUIDv4 values via Laravel's
 * `Str::uuid()` helper.
 */
final class UuidV4IdGenerator implements IdGenerator
{
    public function nextUuid(): Uuid
    {
        return new Uuid((string) Str::uuid());
    }
}
