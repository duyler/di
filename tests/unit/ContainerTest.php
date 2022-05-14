<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Konveyer\DependencyInjection\Container;
use Konveyer\DependencyInjection\Compiler;
use Konveyer\DependencyInjection\DependencyMapper;
use Konveyer\DependencyInjection\Exception\NotFoundException;
use Konveyer\DependencyInjection\Exception\DefinitionIsNotObjectTypeException;

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
        $definition = new stdClass();
        $this->container->set($definition);
        $this->assertTrue($this->container->has(stdClass::class));
        $this->assertFalse($this->container->has('AnotherClassName'));
    }

    /**
     * @test
     */
    public function set_and_get_with_definition()
    {
        $definition = new stdClass();
        $this->container->set($definition);
        $this->assertSame($this->container->get(stdClass::class), $definition);
    }

    /**
     * @test
     */
    public function get_with_undefined_definition()
    {
        $this->expectException(NotFoundException::class);
        $this->container->get('AnotherClassName');
    }

    /**
     * @test
     */
    public function set_with_non_object_type()
    {
        $this->expectException(DefinitionIsNotObjectTypeException::class);
        $this->container->set([]);
    }

    protected function setUp(): void
    {
        $this->compiler = $this->createMock(Compiler::class);
        $this->dependencyMapper = $this->createMock(DependencyMapper::class);
        $this->container = new Container($this->compiler, $this->dependencyMapper);
        parent::setUp();
    }
}
