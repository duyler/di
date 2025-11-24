<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\Storage\ProviderArgumentsStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProviderArgumentsStorageTest extends TestCase
{
    private ProviderArgumentsStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new ProviderArgumentsStorage();
    }

    #[Test]
    public function set_and_get_arguments(): void
    {
        $arguments = ['arg1' => 'value1', 'arg2' => 'value2'];
        $this->storage->set('test', $arguments);

        $this->assertEquals($arguments, $this->storage->get('test'));
    }

    #[Test]
    public function get_returns_empty_array_for_non_existing_class(): void
    {
        $this->assertEquals([], $this->storage->get('nonexistent'));
    }

    #[Test]
    public function merge_arguments_for_same_class(): void
    {
        $this->storage->set('test', ['arg1' => 'value1']);
        $this->storage->set('test', ['arg2' => 'value2']);

        $result = $this->storage->get('test');

        $this->assertArrayHasKey('arg1', $result);
        $this->assertArrayHasKey('arg2', $result);
        $this->assertEquals('value1', $result['arg1']);
        $this->assertEquals('value2', $result['arg2']);
    }

    #[Test]
    public function newer_arguments_overwrite_existing_ones(): void
    {
        $this->storage->set('test', ['arg' => 'first']);
        $this->storage->set('test', ['arg' => 'second']);

        $result = $this->storage->get('test');

        $this->assertEquals('second', $result['arg']);
    }

    #[Test]
    public function reset_clears_all_arguments(): void
    {
        $this->storage->set('test1', ['arg' => 'value']);
        $this->storage->set('test2', ['arg' => 'value']);

        $this->storage->reset();

        $this->assertEquals([], $this->storage->get('test1'));
        $this->assertEquals([], $this->storage->get('test2'));
    }
}
