<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\ContainerService;
use Duyler\DI\Provider\ProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    public function provider_registration(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            TestProviderInterface::class => TestProvider::class,
        ]);

        $container = new Container($config);
        $service = $container->get(TestProviderInterface::class);

        $this->assertInstanceOf(TestProviderImplementation::class, $service);
        $this->assertEquals('test', $service->getValue());
    }

    #[Test]
    public function provider_with_dependencies(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            ComplexProviderServiceInterface::class => ComplexServiceProvider::class,
        ]);

        $container = new Container($config);
        $service = $container->get(ComplexProviderServiceInterface::class);

        $this->assertInstanceOf(ComplexProviderService::class, $service);
        $this->assertInstanceOf(TestProviderDependency::class, $service->getDependency());
    }

    #[Test]
    public function provider_arguments(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            ArgumentServiceInterface::class => ArgumentServiceProvider::class,
        ]);

        $container = new Container($config);
        $service = $container->get(ArgumentServiceInterface::class);

        $this->assertEquals(['dependency' => 'test'], $service->getArguments());
    }

    #[Test]
    public function reentrant_instance_of_other_class_resolves(): void
    {
        $this->container->addProviders([
            ReentrantTargetService::class => ReentrantProvider::class,
        ]);

        $service = $this->container->get(ReentrantTargetService::class);

        $this->assertInstanceOf(ReentrantTargetService::class, $service);
        $this->assertInstanceOf(ReentrantDependencyService::class, $service->dependency);
        $this->assertTrue($this->container->has(ReentrantDependencyService::class));
        $this->assertTrue($this->container->has(ReentrantTargetService::class));
    }
}

interface TestProviderInterface
{
    public function getValue(): string;
}

class TestProviderImplementation implements TestProviderInterface
{
    public function getValue(): string
    {
        return 'test';
    }
}

class TestProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }

    public function bind(): array
    {
        return [
            TestProviderInterface::class => TestProviderImplementation::class,
        ];
    }

    public function accept(object $definition): void {}

    public function finalizer(): ?callable
    {
        return null;
    }

    public function factory(ContainerService $containerService): ?object
    {
        return new TestProviderImplementation();
    }
}

interface ComplexProviderServiceInterface
{
    public function getDependency(): TestProviderDependency;
}

class ComplexProviderService implements ComplexProviderServiceInterface
{
    private TestProviderDependency $dependency;

    public function __construct(TestProviderDependency $dependency)
    {
        $this->dependency = $dependency;
    }

    public function getDependency(): TestProviderDependency
    {
        return $this->dependency;
    }
}

class ComplexServiceProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [
            'dependency' => $containerService->getInstance(TestProviderDependency::class),
        ];
    }

    public function bind(): array
    {
        return [
            ComplexProviderServiceInterface::class => ComplexProviderService::class,
        ];
    }

    public function accept(object $definition): void {}

    public function finalizer(): ?callable
    {
        return null;
    }

    public function factory(ContainerService $containerService): ?object
    {
        return new ComplexProviderService(
            $containerService->getInstance(TestProviderDependency::class),
        );
    }
}

interface ArgumentServiceInterface
{
    public function getArguments(): array;
}

class ArgumentService implements ArgumentServiceInterface
{
    private array $arguments;

    public function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }
}

class ArgumentServiceProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [
            'dependency' => 'test',
        ];
    }

    public function bind(): array
    {
        return [
            ArgumentServiceInterface::class => ArgumentService::class,
        ];
    }

    public function accept(object $definition): void {}

    public function finalizer(): ?callable
    {
        return null;
    }

    public function factory(ContainerService $containerService): ?object
    {
        return new ArgumentService($this->getArguments($containerService));
    }
}

class TestProviderDependency
{
    public function getValue(): string
    {
        return 'dependency';
    }
}

class ReentrantTargetService
{
    public function __construct(public ReentrantDependencyService $dependency) {}
}

class ReentrantDependencyService {}

class ReentrantProvider implements ProviderInterface
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
        $dependency = $containerService->getInstance(ReentrantDependencyService::class);

        return new ReentrantTargetService($dependency);
    }
}
