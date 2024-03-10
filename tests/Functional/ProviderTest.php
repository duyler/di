<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Test\Functional;

use Duyler\DependencyInjection\Container;
use Duyler\DependencyInjection\Provider\AbstractProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase
{
    #[Test]
    public function get_with_provider_call_accept(): void
    {
        $container = new Container();
        $container->addProviders([MyClass::class => ProviderWithAccept::class]);
        $definition = $container->get(MyClass::class);
        $this->assertInstanceOf(MyClass::class, $definition);
        $this->assertSame('test', $definition->getValue());
    }

    #[Test]
    public function get_by_interface_with_provider_call_accept(): void
    {
        $container = new Container();
        $container->addProviders([MyClassInterface::class => ProviderWithAccept::class]);
        $definition = $container->get(MyClassInterface::class);
        $this->assertInstanceOf(MyClass::class, $definition);
        $this->assertSame('test', $definition->getValue());
    }

    #[Test]
    public function get_with_dependency_with_provider_call_accept(): void
    {
        $container = new Container();
        $container->addProviders([MyClassInterface::class => ProviderWithAccept::class]);
        $definition = $container->get(MyClassWithDependency::class);
        $this->assertInstanceOf(MyClassWithDependency::class, $definition);
        $this->assertInstanceOf(MyClassInterface::class, $definition->getDependency());
        $this->assertInstanceOf(MyClass::class, $definition->getDependency());
    }
}

class ProviderWithAccept extends AbstractProvider
{
    public function bind(): array
    {
        return [MyClassInterface::class => MyClass::class];
    }

    public function accept(object $definition): void
    {
        /** @var MyClass $definition */
        $definition->setValue('test');
    }
}

interface MyClassInterface
{
}

class MyClass implements MyClassInterface
{
    private ?string $value = null;

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}

class MyClassWithDependency
{
    public function __construct(
        private MyClassInterface $dependency,
    ) {}

    public function getDependency(): MyClassInterface
    {
        return $this->dependency;
    }
}
