<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\Storage\ServiceStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class ServiceStorageTest extends TestCase
{
    private ServiceStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new ServiceStorage();
    }

    #[Test]
    public function set_and_get_service(): void
    {
        $service = new stdClass();
        $this->storage->set('test', $service);

        $this->assertSame($service, $this->storage->get('test'));
    }

    #[Test]
    public function has_returns_true_for_existing_service(): void
    {
        $service = new stdClass();
        $this->storage->set('test', $service);

        $this->assertTrue($this->storage->has('test'));
    }

    #[Test]
    public function has_returns_false_for_non_existing_service(): void
    {
        $this->assertFalse($this->storage->has('nonexistent'));
    }

    #[Test]
    public function get_all_returns_all_services(): void
    {
        $service1 = new stdClass();
        $service2 = new stdClass();

        $this->storage->set('test1', $service1);
        $this->storage->set('test2', $service2);

        $all = $this->storage->getAll();

        $this->assertCount(2, $all);
        $this->assertSame($service1, $all['test1']);
        $this->assertSame($service2, $all['test2']);
    }

    #[Test]
    public function reset_clears_all_services(): void
    {
        $service = new stdClass();
        $this->storage->set('test', $service);

        $this->storage->reset();

        $this->assertFalse($this->storage->has('test'));
        $this->assertEmpty($this->storage->getAll());
    }

    #[Test]
    public function overwrite_existing_service(): void
    {
        $service1 = new stdClass();
        $service1->value = 'first';
        $service2 = new stdClass();
        $service2->value = 'second';

        $this->storage->set('test', $service1);
        $this->storage->set('test', $service2);

        $this->assertSame($service2, $this->storage->get('test'));
        $this->assertEquals('second', $this->storage->get('test')->value);
    }
}
