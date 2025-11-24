<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\Container;
use Duyler\DI\ContainerService;
use Duyler\DI\Provider\AbstractProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class AbstractProviderTest extends TestCase
{
    #[Test]
    public function default_get_arguments_returns_empty_array(): void
    {
        $provider = new class extends AbstractProvider {};
        $containerService = new ContainerService(new Container());

        $result = $provider->getArguments($containerService);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function default_bind_returns_empty_array(): void
    {
        $provider = new class extends AbstractProvider {};

        $result = $provider->bind();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function default_accept_does_nothing(): void
    {
        $provider = new class extends AbstractProvider {};
        $object = new stdClass();

        $provider->accept($object);

        $this->assertTrue(true);
    }

    #[Test]
    public function default_finalizer_returns_null(): void
    {
        $provider = new class extends AbstractProvider {};

        $result = $provider->finalizer();

        $this->assertNull($result);
    }

    #[Test]
    public function default_factory_returns_null(): void
    {
        $provider = new class extends AbstractProvider {};
        $containerService = new ContainerService(new Container());

        $result = $provider->factory($containerService);

        $this->assertNull($result);
    }

    #[Test]
    public function can_override_methods(): void
    {
        $provider = new class extends AbstractProvider {
            public function getArguments(ContainerService $containerService): array
            {
                return ['test' => 'value'];
            }

            public function bind(): array
            {
                return ['Interface' => 'Implementation'];
            }
        };

        $containerService = new ContainerService(new Container());

        $this->assertEquals(['test' => 'value'], $provider->getArguments($containerService));
        $this->assertEquals(['Interface' => 'Implementation'], $provider->bind());
    }
}
