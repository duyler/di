<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Unit;

use Duyler\DI\DependencyChain;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DependencyChainTest extends TestCase
{
    #[Test]
    public function push_adds_class_to_chain(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');

        $this->assertEquals(['ClassA'], $chain->getChain());
    }

    #[Test]
    public function push_multiple_classes_maintains_order(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');
        $chain->push('ClassB');
        $chain->push('ClassC');

        $this->assertEquals(['ClassA', 'ClassB', 'ClassC'], $chain->getChain());
    }

    #[Test]
    public function pop_removes_last_class_from_chain(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');
        $chain->push('ClassB');
        $chain->push('ClassC');

        $chain->pop();

        $this->assertEquals(['ClassA', 'ClassB'], $chain->getChain());
    }

    #[Test]
    public function pop_on_empty_chain_does_not_error(): void
    {
        $chain = new DependencyChain();

        $chain->pop();

        $this->assertEmpty($chain->getChain());
    }

    #[Test]
    public function reset_clears_entire_chain(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');
        $chain->push('ClassB');

        $chain->reset();

        $this->assertEmpty($chain->getChain());
    }

    #[Test]
    public function to_string_returns_arrow_separated_chain(): void
    {
        $chain = new DependencyChain();
        $chain->push('ServiceA');
        $chain->push('ServiceB');
        $chain->push('ServiceC');

        $this->assertEquals('ServiceA -> ServiceB -> ServiceC', $chain->toString());
    }

    #[Test]
    public function to_string_returns_empty_string_for_empty_chain(): void
    {
        $chain = new DependencyChain();

        $this->assertEquals('', $chain->toString());
    }

    #[Test]
    public function has_returns_true_when_class_exists_in_chain(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');
        $chain->push('ClassB');
        $chain->push('ClassC');

        $this->assertTrue($chain->has('ClassB'));
    }

    #[Test]
    public function has_returns_false_when_class_not_in_chain(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');
        $chain->push('ClassB');

        $this->assertFalse($chain->has('ClassC'));
    }

    #[Test]
    public function is_empty_returns_true_for_new_chain(): void
    {
        $chain = new DependencyChain();

        $this->assertTrue($chain->isEmpty());
    }

    #[Test]
    public function is_empty_returns_false_when_chain_has_items(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');

        $this->assertFalse($chain->isEmpty());
    }

    #[Test]
    public function is_empty_returns_true_after_reset(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');
        $chain->reset();

        $this->assertTrue($chain->isEmpty());
    }

    #[Test]
    public function get_depth_returns_zero_for_empty_chain(): void
    {
        $chain = new DependencyChain();

        $this->assertEquals(0, $chain->getDepth());
    }

    #[Test]
    public function get_depth_returns_correct_count(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');
        $chain->push('ClassB');
        $chain->push('ClassC');

        $this->assertEquals(3, $chain->getDepth());
    }

    #[Test]
    public function get_current_returns_last_added_class(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');
        $chain->push('ClassB');
        $chain->push('ClassC');

        $this->assertEquals('ClassC', $chain->getCurrent());
    }

    #[Test]
    public function get_current_returns_null_for_empty_chain(): void
    {
        $chain = new DependencyChain();

        $this->assertNull($chain->getCurrent());
    }

    #[Test]
    public function get_current_updates_after_push(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');

        $this->assertEquals('ClassA', $chain->getCurrent());

        $chain->push('ClassB');

        $this->assertEquals('ClassB', $chain->getCurrent());
    }

    #[Test]
    public function get_current_updates_after_pop(): void
    {
        $chain = new DependencyChain();
        $chain->push('ClassA');
        $chain->push('ClassB');

        $this->assertEquals('ClassB', $chain->getCurrent());

        $chain->pop();

        $this->assertEquals('ClassA', $chain->getCurrent());
    }

    #[Test]
    public function complex_scenario_with_push_pop_reset(): void
    {
        $chain = new DependencyChain();

        $chain->push('ServiceA');
        $chain->push('ServiceB');
        $this->assertEquals(2, $chain->getDepth());
        $this->assertEquals('ServiceA -> ServiceB', $chain->toString());

        $chain->pop();
        $this->assertEquals(1, $chain->getDepth());
        $this->assertEquals('ServiceA', $chain->toString());

        $chain->push('ServiceC');
        $chain->push('ServiceD');
        $this->assertTrue($chain->has('ServiceC'));
        $this->assertFalse($chain->has('ServiceB'));

        $chain->reset();
        $this->assertTrue($chain->isEmpty());
        $this->assertEquals(0, $chain->getDepth());
        $this->assertNull($chain->getCurrent());
    }
}
