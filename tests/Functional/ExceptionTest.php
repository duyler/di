<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\Exception\CircularReferenceException;
use Duyler\DI\Exception\InterfaceBindNotFoundException;
use Duyler\DI\Exception\InterfaceMapNotFoundException;
use Duyler\DI\Exception\NotFoundException;
use Duyler\DI\Exception\ResolveDependenciesTreeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    public function not_found_exception_for_non_existent_class(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get('NonExistentClass');
    }

    #[Test]
    public function interface_bind_not_found_exception(): void
    {
        $this->expectException(InterfaceBindNotFoundException::class);
        $this->container->get(UnboundInterface::class);
    }

    #[Test]
    public function interface_map_not_found_exception_in_dependency(): void
    {
        $this->expectException(InterfaceMapNotFoundException::class);
        $this->container->get(ServiceWithUnboundInterface::class);
    }

    #[Test]
    public function circular_reference_exception(): void
    {
        $this->expectException(CircularReferenceException::class);
        $this->container->get(CircularA::class);
    }

    #[Test]
    public function resolve_dependencies_tree_exception_for_class_with_missing_dependencies(): void
    {
        $this->expectException(ResolveDependenciesTreeException::class);
        $this->container->get(ServiceWithMissingDependency::class);
    }
}

interface UnboundInterface {}

interface AnotherUnboundInterface {}

class ServiceWithUnboundInterface
{
    public function __construct(AnotherUnboundInterface $dependency) {}
}

class CircularA
{
    public function __construct(CircularB $b) {}
}

class CircularB
{
    public function __construct(CircularA $a) {}
}

class ServiceWithMissingDependency
{
    public function __construct(string $requiredString) {}
}
