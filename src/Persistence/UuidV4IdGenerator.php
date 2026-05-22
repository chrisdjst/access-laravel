<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Persistence;

use Illuminate\Support\Str;
use Modularize\Access\Domain\Shared\IdGenerator;
use Modularize\Access\Domain\Shared\Uuid;

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
