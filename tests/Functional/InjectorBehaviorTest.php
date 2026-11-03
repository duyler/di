<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\ContainerService;
use Duyler\DI\Exception\ResolveDependenciesTreeException;
use Duyler\DI\Exception\ServiceNotCreatedException;
use Duyler\DI\Provider\ProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InjectorBehaviorTest extends TestCase
{
    #[Test]
    public function reentrant_get_in_provider_does_not_cross_trees(): void
    {
        $container = new Container();
        $container->addProviders([
            InjectorReentrantC::class => InjectorReentrantCProvider::class,
        ]);

        $service = $container->get(InjectorReentrantA::class);

        $this->assertInstanceOf(InjectorReentrantA::class, $service);
        $this->assertInstanceOf(InjectorReentrantB::class, $service->dependency);
        $this->assertInstanceOf(InjectorReentrantC::class, $service->dependency->dependency);

        $nested = $container->get(InjectorReentrantX::class);

        $this->assertInstanceOf(InjectorReentrantX::class, $nested);
        $this->assertInstanceOf(InjectorReentrantY::class, $nested->dependency);
        $this->assertTrue($container->has(InjectorReentrantY::class));
    }

    #[Test]
    public function sequential_builds_of_different_classes_are_isolated(): void
    {
        $container = new Container();
        $container->addProviders([
            InjectorReentrantC::class => InjectorReentrantCProvider::class,
        ]);

        $first = $container->get(InjectorReentrantA::class);
        $second = $container->get(InjectorReentrantX::class);
        $third = $container->get(InjectorReentrantA::class);

        $this->assertNotSame($first, $second);
        $this->assertSame($first, $third);
        $this->assertInstanceOf(InjectorReentrantY::class, $second->dependency);
    }

    #[Test]
    public function transient_provider_factory_service_is_reused_across_gets(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([InjectorTransientProvided::class => InjectorTransientProvidedServiceProvider::class]);
        $config->withScope(InjectorTransientProvided::class, \Duyler\DI\Scope::Transient);
        $container = new Container($config);

        $first = $container->get(InjectorTransientProvided::class);
        $second = $container->get(InjectorTransientProvided::class);

        $this->assertSame($first, $second);
        $this->assertSame('provided', $first->marker);
    }

    #[Test]
    public function transient_with_dependencies_builds_via_tree(): void
    {
        $config = new ContainerConfig();
        $config->withScope(InjectorTransientWithDeps::class, \Duyler\DI\Scope::Transient);
        $container = new Container($config);

        $first = $container->get(InjectorTransientWithDeps::class);
        $second = $container->get(InjectorTransientWithDeps::class);

        $this->assertNotSame($first, $second);
        $this->assertSame($first->dependency, $second->dependency);
        $this->assertInstanceOf(InjectorTransientLeaf::class, $first->dependency);
    }

    #[Test]
    public function transient_without_dependencies_builds_without_tree(): void
    {
        $config = new ContainerConfig();
        $config->withScope(InjectorTransientLeaf::class, \Duyler\DI\Scope::Transient);
        $container = new Container($config);

        $first = $container->get(InjectorTransientLeaf::class);
        $second = $container->get(InjectorTransientLeaf::class);

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function transient_failure_without_dependencies_wraps_in_tree_exception(): void
    {
        $config = new ContainerConfig();
        $config->withScope(InjectorThrowingTransient::class, \Duyler\DI\Scope::Transient);
        $container = new Container($config);

        try {
            $container->get(InjectorThrowingTransient::class);
            $this->fail('ResolveDependenciesTreeException expected');
        } catch (ResolveDependenciesTreeException $exception) {
            $this->assertStringContainsString(InjectorThrowingTransient::class, $exception->getMessage());
        }
    }

    #[Test]
    public function transient_failure_with_dependencies_wraps_in_tree_exception(): void
    {
        $config = new ContainerConfig();
        $config->withScope(InjectorThrowingTransientWithDeps::class, \Duyler\DI\Scope::Transient);
        $container = new Container($config);

        try {
            $container->get(InjectorThrowingTransientWithDeps::class);
            $this->fail('ResolveDependenciesTreeException expected');
        } catch (ResolveDependenciesTreeException $exception) {
            $this->assertStringContainsString(InjectorThrowingTransientWithDeps::class, $exception->getMessage());
        }
    }

    #[Test]
    public function uncovered_scalar_constructor_parameter_throws_service_not_created(): void
    {
        $container = new Container();

        try {
            $container->get(InjectorUncoveredScalarService::class);
            $this->fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            $this->assertStringContainsString(
                'Parameter "name" of "' . InjectorUncoveredScalarService::class . '" cannot be autowired',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function uncovered_scalar_transient_parameter_throws_service_not_created(): void
    {
        $config = new ContainerConfig();
        $config->withScope(InjectorUncoveredScalarTransient::class, \Duyler\DI\Scope::Transient);
        $container = new Container($config);

        try {
            $container->get(InjectorUncoveredScalarTransient::class);
            $this->fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            $this->assertStringContainsString(
                'Parameter "label" of "' . InjectorUncoveredScalarTransient::class . '" cannot be autowired',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function variadic_builtin_parameter_resolves(): void
    {
        $container = new Container();
        $service = $container->get(InjectorVariadicService::class);

        $this->assertInstanceOf(InjectorVariadicService::class, $service);
        $this->assertSame([], $service->labels);
    }

    #[Test]
    public function nullable_uncovered_parameter_keeps_legacy_behavior(): void
    {
        $container = new Container();

        try {
            $container->get(InjectorNullableLegacyService::class);
            $this->fail('ResolveDependenciesTreeException expected');
        } catch (ResolveDependenciesTreeException $exception) {
            $this->assertStringContainsString(InjectorNullableLegacyService::class, $exception->getMessage());
        }
    }
}

class InjectorReentrantA
{
    public function __construct(public readonly InjectorReentrantB $dependency) {}
}

class InjectorReentrantB
{
    public function __construct(public readonly InjectorReentrantC $dependency) {}
}

class InjectorReentrantC
{
    public function __construct() {}
}

class InjectorReentrantX
{
    public function __construct(public readonly InjectorReentrantY $dependency) {}
}

class InjectorReentrantY {}

class InjectorReentrantCProvider implements ProviderInterface
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
        $containerService->getInstance(InjectorReentrantX::class);

        return new InjectorReentrantC();
    }
}

class InjectorTransientProvided
{
    public function __construct(public readonly string $marker) {}
}

class InjectorTransientProvidedServiceProvider implements ProviderInterface
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
        return new InjectorTransientProvided('provided');
    }
}

class InjectorTransientWithDeps
{
    public function __construct(public readonly InjectorTransientLeaf $dependency) {}
}

class InjectorTransientLeaf {}

class InjectorThrowingTransient
{
    public function __construct()
    {
        throw new RuntimeException('transient creation failed');
    }
}

class InjectorThrowingTransientWithDeps
{
    public function __construct(InjectorTransientLeaf $dependency)
    {
        throw new RuntimeException('transient creation with dependencies failed');
    }
}

class InjectorUncoveredScalarService
{
    public function __construct(public readonly string $name) {}
}

class InjectorUncoveredScalarTransient
{
    public function __construct(string $label) {}
}

class InjectorVariadicService
{
    /** @var array<string> */
    public readonly array $labels;

    public function __construct(string ...$labels)
    {
        $this->labels = $labels;
    }
}

class InjectorNullableLegacyService
{
    public function __construct(public readonly ?string $value) {}
}
