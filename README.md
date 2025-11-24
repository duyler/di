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
- Scoped services (Singleton, Transient)
- Tagged services for grouping and bulk retrieval
- Binding validation
- Callback factories
- Compile-time dependency validation
- Enhanced error messages with suggestions and context
- Debug mode with profiling and statistics
- Attribute-based configuration (PHP 8+ attributes)
- Auto-tagging by interface
- Event system with lifecycle hooks
- Service decorators for aspect-oriented programming

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

## Advanced Features

### Tagged Services

Tag services to group them by functionality and retrieve them as a collection:

```php
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;

$container = new Container();

// Tag services at runtime
$container->tag(EventListener1::class, 'event.listener');
$container->tag(EventListener2::class, 'event.listener');
$container->tag(EventListener3::class, 'event.listener');

// Get all services with a specific tag
$listeners = $container->tagged('event.listener');
// Returns: [EventListener1, EventListener2, EventListener3]

// Tag a service with multiple tags
$container->tag(LoggerService::class, ['logger', 'monitor', 'debug']);

// Configure tags via ContainerConfig
$config = new ContainerConfig();
$config->withTag(CacheListener::class, 'event.listener');
$config->withTag(EmailListener::class, ['event.listener', 'notification']);

$container = new Container($config);
```

Common use cases:
- Event listeners and subscribers
- Middleware stacks
- Plugin systems
- Decorator chains
- Command handlers

### Service Scopes

Control the lifecycle of your services with scopes:

```php
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\Scope;

$config = new ContainerConfig();
$config->withScope(TransientService::class, Scope::Transient);

$container = new Container($config);

// Singleton (default) - same instance every time
$singleton1 = $container->get(MySingletonService::class);
$singleton2 = $container->get(MySingletonService::class);
// $singleton1 === $singleton2

// Transient - new instance every time
$transient1 = $container->get(TransientService::class);
$transient2 = $container->get(TransientService::class);
// $transient1 !== $transient2
```

### Callback Factories

Register custom factory functions for complex service creation:

```php
use Duyler\DI\Container;
use Duyler\DI\ContainerInterface;

$container = new Container();

$container->factory(MyService::class, function (ContainerInterface $c) {
    $dependency = $c->get(Dependency::class);
    return new MyService($dependency, 'custom_config_value');
});

$service = $container->get(MyService::class);
// Factory is called once, result is cached (singleton)
```

### Binding Validation

Bindings are automatically validated to prevent configuration errors:

```php
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;
use Duyler\DI\Exception\InvalidBindingException;

$config = new ContainerConfig();

// Valid binding
$config->withBind([
    MyInterface::class => MyImplementation::class,
]);

// Invalid bindings throw InvalidBindingException:
// - Interface does not exist
// - Implementation does not exist  
// - Implementation does not implement interface
// - Binding concrete class to concrete class
try {
    $config->withBind([
        ConcreteClass::class => AnotherClass::class, // Error!
    ]);
    $container = new Container($config);
} catch (InvalidBindingException $e) {
    // Handle validation error
}
```

### Compile-time Validation

Validate all dependencies before runtime to catch configuration errors early:

```php
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;

$config = new ContainerConfig();
$config->withBind([
    MyInterface::class => MyImplementation::class,
    AnotherInterface::class => AnotherImplementation::class,
]);

$container = new Container($config);

// Validate all bindings without instantiating services
$errors = $container->compile();

if (empty($errors)) {
    echo "All dependencies are valid!";
} else {
    foreach ($errors as $error) {
        echo "Dependency error: $error\n";
    }
}
```

### Enhanced Error Messages

The container provides detailed error messages with helpful context and suggestions:

**Service Not Found with Suggestions:**

When a service cannot be found, the container suggests similar service names and provides multiple solutions.

**Circular Reference with Full Chain:**

Shows the complete dependency chain leading to the circular reference, making it easy to identify and fix the issue.

**Invalid Binding with Requirements:**

Explains why a binding is invalid and shows the requirements that must be met, along with a correct example.

**Dependency Resolution Errors with Context:**

When dependency resolution fails, shows the full dependency chain and the reason for failure with actionable solutions.

### Debug Mode

Enable debug mode to monitor and profile your container's dependency resolution:

