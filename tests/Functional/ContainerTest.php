<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Attribute\Bind;
use Duyler\DI\Attribute\Tag;
use Duyler\DI\Attribute\Transient;
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\ContainerService;
use Duyler\DI\Definition;
use Duyler\DI\Exception\InvalidBindingException;
use Duyler\DI\Exception\NotFoundException;
use Duyler\DI\Exception\ServiceNotCreatedException;
use Duyler\DI\Provider\ProviderInterface;
use Duyler\DI\Scope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    public function simple_service_registration(): void
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

    #[Test]
    public function interface_binding(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            TestInterface::class => TestImplementation::class,
        ]);

        $container = new Container($config);
        $service = $container->get(TestInterface::class);

        $this->assertInstanceOf(TestImplementation::class, $service);
    }

    #[Test]
    public function service_definition(): void
    {
        $definition = new Definition(
            TestService::class,
            [
                'dependency' => new TestDependency(),
            ],
        );

        $this->container->addDefinition($definition);
        $service = $this->container->get(TestService::class);

        $this->assertInstanceOf(TestService::class, $service);
        $this->assertInstanceOf(TestDependency::class, $service->getDependency());
    }

    #[Test]
    public function service_finalization(): void
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

    #[Test]
    public function dependency_tree(): void
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

    #[Test]
    public function container_reset(): void
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

    #[Test]
    public function factory_failure_throws_service_not_created(): void
    {
        $container = new Container();
        $cause = new RuntimeException('factory exploded');
        $container->factory(ReasonLeakContract::class, static function () use ($cause): never {
            throw $cause;
        });

        try {
            $container->get(ReasonLeakContract::class);
            $this->fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            $this->assertSame($cause, $exception->getPrevious());
            $this->assertStringNotContainsString('factory exploded', $exception->getMessage());
        }
    }

    #[Test]
    public function after_resolve_listener_failure_on_cache_hit_throws_service_not_created(): void
    {
        $container = new Container();
        $container->bind([ReasonLeakContract::class => ReasonLeakImplementation::class]);
        $first = $container->get(ReasonLeakContract::class);
        $this->assertInstanceOf(ReasonLeakImplementation::class, $first);

        $cause = new RuntimeException('listener exploded on cache hit');
        $container->on(
            \Duyler\DI\Event\ContainerEvents::AFTER_RESOLVE,
            static function () use ($cause): never {
                throw $cause;
            },
        );

        try {
            $container->get(ReasonLeakContract::class);
            $this->fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            $this->assertSame($cause, $exception->getPrevious());
            $this->assertStringNotContainsString('listener exploded on cache hit', $exception->getMessage());
        }
    }

    #[Test]
    public function failed_finalizer_can_be_retried(): void
    {
        $container = new Container();
        $service = new StrictThrowableFinalizeService();
        $container->set($service);

        try {
            $container->finalize();
            $this->fail('RuntimeException expected');
        } catch (RuntimeException $exception) {
            $this->assertSame('finalizer failed', $exception->getMessage());
        }

        try {
            $container->finalize();
            $this->fail('RuntimeException expected on retry');
        } catch (RuntimeException $exception) {
            $this->assertSame('finalizer failed', $exception->getMessage());
        }

        $this->assertSame(2, $service->finalizeCalls);
    }

    #[Test]
    public function strict_resolution_blocks_unregistered_class(): void
    {
        $config = new ContainerConfig();
        $config->withStrictResolution(true);
        $container = new Container($config);

        try {
            $container->get(StrictUnregisteredService::class);
            $this->fail('NotFoundException expected');
        } catch (NotFoundException $exception) {
            $this->assertFalse($container->has(StrictUnregisteredService::class));
            $this->assertStringNotContainsString('Did you mean', $exception->getMessage());
        }
    }

    #[Test]
    public function strict_resolution_blocks_unbound_interface(): void
    {
        $config = new ContainerConfig();
        $config->withStrictResolution(true);
        $container = new Container($config);

        $this->expectException(NotFoundException::class);
        $container->get(StrictUnboundContract::class);
    }

    #[Test]
    public function strict_resolution_allows_registered_services(): void
    {
        $config = new ContainerConfig();
        $config->withStrictResolution(true);
        $container = new Container($config);

        $registered = new StrictRegisteredService();
        $container->set($registered);
        $container->bind([StrictContract::class => StrictBoundImplementation::class]);
        $container->factory(StrictFactoryService::class, static fn(): object => new StrictFactoryService());

        $this->assertSame($registered, $container->get(StrictRegisteredService::class));
        $this->assertInstanceOf(StrictBoundImplementation::class, $container->get(StrictContract::class));
        $this->assertInstanceOf(StrictFactoryService::class, $container->get(StrictFactoryService::class));
    }

    #[Test]
    public function strict_resolution_allows_providers_and_tagged(): void
    {
        $config = new ContainerConfig();
        $config->withStrictResolution(true);
        $config->withProvider([
            StrictProviderContract::class => StrictProviderImplementation::class,
        ]);
        $container = new Container($config);

        $tagged = new StrictTaggedService();
        $container->set($tagged);
        $container->tag(StrictTaggedService::class, 'strict-tag');

        $this->assertInstanceOf(
            StrictProviderImplementation::class,
            $container->get(StrictProviderContract::class),
        );

        $resolved = $container->tagged('strict-tag');

        $this->assertCount(1, $resolved);
        $this->assertSame($tagged, $resolved[0]);
    }

    #[Test]
    public function strict_resolution_blocks_unregistered_transient(): void
    {
        $config = new ContainerConfig();
        $config->withScope(StrictTransientUnregistered::class, Scope::Transient);
        $config->withStrictResolution(true);
        $container = new Container($config);

        try {
            $container->get(StrictTransientUnregistered::class);
            $this->fail('NotFoundException expected');
        } catch (NotFoundException $exception) {
            $this->assertFalse($container->has(StrictTransientUnregistered::class));
        }
    }

    #[Test]
    public function strict_resolution_allows_registered_transient(): void
    {
        $config = new ContainerConfig();
        $config->withStrictResolution(true);
        $container = new Container($config);
        $container->addProviders([
            StrictTransientUnregistered::class => StrictTransientProvider::class,
        ]);

        $first = $container->get(StrictTransientUnregistered::class);
        $second = $container->get(StrictTransientUnregistered::class);

        $this->assertInstanceOf(StrictTransientUnregistered::class, $first);
        $this->assertInstanceOf(StrictTransientUnregistered::class, $second);
    }

    #[Test]
    public function service_not_created_hides_reason_outside_debug(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            ReasonLeakContract::class => ReasonLeakServiceProvider::class,
        ]);
        $container = new Container($config);
        $cause = new RuntimeException('secret internal failure details');
        $container->decorate(ReasonLeakContract::class, static function () use ($cause): string {
            throw $cause;
        });

        try {
            $container->get(ReasonLeakContract::class);
            $this->fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            $this->assertStringNotContainsString('secret internal failure details', $exception->getMessage());
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    #[Test]
    public function service_not_created_exposes_reason_in_debug(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            ReasonLeakContract::class => ReasonLeakServiceProvider::class,
        ]);
        $container = new Container($config);
        $container->enableDebug();
        $cause = new RuntimeException('secret internal failure details');
        $container->decorate(ReasonLeakContract::class, static function () use ($cause): string {
            throw $cause;
        });

        try {
            $container->get(ReasonLeakContract::class);
            $this->fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            $this->assertStringContainsString('secret internal failure details', $exception->getMessage());
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    #[Test]
    public function bind_after_resolution_invalidates_dependency_trees(): void
    {
        $config = new ContainerConfig();
        $config->withBind([StaleContract::class => StaleFirstImplementation::class]);
        $config->withScope(StaleDependentService::class, Scope::Transient);
        $container = new Container($config);

        $first = $container->get(StaleDependentService::class);

        $this->assertInstanceOf(StaleFirstImplementation::class, $first->dependency);

        $container->bind([StaleContract::class => StaleSecondImplementation::class]);

        $second = $container->get(StaleDependentService::class);

        $this->assertInstanceOf(StaleSecondImplementation::class, $second->dependency);
    }

    #[Test]
    public function add_providers_after_resolution_invalidates_dependency_trees(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([StaleContract::class => StaleFirstProvider::class]);
        $config->withScope(StaleDependentService::class, Scope::Transient);
        $container = new Container($config);

        $first = $container->get(StaleDependentService::class);

        $this->assertInstanceOf(StaleFirstImplementation::class, $first->dependency);

        $container->addProviders([StaleContract::class => StaleSecondProvider::class]);

        $second = $container->get(StaleDependentService::class);

        $this->assertInstanceOf(StaleSecondImplementation::class, $second->dependency);
    }

    #[Test]
    public function factory_does_not_invalidate_dependency_trees(): void
    {
        $container = new Container();
        $container->get(TreePlainService::class);

        $this->assertArrayHasKey(TreePlainService::class, $container->getDependencyTree());

        $container->factory(TreePlainService::class, static fn(): object => new TreePlainService());
        $container->get(TreePlainService::class);

        $this->assertArrayHasKey(TreePlainService::class, $container->getDependencyTree());
    }

    #[Test]
    public function attribute_application_stays_idempotent_after_caching(): void
    {
        $container = new Container();

        $first = $container->get(CachedAttributedService::class);
        $second = $container->get(CachedAttributedService::class);

        $this->assertSame($first, $second);

        $tagged = $container->tagged('cached-tag');

        $this->assertCount(1, $tagged);
        $this->assertSame($first, $tagged[0]);
    }

    #[Test]
    public function bind_attribute_validation_runs_on_every_get(): void
    {
        $container = new Container();

        try {
            $container->get(TreeBindMisuseService::class);
            $this->fail('InvalidBindingException expected');
        } catch (InvalidBindingException $exception) {
            $this->assertStringContainsString(TreeMissingInterface::class, $exception->getMessage());
        }

        try {
            $container->get(TreeBindMisuseService::class);
            $this->fail('InvalidBindingException expected');
        } catch (InvalidBindingException $exception) {
            $this->assertStringContainsString(TreeMissingInterface::class, $exception->getMessage());
        }
    }

    #[Test]
    public function top_level_resolves_do_not_share_stack(): void
    {
        $container = new Container();
        $container->addProviders([
            IsolationTreeC::class => IsolationTreeCProvider::class,
        ]);

        $service = $container->get(IsolationTreeA::class);

        $this->assertInstanceOf(IsolationTreeA::class, $service);
        $this->assertTrue($container->has(IsolationTreeB::class));
        $this->assertTrue($container->has(IsolationTreeC::class));
        $this->assertTrue($container->has(IsolationTreeX::class));
        $this->assertTrue($container->has(IsolationTreeY::class));

        $tree = $container->getDependencyTree();

        $this->assertSame(
            [IsolationTreeX::class],
            array_keys($tree[IsolationTreeX::class]),
        );
        $this->assertSame(
            [IsolationTreeX::class => ['dependency' => IsolationTreeY::class]],
            $tree[IsolationTreeX::class],
        );

        $nested = $container->get(IsolationTreeZ::class);

        $this->assertInstanceOf(IsolationTreeZ::class, $nested);
        $this->assertSame([], $container->getDependencyTree()[IsolationTreeZ::class]);
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

class IsolationTreeA
{
    public function __construct(public readonly IsolationTreeB $dependency) {}
}

class IsolationTreeB
{
    public function __construct(public readonly IsolationTreeC $dependency) {}
}

class IsolationTreeC
{
    public function __construct() {}
}

class IsolationTreeX
{
    public function __construct(public readonly IsolationTreeY $dependency) {}
}

class IsolationTreeY {}

class IsolationTreeZ {}

class IsolationTreeCProvider implements ProviderInterface
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
        $containerService->getInstance(IsolationTreeX::class);

        return new IsolationTreeC();
    }
}

