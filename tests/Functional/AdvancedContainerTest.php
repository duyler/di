<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Attribute\Finalize;
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\ContainerService;
use Duyler\DI\Definition;
use Duyler\DI\Provider\ProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdvancedContainerTest extends TestCase
{
    #[Test]
    public function bind_method_adds_interface_to_class_mapping(): void
    {
        $container = new Container();
        $container->bind([
            TestBindInterface::class => TestBindImplementation::class,
        ]);

        $service = $container->get(TestBindInterface::class);

        $this->assertInstanceOf(TestBindImplementation::class, $service);
    }

    #[Test]
    public function add_providers_method(): void
    {
        $container = new Container();
        $container->addProviders([
            AddProviderInterface::class => AddProviderClass::class,
        ]);

        $service = $container->get(AddProviderInterface::class);

        $this->assertInstanceOf(AddProviderImplementation::class, $service);
    }

    #[Test]
    public function provider_accept_method_is_called(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            AcceptInterface::class => AcceptProvider::class,
        ]);

        $container = new Container($config);
        $service = $container->get(AcceptInterface::class);

        $this->assertTrue($service->isInitialized());
    }

    #[Test]
    public function provider_without_factory_uses_constructor(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            NoFactoryInterface::class => NoFactoryProvider::class,
        ]);

        $container = new Container($config);
        $service = $container->get(NoFactoryInterface::class);

        $this->assertInstanceOf(NoFactoryImplementation::class, $service);
        $this->assertEquals('provided', $service->getValue());
    }

    #[Test]
    public function finalize_with_attribute_and_custom_method_name(): void
    {
        $container = new Container();
        $service = $container->get(CustomFinalizeMethod::class);

        $this->assertFalse($service->isShutdown());

        $container->finalize();

        $this->assertTrue($service->isShutdown());
    }

    #[Test]
    public function add_multiple_finalizers_for_different_services(): void
    {
        $container = new Container();

        $service1 = $container->get(FinalizableService1::class);
        $service2 = $container->get(FinalizableService2::class);

        $container->addFinalizer(FinalizableService1::class, fn($s) => $s->finalize());
        $container->addFinalizer(FinalizableService2::class, fn($s) => $s->finalize());

        $container->finalize();

        $this->assertTrue($service1->isFinalized());
        $this->assertTrue($service2->isFinalized());
    }

    #[Test]
    public function definition_overrides_constructor_parameters(): void
    {
        $customValue = 'custom_value';
        $definition = new Definition(
            ServiceWithParameter::class,
            ['value' => $customValue],
        );

        $container = new Container();
        $container->addDefinition($definition);

        $service = $container->get(ServiceWithParameter::class);

        $this->assertEquals($customValue, $service->getValue());
    }

    #[Test]
    public function multiple_definitions_for_different_classes(): void
    {
        $container = new Container();

        $container->addDefinition(new Definition(
            ServiceWithParameter::class,
            ['value' => 'value1'],
        ));

        $container->addDefinition(new Definition(
            AnotherServiceWithParameter::class,
            ['value' => 'value2'],
        ));

        $service1 = $container->get(ServiceWithParameter::class);
        $service2 = $container->get(AnotherServiceWithParameter::class);

        $this->assertEquals('value1', $service1->getValue());
        $this->assertEquals('value2', $service2->getValue());
    }

    #[Test]
    public function interface_in_provider_bind_gets_registered(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            BindingInterface::class => BindingProvider::class,
        ]);

        $container = new Container($config);

        $service1 = $container->get(BindingInterface::class);
        $service2 = $container->get(BindingImplementation::class);

        $this->assertInstanceOf(BindingImplementation::class, $service1);
        $this->assertInstanceOf(BindingImplementation::class, $service2);
    }

    #[Test]
    public function config_with_all_options(): void
    {
        $definition = new Definition(ServiceWithParameter::class, ['value' => 'test']);

        $config = new ContainerConfig();
        $config
            ->withBind([TestBindInterface::class => TestBindImplementation::class])
            ->withProvider([AddProviderInterface::class => AddProviderClass::class])
            ->withDefinition($definition);

        $container = new Container($config);

        $bindService = $container->get(TestBindInterface::class);
        $providerService = $container->get(AddProviderInterface::class);
        $definitionService = $container->get(ServiceWithParameter::class);

        $this->assertInstanceOf(TestBindImplementation::class, $bindService);
        $this->assertInstanceOf(AddProviderImplementation::class, $providerService);
        $this->assertInstanceOf(ServiceWithParameter::class, $definitionService);
    }
}

interface TestBindInterface {}
class TestBindImplementation implements TestBindInterface {}

interface AddProviderInterface {}
class AddProviderImplementation implements AddProviderInterface {}

class AddProviderClass implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }
    public function bind(): array
    {
        return [AddProviderInterface::class => AddProviderImplementation::class];
    }
    public function accept(object $definition): void {}
    public function finalizer(): ?callable
    {
        return null;
    }
    public function factory(ContainerService $containerService): ?object
    {
        return new AddProviderImplementation();
    }
}

interface AcceptInterface
{
    public function isInitialized(): bool;
}

class AcceptImplementation implements AcceptInterface
{
    private bool $initialized = false;

    public function initialize(): void
    {
        $this->initialized = true;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}

class AcceptProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }
    public function bind(): array
    {
        return [AcceptInterface::class => AcceptImplementation::class];
    }

    public function accept(object $definition): void
    {
        if ($definition instanceof AcceptImplementation) {
            $definition->initialize();
        }
    }

    public function finalizer(): ?callable
    {
        return null;
    }
    public function factory(ContainerService $containerService): ?object
    {
        return null;
    }
}

interface NoFactoryInterface
{
    public function getValue(): string;
}

class NoFactoryImplementation implements NoFactoryInterface
{
    public function __construct(private string $value) {}
    public function getValue(): string
    {
        return $this->value;
    }
}

class NoFactoryProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return ['value' => 'provided'];
    }

    public function bind(): array
    {
        return [NoFactoryInterface::class => NoFactoryImplementation::class];
    }

    public function accept(object $definition): void {}
    public function finalizer(): ?callable
    {
        return null;
    }
    public function factory(ContainerService $containerService): ?object
    {
        return null;
    }
}

#[Finalize(method: 'shutdown')]
class CustomFinalizeMethod
{
    private bool $isShutdown = false;

    public function shutdown(): void
    {
        $this->isShutdown = true;
    }

    public function isShutdown(): bool
    {
        return $this->isShutdown;
    }
}

class FinalizableService1
{
    private bool $finalized = false;

    public function finalize(): void
    {
        $this->finalized = true;
    }

    public function isFinalized(): bool
    {
        return $this->finalized;
    }
}

class FinalizableService2
{
    private bool $finalized = false;

    public function finalize(): void
    {
        $this->finalized = true;
    }

    public function isFinalized(): bool
    {
        return $this->finalized;
    }
}

class ServiceWithParameter
{
    public function __construct(private string $value) {}
    public function getValue(): string
    {
        return $this->value;
    }
}

class AnotherServiceWithParameter
{
    public function __construct(private string $value) {}
    public function getValue(): string
    {
        return $this->value;
    }
}

interface BindingInterface {}
class BindingImplementation implements BindingInterface {}

class BindingProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }

    public function bind(): array
    {
        return [
            BindingInterface::class => BindingImplementation::class,
        ];
    }

    public function accept(object $definition): void {}
    public function finalizer(): ?callable
    {
        return null;
    }
    public function factory(ContainerService $containerService): ?object
    {
        return null;
    }
}
