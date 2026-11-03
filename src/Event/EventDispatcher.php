<?php

declare(strict_types=1);

namespace Duyler\DI\Event;

final class EventDispatcher
{
    /** @var array<string, array<callable>> */
    private array $listeners = [];

    /**
     * Appends without deduplication: registering the same listener twice
     * invokes it twice per event. removeListeners clears all listeners of
     * an event; EventDispatcher::reset clears every event.
     */
    public function addListener(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $this->listeners[$eventName][] = $listener;
    }

    /**
     * A listener exception interrupts the chain and propagates out of dispatch
     * as is; listeners after the failing one are not invoked. When dispatched
     * from Container::get(), the user receives ServiceNotCreatedException with
     * the listener exception as previous. No try/finally is introduced on
     * purpose: the library has no logger to report to.
     */
    public function dispatch(ContainerEvent $event): void
    {
        if (!isset($this->listeners[$event->name])) {
            return;
        }

        foreach ($this->listeners[$event->name] as $listener) {
            $listener($event);
        }
    }

    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]) && !empty($this->listeners[$eventName]);
    }

    /**
     * @return array<string, array<callable>>
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    public function removeListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
    }

    public function reset(): void
    {
        $this->listeners = [];
    }
}
