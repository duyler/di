<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Unit;

use Duyler\DI\Storage\TagStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TagStorageTest extends TestCase
{
    #[Test]
    public function tag_stores_service_with_single_tag(): void
    {
        $storage = new TagStorage();
        $storage->tag('ServiceA', 'tag1');

        $services = $storage->tagged('tag1');

        $this->assertCount(1, $services);
        $this->assertEquals(['ServiceA'], $services);
    }

    #[Test]
    public function tag_stores_service_with_multiple_tags_as_array(): void
    {
        $storage = new TagStorage();
        $storage->tag('ServiceA', ['tag1', 'tag2', 'tag3']);

        $this->assertEquals(['ServiceA'], $storage->tagged('tag1'));
        $this->assertEquals(['ServiceA'], $storage->tagged('tag2'));
        $this->assertEquals(['ServiceA'], $storage->tagged('tag3'));
    }

    #[Test]
    public function tag_stores_multiple_services_with_same_tag(): void
    {
        $storage = new TagStorage();
        $storage->tag('ServiceA', 'shared');
        $storage->tag('ServiceB', 'shared');
        $storage->tag('ServiceC', 'shared');

        $services = $storage->tagged('shared');

        $this->assertCount(3, $services);
        $this->assertEquals(['ServiceA', 'ServiceB', 'ServiceC'], $services);
    }

    #[Test]
    public function tag_does_not_duplicate_same_service_in_same_tag(): void
    {
        $storage = new TagStorage();
        $storage->tag('ServiceA', 'tag1');
        $storage->tag('ServiceA', 'tag1');
        $storage->tag('ServiceA', 'tag1');

        $services = $storage->tagged('tag1');

        $this->assertCount(1, $services);
    }

    #[Test]
    public function tagged_returns_empty_array_for_non_existent_tag(): void
    {
        $storage = new TagStorage();

        $services = $storage->tagged('non.existent');

        $this->assertEmpty($services);
    }

    #[Test]
    public function reset_clears_all_tags(): void
    {
        $storage = new TagStorage();
        $storage->tag('ServiceA', 'tag1');
        $storage->tag('ServiceB', 'tag2');

        $this->assertNotEmpty($storage->tagged('tag1'));
        $this->assertNotEmpty($storage->tagged('tag2'));

        $storage->reset();

        $this->assertEmpty($storage->tagged('tag1'));
        $this->assertEmpty($storage->tagged('tag2'));
    }

    #[Test]
    public function complex_scenario_with_multiple_services_and_tags(): void
    {
        $storage = new TagStorage();

        $storage->tag('Logger', ['logging', 'monitoring']);
        $storage->tag('Metrics', ['monitoring', 'analytics']);
        $storage->tag('EmailLogger', 'logging');
        $storage->tag('FileLogger', 'logging');

        $this->assertCount(3, $storage->tagged('logging'));
        $this->assertCount(2, $storage->tagged('monitoring'));
        $this->assertCount(1, $storage->tagged('analytics'));

        $this->assertContains('Logger', $storage->tagged('logging'));
        $this->assertContains('Logger', $storage->tagged('monitoring'));
        $this->assertNotContains('Logger', $storage->tagged('analytics'));
        $this->assertEmpty($storage->tagged('non.existent'));
    }
}
