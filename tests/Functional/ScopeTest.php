<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\Scope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ScopeTest extends TestCase
{
    #[Test]
    public function singleton_scope_returns_same_instance(): void
    {
        $config = new ContainerConfig();
        $config->withScope(SingletonService::class, Scope::Singleton);

        $container = new Container($config);

        $instance1 = $container->get(SingletonService::class);
        $instance2 = $container->get(SingletonService::class);

        $this->assertSame($instance1, $instance2);
    }

    #[Test]
    public function transient_scope_returns_different_instances(): void
    {
        $config = new ContainerConfig();
        $config->withScope(TransientService::class, Scope::Transient);

        $container = new Container($config);

        $instance1 = $container->get(TransientService::class);
        $instance2 = $container->get(TransientService::class);

        $this->assertNotSame($instance1, $instance2);
        $this->assertInstanceOf(TransientService::class, $instance1);
        $this->assertInstanceOf(TransientService::class, $instance2);
    }

    #[Test]
    public function default_scope_is_singleton(): void
    {
        $container = new Container();

        $instance1 = $container->get(DefaultScopeService::class);
        $instance2 = $container->get(DefaultScopeService::class);

        $this->assertSame($instance1, $instance2);
    }

    #[Test]
    public function transient_with_dependencies(): void
    {
        $config = new ContainerConfig();
        $config->withScope(TransientWithDependency::class, Scope::Transient);

        $container = new Container($config);

        $instance1 = $container->get(TransientWithDependency::class);
        $instance2 = $container->get(TransientWithDependency::class);

        $this->assertNotSame($instance1, $instance2);
        $this->assertSame($instance1->getDependency(), $instance2->getDependency());
    }

    #[Test]
    public function mixed_scopes(): void
    {
        $config = new ContainerConfig();
        $config->withScope(MixedTransientService::class, Scope::Transient);
        $config->withScope(MixedSingletonService::class, Scope::Singleton);

        $container = new Container($config);

        $transient1 = $container->get(MixedTransientService::class);
        $transient2 = $container->get(MixedTransientService::class);

        $singleton1 = $container->get(MixedSingletonService::class);
        $singleton2 = $container->get(MixedSingletonService::class);

        $this->assertNotSame($transient1, $transient2);
        $this->assertSame($singleton1, $singleton2);
    }
}

class SingletonService
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid();
    }
}

class TransientService
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid();
    }
}

class DefaultScopeService
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid();
    }
}

class ScopeDependency
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid();
    }
}

class TransientWithDependency
{
    public function __construct(private ScopeDependency $dependency) {}

    public function getDependency(): ScopeDependency
    {
        return $this->dependency;
    }
}

class MixedTransientService
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid();
    }
}

class MixedSingletonService
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid();
    }
}
