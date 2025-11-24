<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\ContainerService;
use Duyler\DI\Definition;
use Duyler\DI\Provider\ProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    #[Test]
    public function deep_dependency_chain(): void
    {
        $container = new Container();

        $serviceA = $container->get(ServiceA::class);

        $this->assertInstanceOf(ServiceA::class, $serviceA);
        $this->assertInstanceOf(ServiceB::class, $serviceA->getB());
        $this->assertInstanceOf(ServiceC::class, $serviceA->getB()->getC());
        $this->assertInstanceOf(ServiceD::class, $serviceA->getB()->getC()->getD());
    }

    #[Test]
    public function multiple_dependencies_same_level(): void
    {
        $container = new Container();

        $service = $container->get(ServiceWithMultipleDeps::class);

        $this->assertInstanceOf(ServiceWithMultipleDeps::class, $service);
        $this->assertInstanceOf(ServiceA::class, $service->getA());
        $this->assertInstanceOf(ServiceE::class, $service->getE());
    }

    #[Test]
    public function shared_dependency_returns_same_instance(): void
    {
        $container = new Container();

        $service1 = $container->get(ServiceWithSharedDep::class);
        $service2 = $container->get(AnotherServiceWithSharedDep::class);

        $this->assertSame($service1->getShared(), $service2->getShared());
    }

    #[Test]
    public function complex_scenario_with_config_and_providers(): void
    {
        $config = new ContainerConfig();
        $config
            ->withBind([
                LoggerInterface::class => FileLogger::class,
                RepositoryInterface::class => DatabaseRepository::class,
            ])
            ->withProvider([
                CacheInterface::class => CacheProvider::class,
            ])
            ->withDefinition(new Definition(
                DatabaseRepository::class,
                ['connectionString' => 'sqlite::memory:'],
            ));

        $container = new Container($config);

        $service = $container->get(ComplexService::class);

        $this->assertInstanceOf(ComplexService::class, $service);
        $this->assertInstanceOf(FileLogger::class, $service->getLogger());
        $this->assertInstanceOf(DatabaseRepository::class, $service->getRepository());
        $this->assertInstanceOf(RedisCache::class, $service->getCache());
    }

    #[Test]
    public function provider_with_dependencies_and_finalization(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            ConnectionInterface::class => ConnectionProvider::class,
        ]);

        $container = new Container($config);
        $connection = $container->get(ConnectionInterface::class);

        $this->assertInstanceOf(DatabaseConnection::class, $connection);
        $this->assertTrue($connection->isConnected());

        $container->finalize();

        $this->assertFalse($connection->isConnected());
    }

    #[Test]
    public function multiple_providers_and_bindings(): void
    {
        $config = new ContainerConfig();
        $config
            ->withProvider([
                ServiceInterface1::class => Provider1::class,
                ServiceInterface2::class => Provider2::class,
            ])
            ->withBind([
                ServiceInterface3::class => Service3Implementation::class,
            ]);

        $container = new Container($config);

        $service1 = $container->get(ServiceInterface1::class);
        $service2 = $container->get(ServiceInterface2::class);
        $service3 = $container->get(ServiceInterface3::class);

        $this->assertInstanceOf(Service1Implementation::class, $service1);
        $this->assertInstanceOf(Service2Implementation::class, $service2);
        $this->assertInstanceOf(Service3Implementation::class, $service3);
    }

    #[Test]
    public function dependency_tree_for_complex_service(): void
    {
        $container = new Container();
        $container->get(ComplexDependencyService::class);

        $tree = $container->getDependencyTree();

        $this->assertArrayHasKey(ComplexDependencyService::class, $tree);
        $this->assertIsArray($tree[ComplexDependencyService::class]);
    }

    #[Test]
    public function get_class_map_after_bindings(): void
    {
        $container = new Container();
        $container->bind([
            TestInterface1::class => TestImplementation1::class,
            TestInterface2::class => TestImplementation2::class,
        ]);

        $classMap = $container->getClassMap();

        $this->assertCount(2, $classMap);
        $this->assertEquals(TestImplementation1::class, $classMap[TestInterface1::class]);
        $this->assertEquals(TestImplementation2::class, $classMap[TestInterface2::class]);
    }

    #[Test]
    public function reset_clears_dependencies_and_finalizers(): void
    {
        $container = new Container();

        $service = $container->get(ServiceA::class);
        $container->addFinalizer(ServiceA::class, function () {});

        $this->assertTrue($container->has(ServiceA::class));
        $this->assertNotEmpty($container->getDependencyTree());

        $container->reset();

        $this->assertFalse($container->has(ServiceA::class));
    }
}

