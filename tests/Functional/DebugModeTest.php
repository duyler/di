<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\Scope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugModeTest extends TestCase
{
    #[Test]
    public function debug_mode_disabled_by_default(): void
    {
        $container = new Container();

        $this->assertFalse($container->isDebugEnabled());
    }

    #[Test]
    public function can_enable_debug_mode_via_config(): void
    {
        $config = new ContainerConfig();
        $config->withDebugMode(true);

        $container = new Container($config);

        $this->assertTrue($container->isDebugEnabled());
    }

    #[Test]
    public function can_enable_debug_mode_manually(): void
    {
        $container = new Container();
        $container->enableDebug();

        $this->assertTrue($container->isDebugEnabled());
    }

    #[Test]
    public function can_disable_debug_mode_manually(): void
    {
        $container = new Container();
        $container->enableDebug();
        $container->disableDebug();

        $this->assertFalse($container->isDebugEnabled());
    }

    #[Test]
    public function records_service_resolution_when_debug_enabled(): void
    {
        $container = new Container();
        $container->enableDebug();

        $service = $container->get(DebugSimpleService::class);

        $debugInfo = $container->getDebugInfo();
        $resolutions = $debugInfo->getResolutions();

        $this->assertCount(1, $resolutions);
        $this->assertArrayHasKey(DebugSimpleService::class, $resolutions);
        $this->assertEquals(1, $resolutions[DebugSimpleService::class]['count']);
    }

    #[Test]
    public function does_not_record_when_debug_disabled(): void
    {
        $container = new Container();
        $service = $container->get(DebugSimpleService::class);

        $debugInfo = $container->getDebugInfo();
        $resolutions = $debugInfo->getResolutions();

        $this->assertEmpty($resolutions);
    }

    #[Test]
    public function records_nested_dependencies(): void
    {
        $container = new Container();
        $container->enableDebug();

        $service = $container->get(DebugServiceWithDependency::class);

        $debugInfo = $container->getDebugInfo();
        $resolutions = $debugInfo->getResolutions();

        $this->assertGreaterThanOrEqual(1, count($resolutions));
        $this->assertArrayHasKey(DebugServiceWithDependency::class, $resolutions);
    }

    #[Test]
    public function records_time_and_memory_usage(): void
    {
        $container = new Container();
        $container->enableDebug();

        $service = $container->get(DebugSimpleService::class);

        $debugInfo = $container->getDebugInfo();
        $resolutions = $debugInfo->getResolutions();

        $this->assertGreaterThan(0, $resolutions[DebugSimpleService::class]['total_time']);
        $this->assertGreaterThanOrEqual(0, $resolutions[DebugSimpleService::class]['memory']);
    }

    #[Test]
    public function records_multiple_gets_of_same_service(): void
    {
        $container = new Container();
        $container->enableDebug();

        $container->get(DebugSimpleService::class);
        $container->get(DebugSimpleService::class);
        $container->get(DebugSimpleService::class);

        $debugInfo = $container->getDebugInfo();
        $resolutions = $debugInfo->getResolutions();

        $this->assertEquals(1, $resolutions[DebugSimpleService::class]['count']);
    }

    #[Test]
    public function records_transient_service_multiple_times(): void
    {
        $config = new ContainerConfig();
        $config->withDebugMode(true);
        $config->withScope(DebugTransientService::class, Scope::Transient);

        $container = new Container($config);

        $container->get(DebugTransientService::class);
        $container->get(DebugTransientService::class);
        $container->get(DebugTransientService::class);

        $debugInfo = $container->getDebugInfo();
        $resolutions = $debugInfo->getResolutions();

        $this->assertEquals(3, $resolutions[DebugTransientService::class]['count']);
    }

    #[Test]
    public function provides_statistics(): void
    {
        $container = new Container();
        $container->enableDebug();

        $container->get(DebugSimpleService::class);
        $container->get(DebugServiceWithDependency::class);

        $debugInfo = $container->getDebugInfo();
        $stats = $debugInfo->getStatistics();

        $this->assertGreaterThanOrEqual(2, $stats['total_resolutions']);
        $this->assertGreaterThanOrEqual(2, $stats['unique_services']);
        $this->assertGreaterThan(0, $stats['total_time']);
        $this->assertGreaterThan(0, $stats['avg_time']);
    }

    #[Test]
    public function provides_resolution_log(): void
    {
        $container = new Container();
        $container->enableDebug();

        $container->get(DebugSimpleService::class);
        $container->get(DebugServiceWithDependency::class);

        $debugInfo = $container->getDebugInfo();
        $log = $debugInfo->getResolutionLog();

        $this->assertNotEmpty($log);
        $this->assertArrayHasKey('service', $log[0]);
        $this->assertArrayHasKey('time', $log[0]);
        $this->assertArrayHasKey('memory', $log[0]);
        $this->assertArrayHasKey('depth', $log[0]);
    }

    #[Test]
    public function can_get_slowest_services(): void
    {
        $config = new ContainerConfig();
        $config->withDebugMode(true);
        $config->withScope(DebugSlowService::class, Scope::Transient);

        $container = new Container($config);

        $container->get(DebugSimpleService::class);
        $container->get(DebugSlowService::class);

        $debugInfo = $container->getDebugInfo();
        $slowest = $debugInfo->getSlowestServices(10);

        $this->assertNotEmpty($slowest);
        $this->assertGreaterThan(0, $slowest[0]['total_time']);
    }

    #[Test]
    public function can_get_most_resolved_services(): void
    {
        $config = new ContainerConfig();
        $config->withDebugMode(true);
        $config->withScope(DebugTransientService::class, Scope::Transient);

        $container = new Container($config);

        $container->get(DebugTransientService::class);
        $container->get(DebugTransientService::class);
        $container->get(DebugTransientService::class);
        $container->get(DebugSimpleService::class);

        $debugInfo = $container->getDebugInfo();
        $most = $debugInfo->getMostResolvedServices(10);

        $this->assertNotEmpty($most);
        $this->assertEquals(3, $most[0]['count']);
    }

    #[Test]
    public function debug_mode_with_factory(): void
    {
        $container = new Container();
        $container->enableDebug();
        $container->factory(DebugFactoryService::class, fn() => new DebugFactoryService());

        $service = $container->get(DebugFactoryService::class);

        $debugInfo = $container->getDebugInfo();
        $resolutions = $debugInfo->getResolutions();

        $this->assertArrayHasKey(DebugFactoryService::class, $resolutions);
        $this->assertEquals(1, $resolutions[DebugFactoryService::class]['count']);
    }

    #[Test]
    public function debug_mode_with_bindings(): void
    {
        $config = new ContainerConfig();
        $config->withDebugMode(true);
        $config->withBind([DebugInterface::class => DebugImplementation::class]);

        $container = new Container($config);
        $service = $container->get(DebugInterface::class);

        $debugInfo = $container->getDebugInfo();
        $resolutions = $debugInfo->getResolutions();

        $this->assertArrayHasKey(DebugInterface::class, $resolutions);
    }
}

class DebugSimpleService {}

class DebugDependency {}

class DebugServiceWithDependency
{
    public function __construct(public DebugDependency $dependency) {}
}

class DebugTransientService {}

class DebugSlowService
{
    public function __construct()
    {
        usleep(1000);
    }
}

class DebugFactoryService {}

interface DebugInterface {}

class DebugImplementation implements DebugInterface {}
