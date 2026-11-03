<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\Exception\ServiceNotCreatedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;

class ServiceDecoratorsTest extends TestCase
{
    #[Test]
    public function decorates_service(): void
    {
        $container = new Container();
        $container->decorate(DecoratorTestService::class, fn($service) => new DecoratorWrapper($service));

        $service = $container->get(DecoratorTestService::class);

        $this->assertInstanceOf(DecoratorWrapper::class, $service);
        $this->assertInstanceOf(DecoratorTestService::class, $service->inner);
    }

    #[Test]
    public function applies_multiple_decorators_in_order(): void
    {
        $container = new Container();
        $container->decorate(DecoratorTestService::class, fn($service) => new DecoratorWrapper($service, 'first'));
        $container->decorate(DecoratorTestService::class, fn($service) => new DecoratorWrapper($service, 'second'));

        $service = $container->get(DecoratorTestService::class);

        $this->assertInstanceOf(DecoratorWrapper::class, $service);
        $this->assertEquals('second', $service->label);
        $this->assertEquals('first', $service->inner->label);
    }

    #[Test]
    public function decorator_receives_container(): void
    {
        $container = new Container();
        $receivedContainer = null;

        $container->decorate(DecoratorTestService::class, function ($service, $c) use (&$receivedContainer) {
            $receivedContainer = $c;
            return $service;
        });

        $container->get(DecoratorTestService::class);

        $this->assertSame($container, $receivedContainer);
    }

    #[Test]
    public function decorator_applied_once_for_singleton(): void
    {
        $container = new Container();
        $callCount = 0;
        $container->decorate(DecoratorTestService::class, function ($service) use (&$callCount) {
            $callCount++;
            return new DecoratorWrapper($service);
        });

        $service1 = $container->get(DecoratorTestService::class);
        $service2 = $container->get(DecoratorTestService::class);

        $this->assertSame($service1, $service2);
        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function decorates_factory_services(): void
    {
        $container = new Container();
        $container->factory(DecoratorTestService::class, fn() => new DecoratorTestService());
        $container->decorate(DecoratorTestService::class, fn($service) => new DecoratorWrapper($service));

        $service = $container->get(DecoratorTestService::class);

        $this->assertInstanceOf(DecoratorWrapper::class, $service);
    }

    #[Test]
    public function decorator_returning_non_object_throws_service_not_created(): void
    {
        $container = new Container();
        $container->decorate(DecoratorTestService::class, static fn(): string => 'not an object');

        try {
            $container->get(DecoratorTestService::class);
            $this->fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            $this->assertInstanceOf(ContainerExceptionInterface::class, $exception);
            $this->assertNull($exception->getPrevious());
            $this->assertStringContainsString(
                'Decorator for ' . DecoratorTestService::class . ' must return an object',
                $exception->getMessage(),
            );
        }
    }
}

class DecoratorTestService {}

class DecoratorWrapper
{
    public function __construct(
        public object $inner,
        public string $label = '',
    ) {}
}
