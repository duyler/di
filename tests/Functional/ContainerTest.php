<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\Definition;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testSimpleServiceRegistration(): void
    {
        $service = new class {
            public function getValue(): string
            {
                return 'test';
            }
        };

        $this->container->set($service);
        $retrievedService = $this->container->get(get_class($service));

        $this->assertSame($service, $retrievedService);
        $this->assertEquals('test', $retrievedService->getValue());
    }

    public function testInterfaceBinding(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            TestInterface::class => TestImplementation::class,
        ]);

        $container = new Container($config);
        $service = $container->get(TestInterface::class);

        $this->assertInstanceOf(TestImplementation::class, $service);
    }

    public function testServiceDefinition(): void
    {
        $definition = new Definition(
            TestService::class,
            [
                'dependency' => new TestDependency(),
            ]
        );

        $this->container->addDefinition($definition);
        $service = $this->container->get(TestService::class);

        $this->assertInstanceOf(TestService::class, $service);
        $this->assertInstanceOf(TestDependency::class, $service->getDependency());
    }

    public function testServiceFinalization(): void
    {
        $service = new class {
            private bool $finalized = false;

            public function finalize(): void
            {
                $this->finalized = true;
            }

            public function isFinalized(): bool
            {
                return $this->finalized;
            }
        };

        $this->container->set($service);
        $this->container->addFinalizer(get_class($service), function ($s) {
            $s->finalize();
        });

        $this->container->finalize();
        $retrievedService = $this->container->get(get_class($service));

        $this->assertTrue($retrievedService->isFinalized());
    }

    public function testDependencyTree(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            TestInterface::class => TestImplementation::class,
        ]);

        $container = new Container($config);
        $container->get(TestInterface::class);
        $tree = $container->getDependencyTree();

        $this->assertIsArray($tree);
        $this->assertArrayHasKey(TestImplementation::class, $tree);
    }

    public function testContainerReset(): void
    {
        $service = new class {
            public function getValue(): string
            {
                return 'test';
            }
        };

        $this->container->set($service);
        $this->container->reset();

        $this->assertFalse($this->container->has(get_class($service)));
    }
}

interface TestInterface
{
    public function getValue(): string;
}

class TestImplementation implements TestInterface
{
    public function getValue(): string
    {
        return 'implementation';
    }
}

class TestDependency
{
    public function getValue(): string
    {
        return 'dependency';
    }
}

class TestService
{
    private TestDependency $dependency;

    public function __construct(TestDependency $dependency)
    {
        $this->dependency = $dependency;
    }

    public function getDependency(): TestDependency
    {
        return $this->dependency;
    }
} 
