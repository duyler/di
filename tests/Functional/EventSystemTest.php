<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Attribute\Finalize;
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
        $this->assertGreaterThanOrEqual(0.0, $time);
    }

    #[Test]
    public function after_resolve_dispatched_once_per_cache_hit_and_twice_on_fresh_create(): void
    {
        $container = new Container();

        $events = [];
        $container->on(ContainerEvents::AFTER_RESOLVE, static function ($event) use (&$events): void {
            $events[] = $event;
        });

        $container->decorate(
            EventCacheHitService::class,
            static fn(object $service): object => new EventDecoratedService($service),
        );

        $fresh = $container->get(EventCacheHitService::class);

        $this->assertCount(2, $events);
        $this->assertNotSame($fresh, $events[0]->service);
        $this->assertInstanceOf(EventCacheHitService::class, $events[0]->service);
        $this->assertSame($fresh, $events[1]->service);
        $this->assertInstanceOf(EventDecoratedService::class, $events[1]->service);

        $cached = $container->get(EventCacheHitService::class);

        $this->assertCount(3, $events);
        $this->assertSame($fresh, $cached);
        $this->assertSame($fresh, $events[2]->service);
    }

    #[Test]
    public function finalize_called_twice_dispatches_events_once(): void
    {
        $container = new Container();
        $beforeCalls = 0;
        $afterCalls = 0;
        $container->on(ContainerEvents::BEFORE_FINALIZE, static function () use (&$beforeCalls): void {
            $beforeCalls++;
        });
        $container->on(ContainerEvents::AFTER_FINALIZE, static function () use (&$afterCalls): void {
            $afterCalls++;
        });

        $service = $container->get(EventFinalizeService::class);
        $container->finalize();

        $this->assertSame(1, $service->finalizeCalls);
        $this->assertSame(1, $beforeCalls);
        $this->assertSame(1, $afterCalls);

        $container->finalize();

        $this->assertSame(1, $service->finalizeCalls);
        $this->assertSame(1, $beforeCalls);
        $this->assertSame(1, $afterCalls);
    }

    #[Test]
    public function reset_allows_finalize_to_run_again(): void
    {
        $container = new Container();
        $firstCalls = 0;
        $container->on(ContainerEvents::BEFORE_FINALIZE, static function () use (&$firstCalls): void {
            $firstCalls++;
        });

        $service = $container->get(EventFinalizeService::class);
        $container->finalize();

        $this->assertSame(1, $firstCalls);
        $this->assertSame(1, $service->finalizeCalls);

        $container->reset();

        $afterResetCalls = 0;
        $container->on(ContainerEvents::BEFORE_FINALIZE, static function () use (&$afterResetCalls): void {
            $afterResetCalls++;
        });

        $recreated = $container->get(EventFinalizeService::class);

        $container->finalize();
        $container->finalize();

        $this->assertSame(1, $afterResetCalls);
        $this->assertSame(1, $recreated->finalizeCalls);
    }

    #[Test]
    public function make_dispatches_before_and_after_make_events(): void
    {
        $container = new Container();
        $beforeMake = 0;
        $afterMake = 0;
        $afterMakeTime = 'unset';
        $afterMakeService = null;
        $container->on(ContainerEvents::BEFORE_MAKE, static function () use (&$beforeMake): void {
            $beforeMake++;
        });
        $container->on(ContainerEvents::AFTER_MAKE, static function (ContainerEvent $event) use (&$afterMake, &$afterMakeTime, &$afterMakeService): void {
            $afterMake++;
            $afterMakeTime = $event->time;
            $afterMakeService = $event->service;
        });

        $service = $container->get(EventTestService::class);

        $this->assertSame(1, $beforeMake);
        $this->assertSame(1, $afterMake);
        $this->assertSame($service, $afterMakeService);
        $this->assertIsFloat($afterMakeTime);
        $this->assertGreaterThanOrEqual(0.0, $afterMakeTime);
    }

    #[Test]
    public function transient_resolution_does_not_dispatch_make_events(): void
    {
        $container = new Container();
        $makeCalls = 0;
        $container->on(ContainerEvents::BEFORE_MAKE, static function () use (&$makeCalls): void {
            $makeCalls++;
        });
        $container->on(ContainerEvents::AFTER_MAKE, static function () use (&$makeCalls): void {
            $makeCalls++;
        });

        $container->get(EventTestTransientService::class);
        $container->get(EventTestTransientService::class);

        $this->assertSame(0, $makeCalls);
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

class EventCacheHitService {}

class EventDecoratedService
{
    public function __construct(public readonly object $inner) {}
}

#[Finalize]
class EventFinalizeService
{
    public int $finalizeCalls = 0;

    public function finalize(): void
    {
        $this->finalizeCalls++;
    }
}

class EventTestLoggingService {}

class EventTestMultiService {}
