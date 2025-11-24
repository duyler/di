<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Unit;

use Duyler\DI\Definition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class DefinitionTest extends TestCase
{
    #[Test]
    public function creates_definition_with_id_and_arguments(): void
    {
        $arguments = ['arg1' => 'value1', 'arg2' => 'value2'];
        $definition = new Definition('TestClass', $arguments);

        $this->assertEquals('TestClass', $definition->id);
        $this->assertEquals($arguments, $definition->arguments);
    }

    #[Test]
    public function creates_definition_with_empty_arguments(): void
    {
        $definition = new Definition('TestClass', []);

        $this->assertEquals('TestClass', $definition->id);
        $this->assertEmpty($definition->arguments);
    }

    #[Test]
    public function definition_properties_are_readonly(): void
    {
        $definition = new Definition('TestClass', ['arg' => 'value']);

        $this->assertEquals('TestClass', $definition->id);
        $this->assertEquals(['arg' => 'value'], $definition->arguments);
    }

    #[Test]
    public function arguments_can_contain_objects(): void
    {
        $object = new stdClass();
        $definition = new Definition('TestClass', ['obj' => $object]);

        $this->assertSame($object, $definition->arguments['obj']);
    }

    #[Test]
    public function arguments_can_contain_mixed_types(): void
    {
        $arguments = [
            'string' => 'value',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'array' => [1, 2, 3],
            'object' => new stdClass(),
            'null' => null,
        ];

        $definition = new Definition('TestClass', $arguments);

        $this->assertEquals($arguments, $definition->arguments);
    }
}
