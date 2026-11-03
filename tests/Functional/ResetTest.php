<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Functional;

use Duyler\DI\Attribute\Finalize;
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\ContainerService;
use Duyler\DI\Event\ContainerEvents;
use Duyler\DI\Exception\FinalizeNotImplementException;
use Duyler\DI\Provider\ProviderInterface;
use Duyler\DI\Scope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResetTest extends TestCase
{
    #[Test]
    public function finalize_resets_attribute_service_state(): void
    {
        $container = new Container();
        $obj = $container->get(WithReset::class);
        $obj->setName('test');
        $this->assertEquals('test', $obj->getName());
        $obj->setName('test2');
        $this->assertEquals('test2', $obj->getName());
        $container->finalize();
        $this->assertNull($obj->getName());
    }

    #[Test]
    public function finalize_throws_when_finalizer_method_missing(): void
    {
        $container = new Container();
        $container->get(WithoutReset::class);

        $this->expectException(FinalizeNotImplementException::class);
        $container->finalize();
    }

    #[Test]
    public function decorator_added_after_reset_is_applied_on_next_creation(): void
    {
        $container = new Container();
        $container->get(ResetDecoratedService::class);
        $container->reset();

        $container->decorate(
            ResetDecoratedService::class,
            static fn(object $service): object => new ResetDecoratorWrapper($service),
        );

        $service = $container->get(ResetDecoratedService::class);

        $this->assertInstanceOf(ResetDecoratorWrapper::class, $service);
    }

    #[Test]
    public function re_on_after_reset_does_not_duplicate_listeners(): void
    {
        $container = new Container();
        $calls = 0;
        $listener = static function () use (&$calls): void {
            $calls++;
        };

        $container->on(ContainerEvents::AFTER_RESOLVE, $listener);
        $container->get(ResetSimpleService::class);
        $container->reset();
        $container->on(ContainerEvents::AFTER_RESOLVE, $listener);
        $container->get(ResetSimpleService::class);

        $this->assertSame(4, $calls);
    }

    #[Test]
    public function debug_statistics_start_from_zero_after_reset_with_mode_kept(): void
    {
        $container = new Container();
        $container->enableDebug();
        $container->get(ResetSimpleService::class);
        $container->reset();

        $this->assertTrue($container->isDebugEnabled());
        $this->assertSame([], $container->getDebugInfo()->getResolutions());
        $this->assertSame(0, $container->getDebugInfo()->getStatistics()['total_resolutions']);

        $container->get(ResetSimpleService::class);

        $this->assertSame(1, $container->getDebugInfo()->getStatistics()['total_resolutions']);
    }

    #[Test]
    public function configuration_survives_reset(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            ResetProvidedContract::class => ResetProvidedServiceProvider::class,
        ]);
        $config->withBind([
            ResetBoundContract::class => ResetBoundImplementation::class,
        ]);
        $config->withScope(ResetScopedService::class, Scope::Transient);

        $container = new Container($config);
        $providedBefore = $container->get(ResetProvidedContract::class);
        $container->reset();

        $providedAfter = $container->get(ResetProvidedContract::class);
        $this->assertInstanceOf(ResetProvidedImplementation::class, $providedAfter);
        $this->assertNotSame($providedBefore, $providedAfter);

        $bound = $container->get(ResetBoundContract::class);
        $this->assertInstanceOf(ResetBoundImplementation::class, $bound);

        $scopedFirst = $container->get(ResetScopedService::class);
        $scopedSecond = $container->get(ResetScopedService::class);
        $this->assertNotSame($scopedFirst, $scopedSecond);
    }
}

#[Finalize]
class WithReset
{
    private ?string $name = null;

    public function finalize(): void
    {
        $this->name = null;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}

#[Finalize]
class WithoutReset {}

class ResetSimpleService {}

class ResetDecoratedService {}

class ResetDecoratorWrapper
{
    public function __construct(public readonly object $inner) {}
}

interface ResetProvidedContract {}

class ResetProvidedImplementation implements ResetProvidedContract
{
    public function __construct() {}
}

class ResetProvidedServiceProvider implements ProviderInterface
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
        return new ResetProvidedImplementation();
    }
}

interface ResetBoundContract {}

class ResetBoundImplementation implements ResetBoundContract {}

class ResetScopedService {}
