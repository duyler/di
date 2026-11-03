<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\Event\ContainerEvent;
use Duyler\DI\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventDispatcherTest extends TestCase
{
    #[Test]
    public function dispatch_without_listeners_is_noop(): void
    {
        $dispatcher = new EventDispatcher();

        $dispatcher->dispatch(new ContainerEvent('event.name'));

        $this->assertFalse($dispatcher->hasListeners('event.name'));
    }

    #[Test]
    public function dispatch_calls_registered_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $received = null;
        $dispatcher->addListener('event.name', static function (ContainerEvent $event) use (&$received): void {
            $received = $event;
        });

        $event = new ContainerEvent('event.name', 'service.id');
        $dispatcher->dispatch($event);

        $this->assertSame($event, $received);
    }

    #[Test]
    public function has_listeners_reflects_registration_state(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('event.name', static fn() => null);

        $this->assertTrue($dispatcher->hasListeners('event.name'));
        $this->assertFalse($dispatcher->hasListeners('other.event'));
    }

    #[Test]
    public function get_listeners_returns_registered_shape(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = static fn() => null;
        $dispatcher->addListener('event.name', $listener);

        $this->assertSame(['event.name' => [$listener]], $dispatcher->getListeners());
    }

    #[Test]
    public function remove_listeners_deletes_event_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $calls = 0;
        $dispatcher->addListener('event.name', static function () use (&$calls): void {
            $calls++;
        });

        $dispatcher->removeListeners('event.name');
        $dispatcher->dispatch(new ContainerEvent('event.name'));

        $this->assertFalse($dispatcher->hasListeners('event.name'));
        $this->assertSame(0, $calls);
    }

    #[Test]
    public function reset_clears_all_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('event.a', static fn() => null);
        $dispatcher->addListener('event.b', static fn() => null);

        $dispatcher->reset();

        $this->assertFalse($dispatcher->hasListeners('event.a'));
        $this->assertFalse($dispatcher->hasListeners('event.b'));
        $this->assertSame([], $dispatcher->getListeners());
    }

    #[Test]
    public function listener_exception_interrupts_chain_and_propagates(): void
    {
        $dispatcher = new EventDispatcher();
        $secondCalled = false;
        $dispatcher->addListener('event.name', static function (): void {
            throw new RuntimeException('listener failed');
        });
        $dispatcher->addListener('event.name', static function () use (&$secondCalled): void {
            $secondCalled = true;
        });

        try {
            $dispatcher->dispatch(new ContainerEvent('event.name'));
            $this->fail('RuntimeException expected');
        } catch (RuntimeException $exception) {
            $this->assertSame('listener failed', $exception->getMessage());
        }

        $this->assertFalse($secondCalled);
    }
}
