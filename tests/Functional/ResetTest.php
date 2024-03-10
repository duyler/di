<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Test\Functional;

use Duyler\DependencyInjection\Attribute\Reset;
use Duyler\DependencyInjection\Container;
use Duyler\DependencyInjection\Exception\ResetNotImplementException;
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
        $container->selectiveReset();
        $this->assertNull($obj->getName());
    }

    #[Test]
    public function without_implement_reset(): void
    {
        $container = new Container();
        $container->get(WithoutReset::class);

        $this->expectException(ResetNotImplementException::class);
        $container->selectiveReset();
    }
}

#[Reset]
class WithReset
{
    private ?string $name = null;

    public function reset(): void
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

#[Reset]
class WithoutReset {}
