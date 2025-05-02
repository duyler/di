<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Attribute\Finalize;
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
            ComplexServiceInterface::class => ComplexServiceProvider::class,
        ]);

        $container = new Container($config);
        $service = $container->get(ComplexServiceInterface::class);

        $this->assertInstanceOf(ComplexService::class, $service);
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

interface ComplexServiceInterface
{
    public function getDependency(): TestProviderDependency;
}

class ComplexService implements ComplexServiceInterface
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
            ComplexServiceInterface::class => ComplexService::class,
        ];
    }

    public function accept(object $definition): void {}

    public function finalizer(): ?callable
    {
        return null;
    }

    public function factory(ContainerService $containerService): ?object
    {
        return new ComplexService(
            $containerService->getInstance(TestProviderDependency::class),
        );
    }
}

interface FinalizableInterface
{
    public function isFinalized(): bool;
}

#[Finalize]
class FinalizableService implements FinalizableInterface
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

class FinalizableProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }

    public function bind(): array
    {
        return [
            FinalizableInterface::class => FinalizableService::class,
        ];
    }

    public function accept(object $definition): void {}

    public function finalizer(): ?callable
    {
        return function (FinalizableService $service) {
            $service->finalize();
        };
    }

    public function factory(ContainerService $containerService): ?object
    {
        return new FinalizableService();
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
