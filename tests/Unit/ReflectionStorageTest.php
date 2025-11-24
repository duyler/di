<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\Storage\ReflectionStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

class ReflectionStorageTest extends TestCase
{
    private ReflectionStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new ReflectionStorage();
    }

    #[Test]
    public function set_and_get_reflection(): void
    {
        $reflection = new ReflectionClass(stdClass::class);
        $this->storage->set(stdClass::class, $reflection);

        $this->assertSame($reflection, $this->storage->get(stdClass::class));
    }

    #[Test]
    public function has_returns_true_for_existing_reflection(): void
    {
        $reflection = new ReflectionClass(stdClass::class);
        $this->storage->set(stdClass::class, $reflection);

        $this->assertTrue($this->storage->has(stdClass::class));
    }

    #[Test]
    public function has_returns_false_for_non_existing_reflection(): void
    {
        $this->assertFalse($this->storage->has('NonExistentClass'));
    }

    #[Test]
    public function overwrite_existing_reflection(): void
    {
        $reflection1 = new ReflectionClass(stdClass::class);
        $reflection2 = new ReflectionClass(stdClass::class);

        $this->storage->set(stdClass::class, $reflection1);
        $this->storage->set(stdClass::class, $reflection2);

        $this->assertSame($reflection2, $this->storage->get(stdClass::class));
    }
}
