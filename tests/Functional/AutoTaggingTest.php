<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AutoTaggingTest extends TestCase
{
    #[Test]
    public function auto_tags_by_interface(): void
    {
        $config = new ContainerConfig();
        $config->withAutoTagging(true);

        $container = new Container($config);

        $container->get(AutoTagImpl1::class);
        $container->get(AutoTagImpl2::class);

        $tagged = $container->tagged(AutoTagInterface::class);

        $this->assertCount(2, $tagged);
    }

    #[Test]
    public function auto_tags_by_multiple_interfaces(): void
    {
        $config = new ContainerConfig();
        $config->withAutoTagging(true);

        $container = new Container($config);

        $container->get(AutoTagMultiImpl::class);

        $tagged1 = $container->tagged(AutoTagInterface1::class);
        $tagged2 = $container->tagged(AutoTagInterface2::class);

        $this->assertCount(1, $tagged1);
        $this->assertCount(1, $tagged2);
    }

    #[Test]
    public function auto_tagging_disabled_by_default(): void
    {
        $container = new Container();

        $container->get(AutoTagImpl1::class);
        $container->get(AutoTagImpl2::class);

        $tagged = $container->tagged(AutoTagInterface::class);

        $this->assertEmpty($tagged);
    }

    #[Test]
    public function auto_tagging_works_with_manual_tags(): void
    {
        $config = new ContainerConfig();
        $config->withAutoTagging(true);
        $config->withTag(AutoTagImpl1::class, 'manual');

        $container = new Container($config);

        $container->get(AutoTagImpl1::class);

        $autoTagged = $container->tagged(AutoTagInterface::class);
        $manualTagged = $container->tagged('manual');

        $this->assertCount(1, $autoTagged);
        $this->assertCount(1, $manualTagged);
    }

    #[Test]
    public function auto_tagging_does_not_tag_classes_without_interfaces(): void
    {
        $config = new ContainerConfig();
        $config->withAutoTagging(true);

        $container = new Container($config);

        $container->get(AutoTagNoInterface::class);

        $this->assertTrue(true);
    }

    #[Test]
    public function auto_tagging_applies_to_all_implemented_interfaces(): void
    {
        $config = new ContainerConfig();
        $config->withAutoTagging(true);

        $container = new Container($config);

        $container->get(AutoTagComplexImpl::class);

        $tagged1 = $container->tagged(AutoTagInterface1::class);
        $tagged2 = $container->tagged(AutoTagInterface2::class);
        $tagged3 = $container->tagged(AutoTagInterface3::class);

        $this->assertCount(1, $tagged1);
        $this->assertCount(1, $tagged2);
        $this->assertCount(1, $tagged3);
    }

    #[Test]
    public function can_retrieve_all_implementations_of_interface(): void
    {
        $config = new ContainerConfig();
        $config->withAutoTagging(true);

        $container = new Container();
        $container->get(AutoTagImpl1::class);
        $container->get(AutoTagImpl2::class);
        $container->get(AutoTagNoInterface::class);

        $config->withAutoTagging(true);
        $container2 = new Container($config);

        $container2->get(AutoTagImpl1::class);
        $container2->get(AutoTagImpl2::class);

        $implementations = $container2->tagged(AutoTagInterface::class);

        $this->assertCount(2, $implementations);
        $this->assertContainsOnlyInstancesOf(AutoTagInterface::class, $implementations);
    }
}

interface AutoTagInterface {}

class AutoTagImpl1 implements AutoTagInterface {}

class AutoTagImpl2 implements AutoTagInterface {}

interface AutoTagInterface1 {}

interface AutoTagInterface2 {}

class AutoTagMultiImpl implements AutoTagInterface1, AutoTagInterface2 {}

class AutoTagNoInterface {}

interface AutoTagInterface3 {}

class AutoTagComplexImpl implements AutoTagInterface1, AutoTagInterface2, AutoTagInterface3 {}
