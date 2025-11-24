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
    public function detects_error_when_interface_does_not_exist(): void
    {
        $config = new ContainerConfig();
        $config->withBind(['NonExistentInterface' => ValidImplementation::class]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Class "NonExistentInterface" does not exist', $errors[0]);
    }

    #[Test]
    public function detects_error_when_implementation_does_not_exist(): void
    {
        $config = new ContainerConfig();
        $config->withBind([ValidInterface::class => 'NonExistentImplementation']);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Class "NonExistentImplementation" does not exist', $errors[0]);
    }

    #[Test]
    public function detects_error_when_implementation_does_not_implement_interface(): void
    {
        $config = new ContainerConfig();
        $config->withBind([ValidInterface::class => InvalidImplementation::class]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString(
            sprintf('Class "%s" does not implement interface "%s"', InvalidImplementation::class, ValidInterface::class),
            $errors[0]
        );
    }

    #[Test]
    public function detects_error_when_binding_concrete_class(): void
    {
        $config = new ContainerConfig();
        $config->withBind([ValidImplementation::class => ValidImplementation::class]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString(
            sprintf('"%s" must be an interface or abstract class', ValidImplementation::class),
            $errors[0]
        );
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
    public function detects_error_when_class_does_not_extend_abstract_class(): void
    {
        $config = new ContainerConfig();
        $config->withBind([AbstractClass::class => ValidImplementation::class]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString(
            sprintf('Class "%s" does not extend abstract class "%s"', ValidImplementation::class, AbstractClass::class),
            $errors[0]
        );
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