class ServiceA
{
    public function __construct(private ServiceB $b) {}
    public function getB(): ServiceB
    {
        return $this->b;
    }
}

class ServiceB
{
    public function __construct(private ServiceC $c) {}
    public function getC(): ServiceC
    {
        return $this->c;
    }
}

class ServiceC
{
    public function __construct(private ServiceD $d) {}
    public function getD(): ServiceD
    {
        return $this->d;
    }
}

class ServiceD {}

class ServiceE {}

class ServiceWithMultipleDeps
{
    public function __construct(
        private ServiceA $a,
        private ServiceE $e,
    ) {}

    public function getA(): ServiceA
    {
        return $this->a;
    }
    public function getE(): ServiceE
    {
        return $this->e;
    }
}

class SharedDependency {}

class ServiceWithSharedDep
{
    public function __construct(private SharedDependency $shared) {}
    public function getShared(): SharedDependency
    {
        return $this->shared;
    }
}

class AnotherServiceWithSharedDep
{
    public function __construct(private SharedDependency $shared) {}
    public function getShared(): SharedDependency
    {
        return $this->shared;
    }
}

interface LoggerInterface {}
class FileLogger implements LoggerInterface {}

interface RepositoryInterface
{
    public function getConnectionString(): string;
}

class DatabaseRepository implements RepositoryInterface
{
    public function __construct(private string $connectionString) {}
    public function getConnectionString(): string
    {
        return $this->connectionString;
    }
}

interface CacheInterface {}
class RedisCache implements CacheInterface {}

class CacheProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }

    public function bind(): array
    {
        return [CacheInterface::class => RedisCache::class];
    }

    public function accept(object $definition): void {}

    public function finalizer(): ?callable
    {
        return null;
    }

    public function factory(ContainerService $containerService): ?object
    {
        return new RedisCache();
    }
}

class ComplexService
{
    public function __construct(
        private LoggerInterface $logger,
        private RepositoryInterface $repository,
        private CacheInterface $cache,
    ) {}

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
    public function getRepository(): RepositoryInterface
    {
        return $this->repository;
    }
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }
}

interface ConnectionInterface
{
    public function isConnected(): bool;
    public function disconnect(): void;
}

class DatabaseConnection implements ConnectionInterface
{
    private bool $connected = false;

    public function connect(): void
    {
        $this->connected = true;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }
}

class ConnectionProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }

    public function bind(): array
    {
        return [ConnectionInterface::class => DatabaseConnection::class];
    }

    public function accept(object $definition): void
    {
        if ($definition instanceof DatabaseConnection) {
            $definition->connect();
        }
    }

    public function finalizer(): ?callable
    {
        return function (DatabaseConnection $connection) {
            $connection->disconnect();
        };
    }

    public function factory(ContainerService $containerService): ?object
    {
        return null;
    }
}

interface ServiceInterface1 {}
class Service1Implementation implements ServiceInterface1 {}

interface ServiceInterface2 {}
class Service2Implementation implements ServiceInterface2 {}

interface ServiceInterface3 {}
class Service3Implementation implements ServiceInterface3 {}

class Provider1 implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }
    public function bind(): array
    {
        return [ServiceInterface1::class => Service1Implementation::class];
    }
    public function accept(object $definition): void {}
    public function finalizer(): ?callable
    {
        return null;
    }
    public function factory(ContainerService $containerService): ?object
    {
        return new Service1Implementation();
    }
}

class Provider2 implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }
    public function bind(): array
    {
        return [ServiceInterface2::class => Service2Implementation::class];
    }
    public function accept(object $definition): void {}
    public function finalizer(): ?callable
    {
        return null;
    }
    public function factory(ContainerService $containerService): ?object
    {
        return new Service2Implementation();
    }
}

class ComplexDependencyService
{
    public function __construct(
        private ServiceA $a,
        private ServiceE $e,
        private SharedDependency $shared,
    ) {}
}

interface TestInterface1 {}

class TestImplementation1 implements TestInterface1 {}

interface TestInterface2 {}

class TestImplementation2 implements TestInterface2 {}
