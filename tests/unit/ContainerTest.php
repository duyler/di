<?php

declare(strict_types=1);

use Duyler\DependencyInjection\Cache\CacheHandlerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\DependencyInjection\ServiceStorage;
use Duyler\DependencyInjection\Container;
use Duyler\DependencyInjection\Compiler;
use Duyler\DependencyInjection\DependencyMapper;
use Duyler\DependencyInjection\Exception\NotFoundException;
use Duyler\DependencyInjection\Exception\DefinitionIsNotObjectTypeException;

class ContainerTest extends TestCase
{
    private Container $container;
    private Compiler $compiler;
    private DependencyMapper $dependencyMapper;
    private ServiceStorage $serviceStorage;

    protected function setUp(): void
    {
        $this->compiler = $this->createMock(Compiler::class);
        $this->dependencyMapper = $this->createMock(DependencyMapper::class);
        $this->serviceStorage = $this->createMock(ServiceStorage::class);
        $this->cacheHandler = $this->createMock(CacheHandlerInterface::class);
        $this->container = new Container(
            $this->compiler,
            $this->dependencyMapper,
            $this->serviceStorage,
            $this->cacheHandler
        );

        parent::setUp();
    }

    /**
     * @test
     */
    public function has_with_true()
    {
        $this->serviceStorage->method('has')->willReturn(true);
        $this->assertTrue($this->container->has('AnotherClassName'));
    }

    #[Test]
    public function has_with_false()
    {
        $this->serviceStorage->method('has')->willReturn(false);
        $this->assertFalse($this->container->has('AnotherClassName'));
    }

    #[Test]
    public function get_with_definition()
    {
        $definition = new stdClass();

        $this->serviceStorage->method('has')->willReturn(true);
        $this->serviceStorage->method('get')->willReturn($definition);

        $this->assertSame($this->container->get(stdClass::class), $definition);
    }

    #[Test]
    public function get_with_undefined_definition()
    {
        $this->serviceStorage->method('has')->willReturn(false);
        $this->expectException(NotFoundException::class);
        $this->container->get('AnotherClassName');
    }

    #[Test]
    public function set_with_non_object_type()
    {
        $this->expectException(DefinitionIsNotObjectTypeException::class);
        $this->container->set([]);
    }
}
