<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    #[Test]
    public function creates_service_using_factory(): void
    {
        $container = new Container();
        $container->factory(FactoryService::class, function (ContainerInterface $c) {
            return new FactoryService('custom_value');
        });

        $service = $container->get(FactoryService::class);

        $this->assertInstanceOf(FactoryService::class, $service);
        $this->assertEquals('custom_value', $service->getValue());
    }

    #[Test]
    public function factory_receives_container_instance(): void
    {
        $container = new Container();
        $container->factory(FactoryServiceWithDependency::class, function (ContainerInterface $c) {
            $dependency = $c->get(SimpleDependency::class);
            return new FactoryServiceWithDependency($dependency, 'from_factory');
        });

        $service = $container->get(FactoryServiceWithDependency::class);

        $this->assertInstanceOf(FactoryServiceWithDependency::class, $service);
        $this->assertInstanceOf(SimpleDependency::class, $service->getDependency());
        $this->assertEquals('from_factory', $service->getConfig());
    }

    #[Test]
    public function factory_creates_singleton_by_default(): void
    {
        $container = new Container();
        $container->factory(FactoryService::class, function (ContainerInterface $c) {
            return new FactoryService('singleton_value');
        });

        $service1 = $container->get(FactoryService::class);
        $service2 = $container->get(FactoryService::class);

        $this->assertSame($service1, $service2);
    }

    #[Test]
    public function factory_has_priority_over_autowiring(): void
    {
        $container = new Container();
        $container->factory(ServiceWithAutowiring::class, function (ContainerInterface $c) {
            return new ServiceWithAutowiring('factory_dependency');
        });

        $service = $container->get(ServiceWithAutowiring::class);

        $this->assertEquals('factory_dependency', $service->getValue());
    }

    #[Test]
    public function reset_clears_factories(): void
    {
        $container = new Container();
        $container->factory(FactoryService::class, function (ContainerInterface $c) {
            return new FactoryService('value1');
        });

        $service1 = $container->get(FactoryService::class);
        $this->assertEquals('value1', $service1->getValue());

        $container->reset();

        $container->factory(FactoryService::class, function (ContainerInterface $c) {
            return new FactoryService('value2');
        });

        $service2 = $container->get(FactoryService::class);
        $this->assertEquals('value2', $service2->getValue());
    }

    #[Test]
    public function multiple_factories_work_independently(): void
    {
        $container = new Container();

        $container->factory(FactoryService::class, function (ContainerInterface $c) {
            return new FactoryService('service1');
        });

        $container->factory(AnotherFactoryService::class, function (ContainerInterface $c) {
            return new AnotherFactoryService('service2');
        });

        $service1 = $container->get(FactoryService::class);
        $service2 = $container->get(AnotherFactoryService::class);

        $this->assertEquals('service1', $service1->getValue());
        $this->assertEquals('service2', $service2->getValue());
    }
}

class FactoryService
{
    public function __construct(private string $value) {}

    public function getValue(): string
    {
        return $this->value;
    }
}

class SimpleDependency {}

class FactoryServiceWithDependency
{
    public function __construct(
        private SimpleDependency $dependency,
        private string $config
    ) {}

    public function getDependency(): SimpleDependency
    {
        return $this->dependency;
    }

    public function getConfig(): string
    {
        return $this->config;
    }
}

class ServiceWithAutowiring
{
    public function __construct(private string $value) {}

    public function getValue(): string
    {
        return $this->value;
    }
}

class AnotherFactoryService
{
    public function __construct(private string $value) {}

    public function getValue(): string
    {
        return $this->value;
    }
}

