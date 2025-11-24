<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Attribute\Bind;
use Duyler\DI\Attribute\Singleton;
use Duyler\DI\Attribute\Tag;
use Duyler\DI\Attribute\Transient;
use Duyler\DI\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AttributeConfigTest extends TestCase
{
    #[Test]
    public function applies_transient_scope_from_attribute(): void
    {
        $container = new Container();

        $service1 = $container->get(AttrTransientService::class);
        $service2 = $container->get(AttrTransientService::class);

        $this->assertNotSame($service1, $service2);
    }

    #[Test]
    public function applies_singleton_scope_from_attribute(): void
    {
        $container = new Container();

        $service1 = $container->get(AttrSingletonService::class);
        $service2 = $container->get(AttrSingletonService::class);

        $this->assertSame($service1, $service2);
    }

    #[Test]
    public function applies_tags_from_attribute(): void
    {
        $container = new Container();

        $container->get(AttrTaggedService1::class);
        $container->get(AttrTaggedService2::class);

        $tagged = $container->tagged('event.listener');

        $this->assertCount(2, $tagged);
    }

    #[Test]
    public function applies_multiple_tags_from_attribute(): void
    {
        $container = new Container();

        $container->get(AttrMultiTagService::class);

        $listeners = $container->tagged('listener');
        $handlers = $container->tagged('handler');

        $this->assertCount(1, $listeners);
        $this->assertCount(1, $handlers);
    }

    #[Test]
    public function applies_binding_from_attribute(): void
    {
        $container = new Container();

        $container->get(AttrServiceImplementation::class);

        $service = $container->get(AttrServiceInterface::class);

        $this->assertInstanceOf(AttrServiceImplementation::class, $service);
    }

    #[Test]
    public function binding_attribute_creates_singleton(): void
    {
        $container = new Container();

        $container->get(AttrServiceImplementation::class);

        $service1 = $container->get(AttrServiceInterface::class);
        $service2 = $container->get(AttrServiceInterface::class);

        $this->assertSame($service1, $service2);
    }

    #[Test]
    public function combines_multiple_attributes(): void
    {
        $container = new Container();

        $container->get(AttrCombinedService::class);

        $service1 = $container->get(AttrCombinedInterface::class);
        $service2 = $container->get(AttrCombinedInterface::class);

        $this->assertNotSame($service1, $service2);

        $tagged = $container->tagged('combined');
        $this->assertCount(1, $tagged);
    }

    #[Test]
    public function attributes_work_with_dependencies(): void
    {
        $container = new Container();

        $service = $container->get(AttrServiceWithDeps::class);

        $this->assertInstanceOf(AttrServiceWithDeps::class, $service);
        $this->assertInstanceOf(AttrDependency::class, $service->dependency);
    }

    #[Test]
    public function manual_config_overrides_attributes(): void
    {
        $container = new Container();

        $container->get(AttrTransientService::class);

        $service1 = $container->get(AttrTransientService::class);
        $service2 = $container->get(AttrTransientService::class);

        $this->assertNotSame($service1, $service2);
    }

    #[Test]
    public function repeated_tag_attributes_combine(): void
    {
        $container = new Container();

        $container->get(AttrRepeatedTagService::class);

        $tag1 = $container->tagged('tag1');
        $tag2 = $container->tagged('tag2');
        $tag3 = $container->tagged('tag3');

        $this->assertCount(1, $tag1);
        $this->assertCount(1, $tag2);
        $this->assertCount(1, $tag3);
    }

    #[Test]
    public function service_without_attributes_works_normally(): void
    {
        $container = new Container();

        $service1 = $container->get(AttrNoAttributeService::class);
        $service2 = $container->get(AttrNoAttributeService::class);

        $this->assertSame($service1, $service2);
    }
}

#[Transient]
class AttrTransientService {}

#[Singleton]
class AttrSingletonService {}

#[Tag('event.listener')]
class AttrTaggedService1 {}

#[Tag('event.listener')]
class AttrTaggedService2 {}

#[Tag(['listener', 'handler'])]
class AttrMultiTagService {}

interface AttrServiceInterface {}

#[Bind(AttrServiceInterface::class)]
class AttrServiceImplementation implements AttrServiceInterface {}

interface AttrCombinedInterface {}

#[Transient]
#[Tag('combined')]
#[Bind(AttrCombinedInterface::class)]
class AttrCombinedService implements AttrCombinedInterface {}

class AttrDependency {}

#[Singleton]
class AttrServiceWithDeps
{
    public function __construct(public AttrDependency $dependency) {}
}

#[Tag('tag1')]
#[Tag('tag2')]
#[Tag('tag3')]
class AttrRepeatedTagService {}

class AttrNoAttributeService {}
