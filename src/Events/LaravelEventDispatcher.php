<?php

declare(strict_types=1);

namespace ModularizeRbac\Laravel\Events;

use Illuminate\Contracts\Events\Dispatcher;
use ModularizeRbac\Core\Application\Ports\DomainEventDispatcher;
use ModularizeRbac\Core\Domain\Shared\DomainEvent;
use ModularizeRbac\Laravel\Audit\AuditingListener;

/**
 * {@see DomainEventDispatcher} adapter that forwards domain events
 * to Laravel's event dispatcher. Subscribers in the host's
 * `EventServiceProvider` listen by concrete event class.
 *
 * If an {@see AuditingListener} is injected (the ServiceProvider
 * wires one when `config('access.audit.enabled')` is truthy), it
 * runs inline BEFORE forwarding — auditing is part of the same
 * transactional flow as the use-case that dispatched the event.
 */
final class LaravelEventDispatcher implements DomainEventDispatcher
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly ?AuditingListener $audit = null,
    ) {
    }

    public function dispatch(DomainEvent $event): void
    {
        $this->audit?->onDomainEvent($event);
        $this->dispatcher->dispatch($event);
    }

    public function dispatchAll(iterable $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}
