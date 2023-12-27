<?php

declare(strict_types=1);

use Duyler\DependencyInjection\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    #[Test]
    public function has_with_true()
    {
        $container = new Container();
        $container->set(new stdClass());
        $this->assertTrue($container->has(stdClass::class));
    }

    #[Test]
    public function has_with_false()
    {
        $container = new Container();
        $this->assertFalse($container->has('AnotherClassName'));
    }

    #[Test]
    public function get_with_definition()
    {
        $definition = new stdClass();

        $container = new Container();
        $container->set($definition);

        $this->assertSame($container->get(stdClass::class), $definition);
    }
}
