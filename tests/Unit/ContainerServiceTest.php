<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\Container;
use Duyler\DI\ContainerService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class ContainerServiceTest extends TestCase
{
    #[Test]
    public function get_instance_returns_service_from_container(): void
    {
        $container = new Container();
        $service = new stdClass();
        $container->set($service);

        $containerService = new ContainerService($container);
        $result = $containerService->getInstance(stdClass::class);

        $this->assertSame($service, $result);
    }

    #[Test]
    public function get_instance_creates_service_if_not_exists(): void
    {
        $container = new Container();
        $containerService = new ContainerService($container);

        $result = $containerService->getInstance(SimpleTestService::class);

        $this->assertInstanceOf(SimpleTestService::class, $result);
    }

    #[Test]
    public function get_instance_returns_same_instance_on_multiple_calls(): void
    {
        $container = new Container();
        $containerService = new ContainerService($container);

        $result1 = $containerService->getInstance(SimpleTestService::class);
        $result2 = $containerService->getInstance(SimpleTestService::class);

        $this->assertSame($result1, $result2);
    }
}

class SimpleTestService
{
    public string $value = 'test';
}
