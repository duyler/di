[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=duyler_di&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=duyler_di)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=duyler_di&metric=coverage)](https://sonarcloud.io/summary/new_code?id=duyler_di)
[![type-coverage](https://shepherd.dev/github/duyler/di/coverage.svg)](https://shepherd.dev/github/duyler/di)
[![psalm-level](https://shepherd.dev/github/duyler/di/level.svg)](https://shepherd.dev/github/duyler/di)

# Duyler Dependency Injection Container

A modern, flexible, and type-safe dependency injection container for PHP applications. This container implements the PSR-11 Container Interface and provides additional features for dependency management.

## Features

- PSR-11 Container Interface implementation
- Type-safe dependency injection
- Provider-based service registration
- Support for interface bindings
- Service finalization
- Reflection caching
- Dependency tree visualization
- Strict type checking

## Installation

```bash
composer require duyler/dependency-injection
```

## Basic Usage

### Simple Container Usage

```php
use Duyler\DI\Container;

$container = new Container();

// Register a service
$container->set(new MyService());

// Get a service
$service = $container->get(MyService::class);
```

### Using Container Configuration

```php
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;

$config = new ContainerConfig();
$config
    ->withBind([
        MyInterface::class => MyImplementation::class,
    ])
    ->withProvider([
        AnotherInterface::class => MyProvider::class,
    ]);

$container = new Container($config);
```

### Creating a Service Provider

```php
use Duyler\DI\Provider\ProviderInterface;
use Duyler\DI\ContainerService;

class MyServiceProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [
            'dependency' => $containerService->getInstance(Dependency::class),
        ];
    }

    public function bind(): array
    {
        return [
            MyInterface::class => MyImplementation::class,
        ];
    }

    public function accept(object $definition): void
    {
        // Handle definition if needed
    }

    public function finalizer(): ?callable
    {
        return function (MyImplementation $service) {
            // Perform finalization
            $service->finalize();
        };
    }

    public function factory(ContainerService $containerService): ?object
    {
        return new MyImplementation(
            $containerService->getInstance(Dependency::class)
        );
    }
}
```

### Using Service Definitions

```php
use Duyler\DI\Definition;

$definition = new Definition(
    MyService::class,
    [
        'dependencyOne' => new AnotherService(),
        'dependencyTwo' => 'Hello, World!',
    ]
);

$container->addDefinition($definition);
```

### Service Finalization

```php
use Duyler\DI\Attribute\Finalize;

#[Finalize]
class MyService
{
    public function finalize(): void
    {
        // Finalization logic
    }
}

// Or using container method
$container->addFinalizer(MyService::class, function (MyService $service) {
    $service->finalize();
});

// Execute finalizers
$container->finalize();
```

### Resetting Container

```php
// Reset all services and providers
$container->reset();
```

### Getting Dependency Tree

```php
// Get the dependency tree for a specific class
$tree = $container->getDependencyTree();
```

### Dependency Mapping

```php
// Get current class mappings

$container = new Container();
$myObject = $container->get(MyClass::class);
$classMap = $container->getClassMap();

```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
