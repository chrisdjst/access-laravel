<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Persistence;

use DateTimeImmutable;
use Modularize\Access\Domain\Shared\Clock;

/**
 * Default {@see Clock} adapter using PHP's system time. The bridge
 * binds this to the {@see Clock} interface in the ServiceProvider so
 * domain code never reaches for `now()` directly.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
