<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Unit;

use Duyler\DI\Exception\CircularReferenceException;
use Duyler\DI\Exception\InvalidBindingException;
use Duyler\DI\Exception\NotFoundException;
use Duyler\DI\Exception\ResolveDependenciesTreeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionTest extends TestCase
{
    #[Test]
    public function not_found_exception_without_suggestions_or_chain(): void
    {
        $exception = new NotFoundException('TestService');
        $message = $exception->getMessage();

        $this->assertStringContainsString('Service "TestService" not found', $message);
        $this->assertStringContainsString('Possible solutions:', $message);
        $this->assertStringNotContainsString('Did you mean', $message);
        $this->assertStringNotContainsString('Dependency chain:', $message);
    }

    #[Test]
    public function not_found_exception_with_suggestions_but_no_chain(): void
    {
        $exception = new NotFoundException('TestService', ['TestService1', 'TestService2']);
        $message = $exception->getMessage();

        $this->assertStringContainsString('Service "TestService" not found', $message);
        $this->assertStringContainsString('Did you mean one of these?', $message);
        $this->assertStringContainsString('TestService1', $message);
        $this->assertStringContainsString('TestService2', $message);
        $this->assertStringNotContainsString('Dependency chain:', $message);
    }

    #[Test]
    public function not_found_exception_with_chain_but_no_suggestions(): void
    {
        $exception = new NotFoundException('TestService', [], ['ServiceA', 'ServiceB']);
        $message = $exception->getMessage();

        $this->assertStringContainsString('Service "TestService" not found', $message);
        $this->assertStringContainsString('Dependency chain:', $message);
        $this->assertStringContainsString('ServiceA', $message);
        $this->assertStringContainsString('ServiceB', $message);
        $this->assertStringContainsString('(not found)', $message);
        $this->assertStringNotContainsString('Did you mean', $message);
    }

    #[Test]
    public function not_found_exception_with_both_chain_and_suggestions(): void
    {
        $exception = new NotFoundException('TestService', ['TestService1'], ['ServiceA', 'ServiceB']);
        $message = $exception->getMessage();

        $this->assertStringContainsString('Dependency chain:', $message);
        $this->assertStringContainsString('Did you mean one of these?', $message);
        $this->assertStringContainsString('TestService1', $message);
        $this->assertStringContainsString('ServiceA', $message);
    }

    #[Test]
    public function not_found_exception_suggestions_use_levenshtein(): void
    {
        $availableServices = [
            'MyService',
            'MyServiceImpl',
            'YourService',
            'CompletelyDifferent',
        ];

        $exception = new NotFoundException('MyServce', $availableServices);
        $message = $exception->getMessage();

        $this->assertStringContainsString('Did you mean one of these?', $message);
        $this->assertStringContainsString('MyService', $message);
    }

    #[Test]
    public function not_found_exception_suggestions_limited_to_three(): void
    {
        $availableServices = [
            'Service1',
            'Service2',
            'Service3',
            'Service4',
            'Service5',
        ];

        $exception = new NotFoundException('Service', $availableServices);
        $message = $exception->getMessage();

        $this->assertStringContainsString('Did you mean one of these?', $message);
        preg_match_all('/- .*Service/', $message, $matches);
        $this->assertLessThanOrEqual(3, count($matches[0]));
    }

    #[Test]
    public function circular_reference_exception_without_chain(): void
    {
        $exception = new CircularReferenceException('ClassA', 'ClassA');
        $message = $exception->getMessage();

        $this->assertStringContainsString('Circular reference detected for class "ClassA"', $message);
        $this->assertStringContainsString('depends on "ClassA" which creates a circular dependency', $message);
        $this->assertStringContainsString('Hint:', $message);
        $this->assertStringNotContainsString('Dependency chain:', $message);
    }

    #[Test]
    public function circular_reference_exception_with_chain(): void
    {
        $chain = ['ClassA', 'ClassB', 'ClassC'];
        $exception = new CircularReferenceException('ClassA', 'ClassA', $chain);
        $message = $exception->getMessage();

        $this->assertStringContainsString('Circular reference detected for class "ClassA"', $message);
        $this->assertStringContainsString('Dependency chain:', $message);
        $this->assertStringContainsString('ClassA', $message);
        $this->assertStringContainsString('ClassB', $message);
        $this->assertStringContainsString('ClassC', $message);
        $this->assertStringContainsString('(circular reference back to ClassA)', $message);
        $this->assertStringContainsString('Hint:', $message);
    }

    #[Test]
    public function invalid_binding_exception_shows_all_details(): void
    {
        $exception = new InvalidBindingException(
            'MyInterface',
            'MyImplementation',
            'Does not implement interface',
        );
        $message = $exception->getMessage();

        $this->assertStringContainsString('Invalid binding: "MyInterface" => "MyImplementation"', $message);
        $this->assertStringContainsString('Reason: Does not implement interface', $message);
        $this->assertStringContainsString('Binding requirements:', $message);
        $this->assertStringContainsString('1.', $message);
        $this->assertStringContainsString('2.', $message);
        $this->assertStringContainsString('3.', $message);
        $this->assertStringContainsString('4.', $message);
        $this->assertStringContainsString('Example:', $message);
        $this->assertStringContainsString('ConcreteImplementation', $message);
    }

    #[Test]
    public function resolve_dependencies_exception_without_chain(): void
    {
        $exception = new ResolveDependenciesTreeException(
            'MyService',
            'Constructor parameter cannot be resolved',
        );
        $message = $exception->getMessage();

        $this->assertStringContainsString('Failed to resolve dependency tree for "MyService"', $message);
        $this->assertStringContainsString('Reason: Constructor parameter cannot be resolved', $message);
        $this->assertStringContainsString('Possible solutions:', $message);
        $this->assertStringNotContainsString('Dependency chain:', $message);
    }

    #[Test]
    public function resolve_dependencies_exception_with_chain(): void
    {
        $chain = ['ServiceA', 'ServiceB', 'ServiceC'];
        $exception = new ResolveDependenciesTreeException(
            'MyService',
            'Constructor parameter cannot be resolved',
            $chain,
        );
        $message = $exception->getMessage();

        $this->assertStringContainsString('Failed to resolve dependency tree for "MyService"', $message);
        $this->assertStringContainsString('Dependency chain:', $message);
        $this->assertStringContainsString('ServiceA', $message);
        $this->assertStringContainsString('ServiceB', $message);
        $this->assertStringContainsString('ServiceC', $message);
        $this->assertStringContainsString('(resolution failed here)', $message);
    }

    #[Test]
    public function resolve_dependencies_exception_with_previous(): void
    {
        $previous = new RuntimeException('Original error');
        $exception = new ResolveDependenciesTreeException(
            'MyService',
            'Constructor parameter cannot be resolved',
            [],
            $previous,
        );

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function all_exceptions_contain_actionable_hints(): void
    {
        $notFound = new NotFoundException('Service');
        $circular = new CircularReferenceException('A', 'A');
        $invalid = new InvalidBindingException('I', 'C', 'reason');
        $resolve = new ResolveDependenciesTreeException('S', 'reason');

        $this->assertStringContainsString('$container->', $notFound->getMessage());
        $this->assertStringContainsString('Hint:', $circular->getMessage());
        $this->assertStringContainsString('requirements:', $invalid->getMessage());
        $this->assertStringContainsString('solutions:', $resolve->getMessage());
    }
}
