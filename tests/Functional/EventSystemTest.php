<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Attribute\Transient;
use Duyler\DI\Container;
use Duyler\DI\Event\ContainerEvent;
use Duyler\DI\Event\ContainerEvents;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EventSystemTest extends TestCase
{
    #[Test]
    public function fires_before_resolve_event(): void
    {
        $container = new Container();
        $called = false;

        $container->on(ContainerEvents::BEFORE_RESOLVE, function (ContainerEvent $event) use (&$called): void {
            $called = true;
            $this->assertEquals(ContainerEvents::BEFORE_RESOLVE, $event->name);
            $this->assertEquals(EventTestService::class, $event->serviceId);
        });

        $container->get(EventTestService::class);

        $this->assertTrue($called);
    }

    #[Test]
    public function fires_after_resolve_event(): void
    {
        $container = new Container();
        $called = false;
        $container->factory(EventTestService::class, fn() => new EventTestService());

        $container->on(ContainerEvents::AFTER_RESOLVE, function (ContainerEvent $event) use (&$called): void {
            $called = true;
            $this->assertEquals(ContainerEvents::AFTER_RESOLVE, $event->name);
            $this->assertEquals(EventTestService::class, $event->serviceId);
            $this->assertInstanceOf(EventTestService::class, $event->service);
            $this->assertIsFloat($event->time);
        });

        $container->get(EventTestService::class);

        $this->assertTrue($called);
    }

    #[Test]
    public function fires_before_finalize_event(): void
    {
        $container = new Container();
        $called = false;

        $container->on(ContainerEvents::BEFORE_FINALIZE, function (ContainerEvent $event) use (&$called): void {
            $called = true;
            $this->assertEquals(ContainerEvents::BEFORE_FINALIZE, $event->name);
        });

        $container->finalize();

        $this->assertTrue($called);
    }

    #[Test]
    public function fires_after_finalize_event(): void
    {
        $container = new Container();
        $called = false;

        $container->on(ContainerEvents::AFTER_FINALIZE, function (ContainerEvent $event) use (&$called): void {
            $called = true;
            $this->assertEquals(ContainerEvents::AFTER_FINALIZE, $event->name);
        });

        $container->finalize();

        $this->assertTrue($called);
    }

    #[Test]
    public function can_register_multiple_listeners_for_same_event(): void
    {
        $container = new Container();
        $calls = 0;
        $container->factory(EventTestMultiService::class, fn() => new EventTestMultiService());

        $container->on(ContainerEvents::BEFORE_RESOLVE, function (ContainerEvent $e) use (&$calls): void {
            if ($e->serviceId === EventTestMultiService::class) {
                $calls++;
            }
        });
        $container->on(ContainerEvents::BEFORE_RESOLVE, function (ContainerEvent $e) use (&$calls): void {
            if ($e->serviceId === EventTestMultiService::class) {
                $calls++;
            }
        });
        $container->on(ContainerEvents::BEFORE_RESOLVE, function (ContainerEvent $e) use (&$calls): void {
            if ($e->serviceId === EventTestMultiService::class) {
                $calls++;
            }
        });

        $container->get(EventTestMultiService::class);

        $this->assertEquals(3, $calls);
    }

    #[Test]
    public function event_provides_service_instance(): void
    {
        $container = new Container();
        $receivedService = null;
        $container->factory(EventTestService::class, fn() => new EventTestService());

        $container->on(ContainerEvents::AFTER_RESOLVE, function (ContainerEvent $event) use (&$receivedService): void {
            $receivedService = $event->service;
        });

        $service = $container->get(EventTestService::class);

        $this->assertSame($service, $receivedService);
    }

    #[Test]
    public function events_fire_for_each_transient_resolution(): void
    {
        $container = new Container();
        $resolutionCount = 0;

        $container->on(ContainerEvents::AFTER_RESOLVE, function (ContainerEvent $e) use (&$resolutionCount): void {
            if ($e->serviceId === EventTestTransientService::class) {
                $resolutionCount++;
            }
        });

        $container->factory(EventTestTransientService::class, fn() => new EventTestTransientService());

        $container->get(EventTestTransientService::class);
        $container->get(EventTestTransientService::class);

        $this->assertEquals(2, $resolutionCount);
    }

    #[Test]
    public function can_access_event_dispatcher(): void
    {
        $container = new Container();

        $dispatcher = $container->getEventDispatcher();

        $this->assertInstanceOf(\Duyler\DI\Event\EventDispatcher::class, $dispatcher);
    }

    #[Test]
    public function events_work_with_factory(): void
    {
        $container = new Container();
        $called = false;

        $container->factory(EventTestService::class, fn() => new EventTestService());

        $container->on(ContainerEvents::AFTER_RESOLVE, function (ContainerEvent $event) use (&$called): void {
            $called = true;
            $this->assertInstanceOf(EventTestService::class, $event->service);
        });

        $container->get(EventTestService::class);

        $this->assertTrue($called);
    }

    #[Test]
    public function events_provide_resolution_time(): void
    {
        $container = new Container();
        $time = null;
        $container->factory(EventTestService::class, fn() => new EventTestService());

        $container->on(ContainerEvents::AFTER_RESOLVE, function (ContainerEvent $event) use (&$time): void {
            $time = $event->time;
        });

        $container->get(EventTestService::class);

        $this->assertIsFloat($time);
        $this->assertGreaterThan(0, $time);
    }

    #[Test]
    public function can_use_events_for_logging(): void
    {
        $container = new Container();
        $log = [];
        $container->factory(EventTestLoggingService::class, fn() => new EventTestLoggingService());

        $container->on(ContainerEvents::BEFORE_RESOLVE, function (ContainerEvent $event) use (&$log): void {
            if ($event->serviceId === EventTestLoggingService::class) {
                $log[] = "Resolving: {$event->serviceId}";
            }
        });

        $container->on(ContainerEvents::AFTER_RESOLVE, function (ContainerEvent $event) use (&$log): void {
            if ($event->serviceId === EventTestLoggingService::class) {
                $log[] = "Resolved: {$event->serviceId} in {$event->time}s";
            }
        });

        $container->get(EventTestLoggingService::class);

        $this->assertCount(2, $log);
        $this->assertStringContainsString('Resolving:', $log[0]);
        $this->assertStringContainsString('Resolved:', $log[1]);
    }
}

class EventTestService {}

#[Transient]
class EventTestTransientService {}

class EventTestLoggingService {}

class EventTestMultiService {}
