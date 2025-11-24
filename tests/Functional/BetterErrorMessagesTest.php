<?php

declare(strict_types=1);

namespace Duyler\DI\Tests\Functional;

use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\Exception\CircularReferenceException;
use Duyler\DI\Exception\NotFoundException;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

class BetterErrorMessagesTest extends TestCase
{
    #[Test]
    public function not_found_exception_includes_suggestions(): void
    {
        $container = new Container();
        $container->set(new BetterErrorService1());
        $container->set(new BetterErrorService2());

        try {
            $container->get('Duyler\DI\Tests\Functional\BetterErrorService');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('not found', $message);
            $this->assertStringContainsString('Did you mean one of these?', $message);
            $this->assertStringContainsString('BetterErrorService', $message);
            $this->assertStringContainsString('Possible solutions:', $message);
        }
    }

    #[Test]
    public function circular_reference_shows_full_chain(): void
    {
        $config = new ContainerConfig();
        $config->withBind([BetterErrorCircularA::class => BetterErrorCircularA::class]);

        $container = new Container($config);

        try {
            $container->get(BetterErrorCircularA::class);
            $this->fail('Expected CircularReferenceException');
        } catch (CircularReferenceException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Circular reference detected', $message);
            $this->assertStringContainsString('BetterErrorCircularA', $message);
            $this->assertStringContainsString('Hint:', $message);
        }
    }

    #[Test]
    public function invalid_binding_shows_requirements(): void
    {
        $config = new ContainerConfig();
        $config->withBind(['NonExistentInterface' => InvalidBindingImplementation::class]);

        $container = new Container($config);

        try {
            $container->compile();
            $this->fail('Expected error in compile');
        } catch (Exception $exception) {
            $errors = $container->compile();
            $this->assertNotEmpty($errors);

            $error = $errors[0];
            $this->assertStringContainsString('Invalid binding', $error);
            $this->assertStringContainsString('Reason:', $error);
            $this->assertStringContainsString('Binding requirements:', $error);
        }
    }

    #[Test]
    public function not_found_exception_shows_solutions(): void
    {
        $container = new Container();

        try {
            $container->get('NonExistentServiceAtAll');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Possible solutions:', $message);
            $this->assertStringContainsString('$container->set', $message);
            $this->assertStringContainsString('$container->bind', $message);
            $this->assertStringContainsString('service provider', $message);
            $this->assertStringContainsString('$container->factory', $message);
        }
    }

    #[Test]
    public function invalid_binding_shows_example(): void
    {
        $config = new ContainerConfig();
        $config->withBind([BetterErrorTestInterface::class => 'InvalidClass']);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $error = $errors[0];
        $this->assertStringContainsString('Invalid binding', $error);
        $this->assertStringContainsString('Example:', $error);
        $this->assertStringContainsString('ConcreteImplementation', $error);
    }

    #[Test]
    public function not_found_exception_with_no_similar_services(): void
    {
        $container = new Container();

        try {
            $container->get('CompletelyUniqueServiceName12345');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('not found', $message);
            $this->assertStringContainsString('Possible solutions:', $message);
        }
    }

    #[Test]
    public function not_found_exception_uses_levenshtein_for_suggestions(): void
    {
        $container = new Container();
        $container->set(new BetterErrorMyService());

        try {
            $container->get('Duyler\DI\Tests\Functional\BetterErrorMyServce');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Did you mean one of these?', $message);
            $this->assertStringContainsString('BetterErrorMyService', $message);
        }
    }

    #[Test]
    public function circular_reference_with_empty_chain(): void
    {
        $config = new ContainerConfig();
        $config->withBind([SelfDependentService::class => SelfDependentService::class]);

        $container = new Container($config);

        try {
            $container->get(SelfDependentService::class);
            $this->fail('Expected CircularReferenceException');
        } catch (CircularReferenceException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Circular reference detected', $message);
            $this->assertStringContainsString('SelfDependentService', $message);
        }
    }

    #[Test]
    public function multiple_suggestions_sorted_by_distance(): void
    {
        $container = new Container();
        $container->set(new LoggerService());
        $container->set(new LogService());
        $container->set(new LogHandler());

        try {
            $container->get('Duyler\DI\Tests\Functional\Logger');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Did you mean one of these?', $message);
        }
    }

