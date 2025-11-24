<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\Provider\ProviderInterface;
use Duyler\DI\Storage\ProviderStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProviderStorageTest extends TestCase
{
    private ProviderStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new ProviderStorage();
    }

    #[Test]
    public function add_and_get_provider(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $this->storage->add('test', $provider);

        $this->assertSame($provider, $this->storage->get('test'));
    }

    #[Test]
    public function has_returns_true_for_existing_provider(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $this->storage->add('test', $provider);

        $this->assertTrue($this->storage->has('test'));
    }

    #[Test]
    public function has_returns_false_for_non_existing_provider(): void
    {
        $this->assertFalse($this->storage->has('nonexistent'));
    }

    #[Test]
    public function overwrite_existing_provider(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider2 = $this->createMock(ProviderInterface::class);

        $this->storage->add('test', $provider1);
        $this->storage->add('test', $provider2);

        $this->assertSame($provider2, $this->storage->get('test'));
    }
}
