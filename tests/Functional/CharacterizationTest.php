<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Functional;

use Duyler\DI\Attribute\Transient;
use Duyler\DI\Container;
use Duyler\DI\ContainerService;
use Duyler\DI\Event\ContainerEvent;
use Duyler\DI\Event\ContainerEvents;
use Duyler\DI\Exception\CircularReferenceException;
use Duyler\DI\Exception\InterfaceBindNotFoundException;
use Duyler\DI\Exception\InterfaceMapNotFoundException;
use Duyler\DI\Exception\InvalidBindingException;
use Duyler\DI\Exception\ResolveDependenciesTreeException;
use Duyler\DI\Exception\ServiceNotCreatedException;
use Duyler\DI\Exception\UnsupportedTypeException;
use Duyler\DI\Provider\ProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use TypeError;

final class CharacterizationTest extends TestCase
{
    #[Test]
    public function union_parameter_throws_unsupported_type_exception(): void
    {
        $container = new Container();

        try {
            $container->get(CharacterizationUnionTarget::class);
            self::fail('UnsupportedTypeException expected');
        } catch (UnsupportedTypeException $exception) {
            self::assertInstanceOf(ContainerExceptionInterface::class, $exception);
            self::assertNull($exception->getPrevious());
            self::assertSame(
                'Parameter "dep" of "' . CharacterizationUnionTarget::class . '"'
                . ' has unsupported type "' . CharacterizationUnionDependencyA::class . '|' . CharacterizationUnionDependencyB::class . '":'
                . ' union and intersection types are not supported for autowired constructor parameters',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function provider_factory_failure_throws_service_not_created_exception(): void
    {
        $container = new Container();
        $container->addProviders([
            CharacterizationFactoryContract::class => CharacterizationThrowingProvider::class,
        ]);

        try {
            $container->get(CharacterizationFactoryContract::class);
            self::fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            self::assertInstanceOf(ContainerExceptionInterface::class, $exception);
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            self::assertStringContainsString(
                'Failed to create service "' . CharacterizationFactoryContract::class . '"',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('provider factory failed', $exception->getMessage());
        }

        $typeContainer = new Container();
        $typeContainer->addProviders([
            CharacterizationFactoryContract::class => CharacterizationTypeMismatchProvider::class,
        ]);

        try {
            $typeContainer->get(CharacterizationFactoryContract::class);
            self::fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            self::assertInstanceOf(TypeError::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function unresolvable_id_throws_not_found_interface(): void
    {
        $container = new Container();

        try {
            $container->get('');
            self::fail('NotFoundExceptionInterface expected');
        } catch (NotFoundExceptionInterface $exception) {
            self::assertNull($exception->getPrevious());
            self::assertStringStartsWith('Service "" not found in the container.', $exception->getMessage());
            self::assertStringNotContainsString('Did you mean', $exception->getMessage());
        }

        try {
            $container->get('NoSuchClass');
            self::fail('NotFoundExceptionInterface expected');
        } catch (NotFoundExceptionInterface $exception) {
            self::assertNull($exception->getPrevious());
            self::assertStringStartsWith('Service "NoSuchClass" not found in the container.', $exception->getMessage());
        }
    }

    #[Test]
    public function listener_failure_throws_service_not_created_exception_with_previous(): void
    {
        $beforeContainer = new Container();
        $beforeContainer->on(ContainerEvents::BEFORE_RESOLVE, static function (): void {
            throw new RuntimeException('listener failed before resolve');
        });

        try {
            $beforeContainer->get(CharacterizationSimpleService::class);
            self::fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            self::assertStringNotContainsString('listener failed before resolve', $exception->getMessage());
        }

        $afterContainer = new Container();
        $afterContainer->on(ContainerEvents::AFTER_RESOLVE, static function (): void {
            throw new RuntimeException('listener failed after resolve');
        });

        try {
            $afterContainer->get(CharacterizationSimpleService::class);
            self::fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            self::assertStringNotContainsString('listener failed after resolve', $exception->getMessage());
            self::assertTrue($afterContainer->has(CharacterizationSimpleService::class));
        }
    }

    #[Test]
    public function reset_clears_decorators_listeners_and_debug_statistics(): void
    {
        $container = new Container();
        $container->enableDebug();
        $listenerCalls = 0;
        $container->decorate(
            CharacterizationDecoratedService::class,
            static fn(object $service): object => new CharacterizationDecoratorWrapper($service, 'decorated'),
        );
        $container->on(ContainerEvents::AFTER_RESOLVE, static function () use (&$listenerCalls): void {
            $listenerCalls++;
        });

        $first = $container->get(CharacterizationDecoratedService::class);
        $container->reset();

        self::assertTrue($container->isDebugEnabled());
        self::assertSame([], $container->getDebugInfo()->getResolutions());
        self::assertSame(0, $container->getDebugInfo()->getStatistics()['total_resolutions']);

        $second = $container->get(CharacterizationDecoratedService::class);

        self::assertInstanceOf(CharacterizationDecoratorWrapper::class, $first);
        self::assertInstanceOf(CharacterizationDecoratedService::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame(2, $listenerCalls);
        self::assertSame(1, $container->getDebugInfo()->getStatistics()['total_resolutions']);
    }

    #[Test]
    public function provider_cycle_through_get_instance_throws_circular_reference_exception(): void
    {
        $container = new Container();
        $container->addProviders([CharacterizationCycleService::class => CharacterizationCycleProvider::class]);

        try {
            $container->get(CharacterizationCycleService::class);
            self::fail('CircularReferenceException expected');
        } catch (CircularReferenceException $exception) {
            self::assertInstanceOf(ContainerExceptionInterface::class, $exception);
            self::assertStringContainsString(CharacterizationCycleService::class, $exception->getMessage());
        }
    }

    #[Test]
    public function cached_resolve_dispatches_after_resolve_with_float_time(): void
    {
        $container = new Container();
        $time = 'not dispatched';

        $container->get(CharacterizationSimpleService::class);
        $container->on(ContainerEvents::AFTER_RESOLVE, static function (ContainerEvent $event) use (&$time): void {
            $time = $event->time;
        });

        $cached = $container->get(CharacterizationSimpleService::class);

        self::assertInstanceOf(CharacterizationSimpleService::class, $cached);
        self::assertIsFloat($time);
        self::assertGreaterThanOrEqual(0.0, $time);
    }

    #[Test]
    public function incompatible_provider_result_throws_service_not_created_exception(): void
    {
        $container = new Container();
        $container->addProviders([
            CharacterizationProvidedService::class => CharacterizationIncompatibleProvider::class,
            CharacterizationProvidedContract::class => CharacterizationIncompatibleProvider::class,
        ]);

        try {
            $container->get(CharacterizationProvidedService::class);
            self::fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            self::assertInstanceOf(ContainerExceptionInterface::class, $exception);
            self::assertStringContainsString(
                'Provider returned "' . CharacterizationForeignService::class . '"'
                . ' which is not an instance of "' . CharacterizationProvidedService::class . '"',
                $exception->getMessage(),
            );
        }

        try {
            $container->get(CharacterizationProvidedContract::class);
            self::fail('InterfaceBindNotFoundException expected');
        } catch (InterfaceBindNotFoundException $exception) {
            self::assertNull($exception->getPrevious());
            self::assertStringContainsString(CharacterizationProvidedContract::class, $exception->getMessage());
        }
    }

    #[Test]
    public function lazy_bind_fails_with_invalid_binding_exception_on_first_resolve(): void
    {
        $container = new Container();
        $container->bind([CharacterizationBoundContract::class => 'CharacterizationMissingImplementation']);

        try {
            $container->get(CharacterizationBoundContract::class);
            self::fail('InvalidBindingException expected');
        } catch (InvalidBindingException $exception) {
            self::assertInstanceOf(ContainerExceptionInterface::class, $exception);
            self::assertStringContainsString('CharacterizationMissingImplementation', $exception->getMessage());
        }
    }

    #[Test]
    public function unbound_interface_keeps_interface_bind_not_found_exception(): void
    {
        $container = new Container();

        try {
            $container->get(CharacterizationBoundContract::class);
            self::fail('InterfaceBindNotFoundException expected');
        } catch (InterfaceBindNotFoundException $exception) {
            self::assertNull($exception->getPrevious());
            self::assertStringContainsString(CharacterizationBoundContract::class, $exception->getMessage());
        }
    }

    #[Test]
    public function failed_service_is_not_cached_but_valid_dependencies_are(): void
    {
        $container = new Container();

        try {
            $container->get(CharacterizationFailingService::class);
            self::fail('ResolveDependenciesTreeException expected');
        } catch (ResolveDependenciesTreeException $exception) {
            self::assertFalse($container->has(CharacterizationFailingService::class));
            self::assertTrue($container->has(CharacterizationSimpleService::class));
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function provider_without_bind_keeps_classmap_null_free(): void
    {
        $container = new Container();
        $container->addProviders([CharacterizationMappedOwner::class => CharacterizationMappedOwnerProvider::class]);

        try {
            $container->get(CharacterizationMappedOwner::class);
            self::fail('InterfaceMapNotFoundException expected');
        } catch (InterfaceMapNotFoundException $exception) {
            self::assertNull($exception->getPrevious());
            self::assertStringContainsString(CharacterizationMappedContract::class, $exception->getMessage());
            self::assertStringContainsString(CharacterizationMappedOwner::class, $exception->getMessage());
        }

        $classMap = $container->getClassMap();
        self::assertArrayNotHasKey(CharacterizationMappedContract::class, $classMap);
        self::assertNotContains(null, $classMap);
    }

    #[Test]
    public function transient_ignores_factory_and_set_decorating_each_get(): void
    {
        $container = new Container();
        $factoryCalled = false;
        $container->factory(CharacterizationTransientService::class, static function () use (&$factoryCalled): object {
            $factoryCalled = true;

            return new CharacterizationTransientService();
        });
        $preInstalled = new CharacterizationTransientService();
        $container->set($preInstalled);
        $container->decorate(
            CharacterizationTransientService::class,
            static fn(object $service): object => new CharacterizationDecoratorWrapper($service, 'transient'),
        );

        $first = $container->get(CharacterizationTransientService::class);
        $second = $container->get(CharacterizationTransientService::class);

        self::assertFalse($factoryCalled);
        self::assertNotSame($preInstalled, $first);
        self::assertNotSame($first, $second);
        self::assertInstanceOf(CharacterizationDecoratorWrapper::class, $first);
        self::assertInstanceOf(CharacterizationDecoratorWrapper::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame('transient', $first->label);
        self::assertSame('transient', $second->label);
    }

    #[Test]
    public function late_decorator_requires_reset(): void
    {
        $container = new Container();
        $container->decorate(
            CharacterizationDecoratedService::class,
            static fn(object $service): object => new CharacterizationDecoratorWrapper($service, 'first'),
        );

        $first = $container->get(CharacterizationDecoratedService::class);

        $container->decorate(
            CharacterizationDecoratedService::class,
            static fn(object $service): object => new CharacterizationDecoratorWrapper($service, 'second'),
        );

        $second = $container->get(CharacterizationDecoratedService::class);

        self::assertInstanceOf(CharacterizationDecoratorWrapper::class, $first);
        self::assertSame($first, $second);
        self::assertSame('first', $second->label);

        $container->reset();
        $container->decorate(
            CharacterizationDecoratedService::class,
            static fn(object $service): object => new CharacterizationDecoratorWrapper($service, 'second'),
        );

        $third = $container->get(CharacterizationDecoratedService::class);

        self::assertNotSame($second, $third);
        self::assertSame('second', $third->label);
    }

    #[Test]
    public function scalar_parameter_without_default_throws_service_not_created(): void
    {
        $container = new Container();

        try {
            $container->get(CharacterizationScalarService::class);
            self::fail('ServiceNotCreatedException expected');
        } catch (ServiceNotCreatedException $exception) {
            self::assertStringContainsString(
                'Parameter "name" of "' . CharacterizationScalarService::class . '" cannot be autowired',
                $exception->getMessage(),
            );
        }
    }
}

class CharacterizationSimpleService {}

class CharacterizationUnionDependencyA {}

class CharacterizationUnionDependencyB {}

class CharacterizationUnionTarget
{
    public function __construct(
        private readonly CharacterizationUnionDependencyA|CharacterizationUnionDependencyB $dep,
    ) {}
}

interface CharacterizationFactoryContract {}

class CharacterizationThrowingProvider implements ProviderInterface
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
        throw new RuntimeException('provider factory failed');
    }
}

class CharacterizationTypeMismatchProvider implements ProviderInterface
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
        throw new TypeError('factory received incompatible argument type');
    }
}

class CharacterizationFailingService
{
    public function __construct(private readonly CharacterizationSimpleService $dep)
    {
        throw new RuntimeException('creation failed after dependency injection');
    }
}

class CharacterizationDecoratedService {}

class CharacterizationDecoratorWrapper
{
    public function __construct(
        public readonly object $inner,
        public readonly string $label = '',
    ) {}
}

class CharacterizationCycleService
{
    public function __construct() {}
}

class CharacterizationCycleProvider implements ProviderInterface
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
        return $containerService->getInstance(CharacterizationCycleService::class);
    }
}

class CharacterizationForeignService {}

class CharacterizationProvidedService
{
    public function __construct() {}
}

interface CharacterizationProvidedContract {}

class CharacterizationIncompatibleProvider implements ProviderInterface
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
        return new CharacterizationForeignService();
    }
}

interface CharacterizationBoundContract {}

interface CharacterizationMappedContract {}

class CharacterizationMappedOwner
{
    public function __construct(
        private readonly CharacterizationMappedContract $dep,
    ) {}
}

class CharacterizationMappedOwnerProvider implements ProviderInterface
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
        return null;
    }
}

#[Transient]
class CharacterizationTransientService {}

class CharacterizationScalarService
{
    public function __construct(
        private readonly string $name,
    ) {}
}
