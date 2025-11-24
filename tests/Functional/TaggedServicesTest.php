<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TaggedServicesTest extends TestCase
{
    #[Test]
    public function tag_single_service(): void
    {
        $container = new Container();
        $container->tag(TaggedService1::class, 'test.tag');

        $services = $container->tagged('test.tag');

        $this->assertCount(1, $services);
        $this->assertInstanceOf(TaggedService1::class, $services[0]);
    }

    #[Test]
    public function tag_multiple_services_with_same_tag(): void
    {
        $container = new Container();
        $container->tag(TaggedService1::class, 'test.tag');
        $container->tag(TaggedService2::class, 'test.tag');

        $services = $container->tagged('test.tag');

        $this->assertCount(2, $services);
        $this->assertInstanceOf(TaggedService1::class, $services[0]);
        $this->assertInstanceOf(TaggedService2::class, $services[1]);
    }

    #[Test]
    public function tag_service_with_multiple_tags(): void
    {
        $container = new Container();
        $container->tag(TaggedService1::class, ['tag1', 'tag2']);

        $servicesTag1 = $container->tagged('tag1');
        $servicesTag2 = $container->tagged('tag2');

        $this->assertCount(1, $servicesTag1);
        $this->assertCount(1, $servicesTag2);
        $this->assertSame($servicesTag1[0], $servicesTag2[0]);
    }

    #[Test]
    public function get_empty_array_for_non_existent_tag(): void
    {
        $container = new Container();

        $services = $container->tagged('non.existent');

        $this->assertEmpty($services);
    }

    #[Test]
    public function tag_via_config(): void
    {
        $config = new ContainerConfig();
        $config->withTag(TaggedService1::class, 'config.tag');
        $config->withTag(TaggedService2::class, 'config.tag');

        $container = new Container($config);
        $services = $container->tagged('config.tag');

        $this->assertCount(2, $services);
        $this->assertInstanceOf(TaggedService1::class, $services[0]);
        $this->assertInstanceOf(TaggedService2::class, $services[1]);
    }

    #[Test]
    public function tagged_services_are_singletons_by_default(): void
    {
        $container = new Container();
        $container->tag(TaggedService1::class, 'singleton.tag');

        $services1 = $container->tagged('singleton.tag');
        $services2 = $container->tagged('singleton.tag');

        $this->assertSame($services1[0], $services2[0]);
    }

    #[Test]
    public function tag_service_with_dependencies(): void
    {
        $container = new Container();
        $container->tag(TaggedServiceWithDependency::class, 'complex.tag');

        $services = $container->tagged('complex.tag');

        $this->assertCount(1, $services);
        $this->assertInstanceOf(TaggedServiceWithDependency::class, $services[0]);
        $this->assertInstanceOf(TaggedSimpleDependency::class, $services[0]->dependency);
    }

    #[Test]
    public function mix_config_and_runtime_tagging(): void
    {
        $config = new ContainerConfig();
        $config->withTag(TaggedService1::class, 'mixed.tag');

        $container = new Container($config);
        $container->tag(TaggedService2::class, 'mixed.tag');

        $services = $container->tagged('mixed.tag');

        $this->assertCount(2, $services);
    }

    #[Test]
    public function tag_same_service_multiple_times_does_not_duplicate(): void
    {
        $container = new Container();
        $container->tag(TaggedService1::class, 'test.tag');
        $container->tag(TaggedService1::class, 'test.tag');

        $services = $container->tagged('test.tag');

        $this->assertCount(1, $services);
    }

    #[Test]
    public function reset_clears_all_tags(): void
    {
        $container = new Container();
        $container->tag(TaggedService1::class, 'tag1');
        $container->tag(TaggedService2::class, 'tag2');

        $this->assertCount(1, $container->tagged('tag1'));
        $this->assertCount(1, $container->tagged('tag2'));

        $container->reset();

        $this->assertEmpty($container->tagged('tag1'));
        $this->assertEmpty($container->tagged('tag2'));
    }

    #[Test]
    public function tags_persist_across_multiple_get_calls(): void
    {
        $container = new Container();
        $container->tag(TaggedService1::class, 'persistent.tag');

        $services1 = $container->tagged('persistent.tag');
        $services2 = $container->tagged('persistent.tag');
        $services3 = $container->tagged('persistent.tag');

        $this->assertCount(1, $services1);
        $this->assertCount(1, $services2);
        $this->assertCount(1, $services3);

        $this->assertSame($services1[0], $services2[0]);
        $this->assertSame($services2[0], $services3[0]);
    }

    #[Test]
    public function tag_with_binding(): void
    {
        $config = new ContainerConfig();
        $config->withBind([TaggedInterface::class => TaggedImplementation::class]);

        $container = new Container($config);
        $container->tag(TaggedInterface::class, 'interface.tag');

        $services = $container->tagged('interface.tag');

        $this->assertCount(1, $services);
        $this->assertInstanceOf(TaggedImplementation::class, $services[0]);
    }
}

class TaggedService1 {}

class TaggedService2 {}

class TaggedSimpleDependency {}

class TaggedServiceWithDependency
{
    public function __construct(public TaggedSimpleDependency $dependency) {}
}

interface TaggedInterface {}

class TaggedImplementation implements TaggedInterface {}