    #[Test]
    public function container_get_available_services_includes_all_types(): void
    {
        $config = new ContainerConfig();
        $config->withBind([AvailableInterface::class => AvailableImplementation::class]);

        $container = new Container($config);
        $container->set(new DirectService());

        try {
            $container->get('NonExistent');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Possible solutions:', $message);
        }
    }

    #[Test]
    public function not_found_exception_with_chain(): void
    {
        $container = new Container();

        try {
            $container->get(ServiceWithChainDep::class);
            $this->fail('Expected exception');
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('ServiceWithChainDep', $message);
        }
    }

    #[Test]
    public function circular_reference_with_chain(): void
    {
        $container = new Container();

        try {
            $container->get(CircularChainA::class);
            $this->fail('Expected CircularReferenceException');
        } catch (CircularReferenceException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Circular reference detected', $message);
        }
    }

    #[Test]
    public function invalid_binding_with_non_interface(): void
    {
        $config = new ContainerConfig();
        $config->withBind([BetterErrorConcreteClass::class => BetterErrorAnotherConcreteClass::class]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('must be an interface or abstract class', $errors[0]);
    }

    #[Test]
    public function invalid_binding_interface_not_implemented(): void
    {
        $config = new ContainerConfig();
        $config->withBind([BetterErrorValidInterface::class => BetterErrorWrongImplementation::class]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('does not implement', $errors[0]);
    }

    #[Test]
    public function invalid_binding_abstract_not_extended(): void
    {
        $config = new ContainerConfig();
        $config->withBind([BetterErrorAbstractBase::class => BetterErrorNotExtendingClass::class]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('does not extend', $errors[0]);
    }

    #[Test]
    public function not_found_with_partial_match_suggestions(): void
    {
        $container = new Container();
        $container->set(new UserService());
        $container->set(new UserRepository());
        $container->set(new UserController());

        try {
            $container->get('Duyler\DI\Tests\Functional\User');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Did you mean one of these?', $message);
        }
    }

    #[Test]
    public function compile_errors_multiple_bindings(): void
    {
        $config = new ContainerConfig();
        $config->withBind([
            'NonExistent1' => 'NonExistent2',
            'NonExistent3' => 'NonExistent4',
        ]);

        $container = new Container($config);
        $errors = $container->compile();

        $this->assertGreaterThanOrEqual(2, count($errors));
    }

    #[Test]
    public function exception_message_format_consistency(): void
    {
        $container = new Container();

        try {
            $container->get('TestService');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Service', $message);
            $this->assertStringContainsString('not found', $message);
            $this->assertStringContainsString('Possible solutions:', $message);
            $this->assertStringContainsString('1.', $message);
            $this->assertStringContainsString('2.', $message);
            $this->assertStringContainsString('3.', $message);
            $this->assertStringContainsString('4.', $message);
        }
    }
}

class BetterErrorService1 {}

class BetterErrorService2 {}

class BetterErrorCircularA
{
    public function __construct(BetterErrorCircularA $a) {}
}

class InvalidBindingImplementation {}

interface BetterErrorMissingInterface {}

class BetterErrorServiceWithMissingDep
{
    public function __construct(BetterErrorMissingInterface $dep) {}
}

interface BetterErrorTestInterface {}

class BetterErrorMyService {}

class SelfDependentService
{
    public function __construct(SelfDependentService $self) {}
}

class LoggerService {}

class LogService {}

class LogHandler {}

interface AvailableInterface {}

class AvailableImplementation implements AvailableInterface {}

class DirectService {}

interface MissingChainInterface {}

class ServiceWithChainDep
{
    public function __construct(MissingChainInterface $dep) {}
}

class CircularChainA
{
    public function __construct(CircularChainB $b) {}
}

class CircularChainB
{
    public function __construct(CircularChainC $c) {}
}

class CircularChainC
{
    public function __construct(CircularChainA $a) {}
}

class BetterErrorConcreteClass {}

class BetterErrorAnotherConcreteClass {}

interface BetterErrorValidInterface {}

class BetterErrorWrongImplementation {}

abstract class BetterErrorAbstractBase {}

class BetterErrorNotExtendingClass {}

class UserService {}

class UserRepository {}

class UserController {}
