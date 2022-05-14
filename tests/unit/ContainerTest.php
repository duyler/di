<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Konveyer\DependencyInjection\Container;
use Konveyer\DependencyInjection\Compiler;
use Konveyer\DependencyInjection\DependencyMapper;

class ContainerTest extends TestCase
{
    private Container $container;
    private Compiler $compiler;
    private DependencyMapper $dependencyMapper;

    /**
     * @test
     */
    public function set_and_has_with_object_type()
    {
        $definition = new StdClass();
        $this->container->set($definition);
        $this->assertTrue($this->container->has(StdClass::class));
    }

    protected function setUp(): void
    {
        $this->compiler = $this->createMock(Compiler::class);
        $this->dependencyMapper = $this->createMock(DependencyMapper::class);
        $this->container = new Container($this->compiler, $this->dependencyMapper);
        parent::setUp();
    }
}
