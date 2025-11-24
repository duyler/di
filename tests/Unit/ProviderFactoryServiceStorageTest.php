<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\Storage\ProviderFactoryServiceStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class ProviderFactoryServiceStorageTest extends TestCase
{
    private ProviderFactoryServiceStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new ProviderFactoryServiceStorage();
    }

    #[Test]
    public function add_and_get_service(): void
    {
        $service = new stdClass();
        $this->storage->add('test', $service);

        $this->assertSame($service, $this->storage->get('test'));
    }

    #[Test]
    public function has_returns_true_for_existing_service(): void
    {
        $service = new stdClass();
        $this->storage->add('test', $service);

        $this->assertTrue($this->storage->has('test'));
    }

    #[Test]
    public function has_returns_false_for_non_existing_service(): void
    {
        $this->assertFalse($this->storage->has('nonexistent'));
    }

    #[Test]
    public function reset_clears_all_services(): void
    {
        $service = new stdClass();
        $this->storage->add('test', $service);

        $this->storage->reset();

        $this->assertFalse($this->storage->has('test'));
    }

    #[Test]
    public function overwrite_existing_service(): void
    {
        $service1 = new stdClass();
        $service1->value = 'first';
        $service2 = new stdClass();
        $service2->value = 'second';

        $this->storage->add('test', $service1);
        $this->storage->add('test', $service2);

        $this->assertSame($service2, $this->storage->get('test'));
        $this->assertEquals('second', $this->storage->get('test')->value);
    }
}
