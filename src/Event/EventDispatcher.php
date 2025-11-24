<?php

declare(strict_types=1);

namespace Duyler\DI\Event;

final class EventDispatcher
{
    /** @var array<string, array<callable>> */
    private array $listeners = [];

    public function addListener(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $this->listeners[$eventName][] = $listener;
    }

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
