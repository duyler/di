<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Functional;

use Duyler\DI\Attribute\Finalize;
use Duyler\DI\Container;
use Duyler\DI\Exception\FinalizeNotImplementException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResetTest extends TestCase
{
    #[Test]
    public function with_implement_reset(): void
    {
        $container = new Container();
        $obj = $container->get(WithReset::class);
        $obj->setName('test');
        $this->assertEquals('test', $obj->getName());
        $obj->setName('test2');
        $this->assertEquals('test2', $obj->getName());
        $container->finalize();
        $this->assertNull($obj->getName());
    }

    #[Test]
    public function without_implement_reset(): void
    {
        $container = new Container();
        $container->get(WithoutReset::class);

        $this->expectException(FinalizeNotImplementException::class);
        $container->finalize();
    }
}

#[Finalize]
class WithReset
{
    private ?string $name = null;

    public function finalize(): void
    {
        $this->name = null;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}

#[Finalize]
class WithoutReset {}