interface StrictContract {}

class StrictBoundImplementation implements StrictContract {}

class StrictRegisteredService {}

class StrictFactoryService {}

interface StrictProviderContract {}

class StrictProviderImplementation implements ProviderInterface, StrictProviderContract
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
        return new StrictProviderImplementation();
    }
}

class StrictTaggedService {}

class StrictUnregisteredService {}

#[\Duyler\DI\Attribute\Finalize]
class StrictThrowableFinalizeService
{
    public int $finalizeCalls = 0;

    public function finalize(): void
    {
        $this->finalizeCalls++;
        throw new RuntimeException('finalizer failed');
    }
}

interface ReasonLeakContract {}

class ReasonLeakImplementation implements ReasonLeakContract {}

class ReasonLeakServiceProvider implements ProviderInterface
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
        return new ReasonLeakImplementation();
    }
}

class StrictTransientProvider implements ProviderInterface
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
        return new StrictTransientUnregistered();
    }
}

interface StrictUnboundContract {}

#[Transient]
class StrictTransientUnregistered {}

interface StaleContract {}

class StaleFirstImplementation implements StaleContract {}

class StaleSecondImplementation implements StaleContract {}

class StaleDependentService
{
    public function __construct(public readonly StaleContract $dependency) {}
}

class StaleFirstProvider implements ProviderInterface
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
        return new StaleFirstImplementation();
    }
}

class StaleSecondProvider implements ProviderInterface
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
        return new StaleSecondImplementation();
    }
}

class TreePlainService {}

#[Tag('cached-tag')]
class CachedAttributedService {}

interface TreeMissingInterface {}

#[Bind(TreeMissingInterface::class)]
class TreeBindMisuseService {}