```php
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;

// Enable via configuration
$config = new ContainerConfig();
$config->withDebugMode(true);
$container = new Container($config);

// Or enable manually
$container = new Container();
$container->enableDebug();

// Resolve some services
$container->get(MyService::class);
$container->get(AnotherService::class);

// Get debug information
$debugInfo = $container->getDebugInfo();

// View statistics
$stats = $debugInfo->getStatistics();
echo "Total resolutions: {$stats['total_resolutions']}\n";
echo "Unique services: {$stats['unique_services']}\n";
echo "Total time: {$stats['total_time']}s\n";
echo "Peak memory: {$stats['peak_memory']} bytes\n";
echo "Average time: {$stats['avg_time']}s\n";

// Get detailed resolution data
$resolutions = $debugInfo->getResolutions();
foreach ($resolutions as $serviceId => $data) {
    echo "$serviceId: {$data['count']} times, {$data['total_time']}s total\n";
}

// Find performance bottlenecks
$slowest = $debugInfo->getSlowestServices(5);
$mostResolved = $debugInfo->getMostResolvedServices(5);

// View resolution log with timestamps
$log = $debugInfo->getResolutionLog();
foreach ($log as $entry) {
    echo "{$entry['service']}: {$entry['time']}s, {$entry['memory']} bytes, depth: {$entry['depth']}\n";
}

// Disable debug mode when not needed (improves performance)
$container->disableDebug();
```

Debug mode features:
- Track resolution count for each service
- Measure time and memory usage per service
- Calculate average resolution time
- Identify slowest services
- Find most frequently resolved services
- Complete resolution log with timestamps
- Minimal overhead when disabled

### Attribute-Based Configuration

Use PHP 8+ attributes to configure services declaratively:

```php
use Duyler\DI\Attribute\Singleton;
use Duyler\DI\Attribute\Transient;
use Duyler\DI\Attribute\Tag;
use Duyler\DI\Attribute\Bind;

#[Singleton]
class CachedUserRepository
{
}

#[Transient]
class RequestHandler
{
}

#[Tag(['logger', 'monitor'])]
class FileLogger
{
}

#[Bind(LoggerInterface::class)]
class ConsoleLogger implements LoggerInterface
{
}

$container = new Container();
$logger = $container->get(LoggerInterface::class);
```

Attributes work seamlessly with all container features.

### Auto-Tagging by Interface

Automatically tag services by their implemented interfaces:

```php
use Duyler\DI\Container;
use Duyler\DI\ContainerConfig;

interface EventListenerInterface {}

class UserListener implements EventListenerInterface {}
class OrderListener implements EventListenerInterface {}

$config = new ContainerConfig();
$config->withAutoTagging(true);

$container = new Container($config);

$container->get(UserListener::class);
$container->get(OrderListener::class);

$listeners = $container->tagged(EventListenerInterface::class);
```

Auto-tagging eliminates manual tag registration for interface-based grouping.

### Event System

Hook into container lifecycle events:

```php
use Duyler\DI\Container;
use Duyler\DI\Event\ContainerEvents;

$container = new Container();

$container->on(ContainerEvents::BEFORE_RESOLVE, function ($event) {
    echo "Resolving: {$event->serviceId}\n";
});

$container->on(ContainerEvents::AFTER_RESOLVE, function ($event) {
    echo "Resolved: {$event->serviceId} in {$event->time}s\n";
});

$container->on(ContainerEvents::BEFORE_FINALIZE, function ($event) {
    echo "Starting finalization\n";
});

$container->on(ContainerEvents::AFTER_FINALIZE, function ($event) {
    echo "Finalization complete\n";
});

$service = $container->get(MyService::class);
$container->finalize();
```

Events are perfect for logging, monitoring, and debugging.

### Service Decorators

Wrap services with additional functionality using decorators:

```php
use Duyler\DI\Container;

interface CacheInterface
{
    public function get(string $key): mixed;
}

class RedisCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return 'value from Redis';
    }
}

class CachedDecorator implements CacheInterface
{
    public function __construct(private CacheInterface $inner) {}

    public function get(string $key): mixed
    {
        echo "Cache hit check\n";
        return $this->inner->get($key);
    }
}

$container = new Container();

$container->decorate(CacheInterface::class, fn($service, $c) => new CachedDecorator($service));

$container->bind([CacheInterface::class => RedisCache::class]);
$cache = $container->get(CacheInterface::class);
```

Decorators enable aspect-oriented programming patterns like logging, caching, and validation.

## Roadmap

Future planned features:

### Container Compilation

Pre-compile dependency resolution for maximum performance:

- Generate optimized PHP code for service instantiation
- Eliminate reflection overhead in production
- Cache compiled container configuration
- Warmup command for production deployments

Benefits: 5-10x faster service resolution.

### Lazy Loading with Proxies

Defer service instantiation until first use:

- Generate proxy classes for services
- Automatic proxy generation for interfaces
- Transparent lazy loading without code changes
- Integration with existing scopes

Benefits: Reduced memory usage and faster bootstrap.

### Container Freeze

Lock container configuration after initialization:

- Prevent runtime modifications in production
- Improve security by disabling dynamic registration
- Performance optimizations for immutable containers
- Development/production mode switching

Benefits: Enhanced security and performance.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
