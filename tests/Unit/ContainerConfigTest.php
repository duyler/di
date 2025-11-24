<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\ContainerConfig;
use Duyler\DI\Definition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContainerConfigTest extends TestCase
{
    #[Test]
    public function with_bind_adds_class_map(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            'Interface' => 'Implementation',
        ]);

        $classMap = $config->getClassMap();

        $this->assertArrayHasKey('Interface', $classMap);
        $this->assertEquals('Implementation', $classMap['Interface']);
    }

    #[Test]
    public function with_bind_merges_class_maps(): void
    {
        $config = new ContainerConfig();
        $config->withBind(['Interface1' => 'Implementation1']);
        $config->withBind(['Interface2' => 'Implementation2']);

        $classMap = $config->getClassMap();

        $this->assertCount(2, $classMap);
        $this->assertArrayHasKey('Interface1', $classMap);
        $this->assertArrayHasKey('Interface2', $classMap);
    }

    #[Test]
    public function with_bind_newer_bindings_override_older(): void
    {
        $config = new ContainerConfig();
        $config->withBind(['Interface' => 'Implementation1']);
        $config->withBind(['Interface' => 'Implementation2']);

        $classMap = $config->getClassMap();

        $this->assertEquals('Implementation2', $classMap['Interface']);
    }

    #[Test]
    public function with_provider_adds_provider(): void
    {
        $config = new ContainerConfig();
        $config->withProvider([
            'Interface' => 'Provider',
        ]);

        $providers = $config->getProviders();

        $this->assertArrayHasKey('Interface', $providers);
        $this->assertEquals('Provider', $providers['Interface']);
    }

    #[Test]
    public function with_provider_merges_providers(): void
    {
        $config = new ContainerConfig();
        $config->withProvider(['Interface1' => 'Provider1']);
        $config->withProvider(['Interface2' => 'Provider2']);

        $providers = $config->getProviders();

        $this->assertCount(2, $providers);
    }

    #[Test]
    public function with_definition_adds_definition(): void
    {
        $config = new ContainerConfig();
        $definition = new Definition('TestClass', ['arg' => 'value']);
        $config->withDefinition($definition);

        $definitions = $config->getDefinitions();

        $this->assertCount(1, $definitions);
        $this->assertSame($definition, $definitions[0]);
    }

    #[Test]
    public function with_definition_adds_multiple_definitions(): void
    {
        $config = new ContainerConfig();
        $definition1 = new Definition('TestClass1', []);
        $definition2 = new Definition('TestClass2', []);

        $config->withDefinition($definition1);
        $config->withDefinition($definition2);

        $definitions = $config->getDefinitions();

        $this->assertCount(2, $definitions);
        $this->assertSame($definition1, $definitions[0]);
        $this->assertSame($definition2, $definitions[1]);
    }

    #[Test]
    public function chaining_methods(): void
    {
        $config = new ContainerConfig();
        $definition = new Definition('TestClass', []);

        $result = $config
            ->withBind(['Interface' => 'Implementation'])
            ->withProvider(['Interface' => 'Provider'])
            ->withDefinition($definition);

        $this->assertSame($config, $result);
        $this->assertNotEmpty($config->getClassMap());
        $this->assertNotEmpty($config->getProviders());
        $this->assertNotEmpty($config->getDefinitions());
    }

    #[Test]
    public function empty_config_returns_empty_arrays(): void
    {
        $config = new ContainerConfig();

        $this->assertEmpty($config->getClassMap());
        $this->assertEmpty($config->getProviders());
        $this->assertEmpty($config->getDefinitions());
    }
}
