<?php

declare(strict_types=1);

namespace Modularize\Access\Laravel\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Modularize\Access\Application\Ports\DomainEventDispatcher;
use Modularize\Access\Domain\Shared\DomainEvent;

/**
 * {@see DomainEventDispatcher} adapter that forwards domain events
 * to Laravel's event dispatcher. Subscribers (e.g. the optional
 * Spatie sync listener landing in PR 5) bind to the concrete event
 * class via the host's `EventServiceProvider`.
 */
final class LaravelEventDispatcher implements DomainEventDispatcher
{
    public function __construct(private readonly Dispatcher $dispatcher)
    {
    }

    public function dispatch(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }

    public function dispatchAll(iterable $events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
