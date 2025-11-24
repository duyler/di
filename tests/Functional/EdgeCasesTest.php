<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\ContainerService;
use Duyler\DI\Definition;
use Duyler\DI\Exception\InterfaceBindNotFoundException;
use Duyler\DI\Provider\ProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class EdgeCasesTest extends TestCase
{
    #[Test]
    public function service_without_constructor(): void
    {
        $container = new Container();
        $service = $container->get(ServiceWithoutConstructor::class);

        $this->assertInstanceOf(ServiceWithoutConstructor::class, $service);
    }

    #[Test]
    public function service_with_empty_constructor(): void
    {
        $container = new Container();
        $service = $container->get(ServiceWithEmptyConstructor::class);

        $this->assertInstanceOf(ServiceWithEmptyConstructor::class, $service);
    }

    #[Test]
    public function service_with_optional_parameter(): void
    {
        $container = new Container();
        $service = $container->get(ServiceWithOptionalParameter::class);

        $this->assertInstanceOf(ServiceWithOptionalParameter::class, $service);
        $this->assertNull($service->getValue());
    }

    #[Test]
    public function definition_with_optional_parameter_provided(): void
    {
        $container = new Container();
        $container->addDefinition(new Definition(
            ServiceWithOptionalParameter::class,
            ['value' => 'provided'],
        ));

        $service = $container->get(ServiceWithOptionalParameter::class);

        $this->assertEquals('provided', $service->getValue());
    }

    #[Test]
    public function provider_factory_returns_wrong_type(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            WrongTypeInterface::class => WrongTypeProvider::class,
        ]);

        $container = new Container($config);

        $this->expectException(InterfaceBindNotFoundException::class);
        $container->get(WrongTypeInterface::class);
    }

    #[Test]
    public function multiple_interfaces_same_implementation(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            Interface1::class => SharedImplementation::class,
            Interface2::class => SharedImplementation::class,
        ]);

        $container = new Container($config);

        $service1 = $container->get(Interface1::class);
        $service2 = $container->get(Interface2::class);

        $this->assertSame($service1, $service2);
    }

    #[Test]
    public function service_created_before_adding_finalizer(): void
    {
        $container = new Container();
        $service = $container->get(SimpleFinalizableService::class);

        $this->assertFalse($service->isFinalized());

        $container->addFinalizer(SimpleFinalizableService::class, function ($s) {
            $s->finalize();
        });

        $container->finalize();

        $this->assertTrue($service->isFinalized());
    }

    #[Test]
    public function finalize_non_existing_service_does_not_throw(): void
    {
        $container = new Container();
        $container->addFinalizer('NonExistentService', function ($s) {});

        $container->finalize();

        $this->assertTrue(true);
    }

    #[Test]
    public function reset_after_finalize(): void
    {
        $container = new Container();
        $service = $container->get(SimpleFinalizableService::class);

        $container->addFinalizer(SimpleFinalizableService::class, function ($s) {
            $s->finalize();
        });

        $container->finalize();
        $this->assertTrue($service->isFinalized());

        $container->reset();

        $newService = $container->get(SimpleFinalizableService::class);
        $this->assertFalse($newService->isFinalized());
        $this->assertNotSame($service, $newService);
    }

    #[Test]
    public function get_dependency_tree_before_creating_services(): void
    {
        $container = new Container();
        $tree = $container->getDependencyTree();

        $this->assertEmpty($tree);
    }

    #[Test]
    public function provider_with_getarguments_returning_objects(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            ServiceWithObjectDependency::class => ObjectDependencyProvider::class,
        ]);

        $container = new Container($config);
        $service = $container->get(ServiceWithObjectDependency::class);

        $this->assertInstanceOf(ServiceWithObjectDependency::class, $service);
        $this->assertInstanceOf(DependencyObject::class, $service->getDependency());
    }

    #[Test]
    public function service_already_set_not_recreated(): void
    {
        $container = new Container();
        $manualService = new ManualService();
        $manualService->value = 'manual';

        $container->set($manualService);
        $retrieved = $container->get(ManualService::class);

        $this->assertSame($manualService, $retrieved);
        $this->assertEquals('manual', $retrieved->value);
    }

    #[Test]
    public function definition_merges_with_provider_arguments(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            MergedArgumentsService::class => MergedArgumentsProvider::class,
        ]);

        $container = new Container($config);
        $container->addDefinition(new Definition(
            MergedArgumentsService::class,
            ['arg2' => 'from_definition'],
        ));

        $service = $container->get(MergedArgumentsService::class);

        $this->assertEquals('from_provider', $service->getArg1());
        $this->assertEquals('from_definition', $service->getArg2());
    }
}

class ServiceWithoutConstructor
{
    public string $value = 'test';
}

class ServiceWithEmptyConstructor
{
    public function __construct() {}
}

class ServiceWithOptionalParameter
{
    public function __construct(private ?string $value = null) {}
    public function getValue(): ?string
    {
        return $this->value;
    }
}

interface WrongTypeInterface {}

class WrongTypeProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }
    public function bind(): array
    {
        return [];
    }
    public function accept(object $definition): void {}
    public function finalizer(): ?callable
    {
        return null;
    }

    public function factory(ContainerService $containerService): ?object
    {
        return new stdClass();
    }
}

interface Interface1 {}
interface Interface2 {}
class SharedImplementation implements Interface1, Interface2 {}

class SimpleFinalizableService
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

class DependencyObject
{
    public string $value = 'dependency';
}

class ServiceWithObjectDependency
{
    public function __construct(private DependencyObject $dependency) {}
    public function getDependency(): DependencyObject
    {
        return $this->dependency;
    }
}

class ObjectDependencyProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return ['dependency' => new DependencyObject()];
    }

    public function bind(): array
    {
        return [];
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

class ManualService
{
    public string $value = 'default';
}

class MergedArgumentsService
{
    public function __construct(
        private string $arg1,
        private string $arg2,
    ) {}

    public function getArg1(): string
    {
        return $this->arg1;
    }
    public function getArg2(): string
    {
        return $this->arg2;
    }
}

class MergedArgumentsProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return ['arg1' => 'from_provider'];
    }

    public function bind(): array
    {
        return [];
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
