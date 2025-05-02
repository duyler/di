<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\Definition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class DefinitionTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    public function simple_definition(): void
    {
        $definition = new Definition(
            SimpleService::class,
            [
                'value' => 'test',
            ],
        );

        $this->container->addDefinition($definition);
        $service = $this->container->get(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $service);
        $this->assertEquals('test', $service->getValue());
    }

    #[Test]
    public function definition_with_dependencies(): void
    {
        $definition = new Definition(
            ServiceWithDependency::class,
            [
                'dependency' => new stdClass(),
            ],
        );

        $this->container->addDefinition($definition);
        $service = $this->container->get(ServiceWithDependency::class);

        $this->assertInstanceOf(ServiceWithDependency::class, $service);
        $this->assertInstanceOf(stdClass::class, $service->getDependency());
    }

    #[Test]
    public function definition_with_multiple_dependencies(): void
    {
        $definition = new Definition(
            ServiceWithMultipleDependencies::class,
            [
                'dependency1' => new TestDefinitionImplementation(),
                'dependency2' => new AnotherDependency(),
            ],
        );

        $this->container->addDefinition($definition);
        $service = $this->container->get(ServiceWithMultipleDependencies::class);

        $this->assertInstanceOf(ServiceWithMultipleDependencies::class, $service);
        $this->assertInstanceOf(TestDefinitionImplementation::class, $service->getDependency1());
        $this->assertInstanceOf(AnotherDependency::class, $service->getDependency2());
    }

    #[Test]
    public function definition_with_interface_binding(): void
    {
        $this->container->bind([
            TestDefinitionInterface::class => TestDefinitionImplementation::class,
        ]);

        $definition = new Definition(
            ServiceWithInterfaceDependency::class,
            [
                'dependency' => new TestDefinitionImplementation(),
            ],
        );

        $this->container->addDefinition($definition);
        $service = $this->container->get(ServiceWithInterfaceDependency::class);

        $this->assertInstanceOf(ServiceWithInterfaceDependency::class, $service);
        $this->assertInstanceOf(TestDefinitionImplementation::class, $service->getDependency());
    }
}

interface TestDefinitionInterface {}

class TestDefinitionImplementation implements TestDefinitionInterface {}

class SimpleService
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

class ServiceWithDependency
{
    private stdClass $dependency;

    public function __construct(stdClass $dependency)
    {
        $this->dependency = $dependency;
    }

    public function getDependency(): stdClass
    {
        return $this->dependency;
    }
}

class ServiceWithMultipleDependencies
{
    private TestDefinitionImplementation $dependency1;
    private AnotherDependency $dependency2;

    public function __construct(TestDefinitionImplementation $dependency1, AnotherDependency $dependency2)
    {
        $this->dependency1 = $dependency1;
        $this->dependency2 = $dependency2;
    }

    public function getDependency1(): TestDefinitionImplementation
    {
        return $this->dependency1;
    }

    public function getDependency2(): AnotherDependency
    {
        return $this->dependency2;
    }
}

class ServiceWithInterfaceDependency
{
    private TestDefinitionInterface $dependency;

    public function __construct(TestDefinitionInterface $dependency)
    {
        $this->dependency = $dependency;
    }

    public function getDependency(): TestDefinitionInterface
    {
        return $this->dependency;
    }
}

class AnotherDependency
{
    public function getValue(): string
    {
        return 'another';
    }
}
