<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CompileTest extends TestCase
{
    #[Test]
    public function compile_returns_empty_array_for_valid_bindings(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            CompileInterface1::class => CompileImplementation1::class,
            CompileInterface2::class => CompileImplementation2::class,
        ]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertEmpty($errors);
    }

    #[Test]
    public function compile_detects_missing_dependencies(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            CompileInterface1::class => CompileWithMissingDependency::class,
        ]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Failed to resolve', $errors[0]);
        $this->assertStringContainsString(CompileInterface1::class, $errors[0]);
        $this->assertStringContainsString(CompileWithMissingDependency::class, $errors[0]);
    }

    #[Test]
    public function compile_detects_circular_dependencies(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            CompileCircularA::class => CompileCircularImplA::class,
            CompileCircularB::class => CompileCircularImplB::class,
        ]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
    }

    #[Test]
    public function compile_works_with_complex_dependencies(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            CompileInterface1::class => CompileImplementation1::class,
            CompileInterface2::class => CompileImplementation2::class,
        ]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertEmpty($errors);
    }

    #[Test]
    public function compile_validates_multiple_bindings(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            CompileInterface1::class => CompileWithMissingDependency::class,
            CompileInterface2::class => CompileImplementation2::class,
        ]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertCount(1, $errors);
        $this->assertStringContainsString(CompileInterface1::class, $errors[0]);
    }
}

interface CompileInterface1 {}

class CompileImplementation1 implements CompileInterface1 {}

interface CompileInterface2 {}

class CompileImplementation2 implements CompileInterface2 {}

class CompileWithMissingDependency implements CompileInterface1
{
    public function __construct(CompileUnboundInterface $dep) {}
}

interface CompileUnboundInterface {}

interface CompileCircularA {}

class CompileCircularImplA implements CompileCircularA
{
    public function __construct(CompileCircularB $b) {}
}

interface CompileCircularB {}

class CompileCircularImplB implements CompileCircularB
{
    public function __construct(CompileCircularA $a) {}
}

