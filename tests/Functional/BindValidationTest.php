<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\Exception\InvalidBindingException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BindValidationTest extends TestCase
{
    #[Test]
    public function throws_exception_when_interface_does_not_exist(): void
    {
        $this->expectException(InvalidBindingException::class);
        $this->expectExceptionMessage('Interface or class "NonExistentInterface" does not exist');

        $config = new ContainerConfig();
        $config->withBind(['NonExistentInterface' => ValidImplementation::class]);

        new Container($config);
    }

    #[Test]
    public function throws_exception_when_implementation_does_not_exist(): void
    {
        $this->expectException(InvalidBindingException::class);
        $this->expectExceptionMessage('Implementation class "NonExistentImplementation" does not exist');

        $config = new ContainerConfig();
        $config->withBind([ValidInterface::class => 'NonExistentImplementation']);

        new Container($config);
    }

    #[Test]
    public function throws_exception_when_implementation_does_not_implement_interface(): void
    {
        $this->expectException(InvalidBindingException::class);
        $this->expectExceptionMessage(
            sprintf('Class "%s" does not implement interface "%s"', InvalidImplementation::class, ValidInterface::class)
        );

        $config = new ContainerConfig();
        $config->withBind([ValidInterface::class => InvalidImplementation::class]);

        new Container($config);
    }

    #[Test]
    public function throws_exception_when_binding_concrete_class(): void
    {
        $this->expectException(InvalidBindingException::class);
        $this->expectExceptionMessage(
            sprintf('"%s" must be an interface or abstract class', ValidImplementation::class)
        );

        $config = new ContainerConfig();
        $config->withBind([ValidImplementation::class => ValidImplementation::class]);

        new Container($config);
    }

    #[Test]
    public function accepts_valid_interface_binding(): void
    {
        $config = new ContainerConfig();
        $config->withBind([ValidInterface::class => ValidImplementation::class]);

        $container = new Container($config);
        $instance = $container->get(ValidInterface::class);

        $this->assertInstanceOf(ValidImplementation::class, $instance);
    }

    #[Test]
    public function accepts_abstract_class_binding(): void
    {
        $config = new ContainerConfig();
        $config->withBind([AbstractClass::class => ConcreteClass::class]);

        $container = new Container($config);
        $service = $container->get(ServiceWithAbstractDependency::class);

        $this->assertInstanceOf(ServiceWithAbstractDependency::class, $service);
        $this->assertInstanceOf(ConcreteClass::class, $service->dependency);
    }

    #[Test]
    public function throws_exception_when_class_does_not_extend_abstract_class(): void
    {
        $this->expectException(InvalidBindingException::class);
        $this->expectExceptionMessage(
            sprintf('Class "%s" does not extend abstract class "%s"', ValidImplementation::class, AbstractClass::class)
        );

        $config = new ContainerConfig();
        $config->withBind([AbstractClass::class => ValidImplementation::class]);

        new Container($config);
    }
}

interface ValidInterface {}

class ValidImplementation implements ValidInterface {}

class InvalidImplementation {}

abstract class AbstractClass {}

class ConcreteClass extends AbstractClass {}

class ServiceWithAbstractDependency
{
    public function __construct(public AbstractClass $dependency) {}
}

