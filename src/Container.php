<?php

declare(strict_types=1);

namespace Duyler\DI;

use Duyler\DI\Attribute\Finalize;
use Duyler\DI\Exception\FinalizeNotImplementException;
use Duyler\DI\Exception\NotFoundException;
use Duyler\DI\Provider\ProviderInterface;
use Duyler\DI\Storage\FactoryStorage;
use Duyler\DI\Storage\ProviderArgumentsStorage;
use Duyler\DI\Storage\ProviderFactoryServiceStorage;
use Duyler\DI\Storage\ProviderStorage;
use Duyler\DI\Storage\ReflectionStorage;
use Duyler\DI\Storage\ScopeStorage;
use Duyler\DI\Storage\ServiceStorage;
use Duyler\DI\Storage\TagStorage;

use function interface_exists;

use Override;
use Psr\Container\ContainerExceptionInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

class Container implements ContainerInterface
{
    private readonly Injector $injector;
    private readonly DependencyMapper $dependencyMapper;
    private readonly ContainerService $containerService;
    private readonly ServiceStorage $serviceStorage;
    private readonly ProviderStorage $providerStorage;
    private readonly ReflectionStorage $reflectionStorage;
    private readonly ProviderArgumentsStorage $argumentsStorage;
    private readonly ProviderFactoryServiceStorage $providerFactoryServiceStorage;
    private readonly ScopeStorage $scopeStorage;
    private readonly FactoryStorage $factoryStorage;
    private readonly TagStorage $tagStorage;
    private readonly DebugInfo $debugInfo;
    private readonly AttributeReader $attributeReader;
    private readonly Event\EventDispatcher $eventDispatcher;
    private readonly Storage\DecoratorStorage $decoratorStorage;

    /** @var array<string, array<string, array<string, string>>> */
    private array $dependenciesTree = [];

    /** @var array<string, callable>  */
    private array $finalizers = [];

    private bool $autoTagging = false;

    public function __construct(
        ?ContainerConfig $containerConfig = null,
    ) {
        $this->serviceStorage = new ServiceStorage();
        $this->providerStorage = new ProviderStorage();
        $this->reflectionStorage = new ReflectionStorage();
        $this->argumentsStorage = new ProviderArgumentsStorage();
        $this->providerFactoryServiceStorage = new ProviderFactoryServiceStorage();
        $this->scopeStorage = new ScopeStorage();
        $this->factoryStorage = new FactoryStorage();
        $this->tagStorage = new TagStorage();
        $this->debugInfo = new DebugInfo();
        $this->attributeReader = new AttributeReader();
        $this->eventDispatcher = new Event\EventDispatcher();
        $this->decoratorStorage = new Storage\DecoratorStorage();
        $this->containerService = new ContainerService($this);

        if ($containerConfig?->isDebugMode()) {
            $this->debugInfo->enable();
        }

        $this->autoTagging = $containerConfig?->isAutoTagging() ?? false;

        $this->injector = new Injector(
            serviceStorage: $this->serviceStorage,
            providerStorage: $this->providerStorage,
            argumentsStorage: $this->argumentsStorage,
            providerFactoryServiceStorage: $this->providerFactoryServiceStorage,
        );

        $this->dependencyMapper = new DependencyMapper(
            reflectionStorage: $this->reflectionStorage,
            serviceStorage: $this->serviceStorage,
            providerStorage: $this->providerStorage,
            argumentsStorage: $this->argumentsStorage,
            containerService: $this->containerService,
            providerFactoryServiceStorage: $this->providerFactoryServiceStorage,
        );

        $this->addProviders($containerConfig?->getProviders() ?? []);
        $this->bind($containerConfig?->getClassMap()  ?? []);

        foreach ($containerConfig?->getDefinitions() ?? [] as $definition) {
            $this->addDefinition($definition);
        }

        foreach ($containerConfig?->getScopes() ?? [] as $className => $scope) {
            $this->scopeStorage->set($className, $scope);
        }

        foreach ($containerConfig?->getTags() ?? [] as $serviceId => $tags) {
            $this->tagStorage->tag($serviceId, $tags);
        }
    }

    #[Override]
    public function get(string $id): mixed
    {
        $this->eventDispatcher->dispatch(new Event\ContainerEvent(Event\ContainerEvents::BEFORE_RESOLVE, $id));

        if (class_exists($id)) {
            $this->applyAttributes($id);
        }

        $resolvedId = $id;
        if (interface_exists($id)) {
            $bound = $this->dependencyMapper->getClassMap()[$id] ?? null;
            if ($bound !== null && class_exists($bound)) {
                $this->applyAttributes($bound);
                $resolvedId = $bound;
            }
        }

        $scope = $this->scopeStorage->get($resolvedId);

        if ($scope === Scope::Transient) {
            try {
                return $this->resolveService($id, fn() => $this->makeTransient($id));
            } catch (ContainerExceptionInterface $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new NotFoundException($id, $this->getAvailableServices());
            }
        }

        if ($this->has($id)) {
            $service = $this->serviceStorage->get($id);
            $this->eventDispatcher->dispatch(new Event\ContainerEvent(
                Event\ContainerEvents::AFTER_RESOLVE,
                $id,
                $service,
            ));
            return $service;
        }

        if ($this->factoryStorage->has($id)) {
            $factory = $this->factoryStorage->get($id);
            return $this->resolveService($id, function () use ($factory): object {
                /** @var object */
                return $factory($this);
            }, true);
        }

        try {
            return $this->resolveService($id, fn() => $this->make($id), true);
        } catch (ContainerExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new NotFoundException($id, $this->getAvailableServices());
        }
    }

    #[Override]
    public function has(string $id): bool
    {
        return $this->serviceStorage->has($id);
    }

    #[Override]
    public function set(object $definition): self
    {
        $this->serviceStorage->set($definition::class, $definition);

        return $this;
    }

    private function make(string $className): object
    {
        if (interface_exists($className)) {
            if ($this->providerStorage->has($className)) {
                $provider = $this->providerStorage->get($className);
                $service = $provider->factory($this->containerService);

                if (null !== $service) {
                    if ($service instanceof $className) {
                        return $service;
                    }
                }
            }
            $className = $this->dependencyMapper->getBind($className);
        }

        return $this->makeRequiredObject($className);
    }

    #[Override]
    public function bind(array $classMap): self
    {
        $this->dependencyMapper->bind($classMap);

        return $this;
    }

    #[Override]
    public function getClassMap(): array
    {
        return $this->dependencyMapper->getClassMap();
    }

    #[Override]
    public function addProviders(array $providers): self
    {
        foreach ($providers as $bindClassName => $providerClassName) {
            /** @var ProviderInterface $provider */
            $provider = $this->makeRequiredObject($providerClassName);
            $this->providerStorage->add($bindClassName, $provider);
            $this->dependencyMapper->bind($provider->bind());
            $classMap = $provider->bind();
            if (array_key_exists($bindClassName, $classMap)) {
                $this->providerStorage->add($classMap[$bindClassName], $provider);
            }

            $finalizer = $provider->finalizer();

            if (null !== $finalizer) {
                $this->finalizers[$bindClassName] = $finalizer;
            }
        }

        return $this;
    }

    #[Override]
    public function addDefinition(Definition $definition): self
    {
        $this->injector->addDefinition($definition);

        return $this;
    }

    private function makeRequiredObject(string $className): object
    {
        if (!isset($this->dependenciesTree[$className])) {
            $this->dependenciesTree[$className] = $this->dependencyMapper->resolve($className);
        }

        /** @var array<string, array<string, string>> $tree */
        $tree = $this->dependenciesTree[$className];
        $this->injector->build($className, $tree);

        /** @var object */
        return $this->get($className);
    }

    private function makeTransient(string $className): object
    {
        if (interface_exists($className)) {
            if ($this->providerStorage->has($className)) {
                $provider = $this->providerStorage->get($className);
                $service = $provider->factory($this->containerService);

                if (null !== $service) {
                    if ($service instanceof $className) {
                        return $service;
                    }
                }
            }
            $className = $this->dependencyMapper->getBind($className);
        }

        if (!isset($this->dependenciesTree[$className])) {
            $this->dependenciesTree[$className] = $this->dependencyMapper->resolve($className);
        }

        /** @var array<string, array<string, string>> $tree */
        $tree = $this->dependenciesTree[$className];

        return $this->injector->buildTransient($className, $tree);
    }

    #[Override]
    public function getDependencyTree(): array
    {
        return $this->dependenciesTree;
    }

    #[Override]
    public function reset(): self
    {
        $this->serviceStorage->reset();
        $this->providerFactoryServiceStorage->reset();
        $this->argumentsStorage->reset();
        $this->factoryStorage->reset();
        $this->tagStorage->reset();
        $this->dependenciesTree = [];
        $this->finalizers = [];
        return $this;
    }

    #[Override]
    public function finalize(): self
    {
        $this->eventDispatcher->dispatch(new Event\ContainerEvent(Event\ContainerEvents::BEFORE_FINALIZE));

        foreach ($this->serviceStorage->getAll() as $className => $service) {
            if ($this->reflectionStorage->has($className)) {
                $reflection = $this->reflectionStorage->get($className);
            } else {
                /** @psalm-suppress ArgumentTypeCoercion */
                $reflection = new ReflectionClass($className);
            }

            $attributes = $reflection->getAttributes();

            foreach ($attributes as $attributeReflection) {
                if ($attributeReflection->getName() === Finalize::class) {
                    $service = $this->serviceStorage->get($className);
                    /** @var Finalize $attribute */
                    $attribute = $attributeReflection->newInstance();
                    if (false === method_exists($service, $attribute->method)) {
                        throw new FinalizeNotImplementException($className, $attribute->method);
                    }

                    $service->{$attribute->method}();
                }
            }
        }

        foreach ($this->finalizers as $class => $finalizer) {
            $classMap = $this->dependencyMapper->getClassMap();

            $class = $classMap[$class] ?? $class;

            if ($this->serviceStorage->has($class)) {
                $service = $this->serviceStorage->get($class);
                $finalizer($service);
            }
        }

        $this->eventDispatcher->dispatch(new Event\ContainerEvent(Event\ContainerEvents::AFTER_FINALIZE));

        return $this;
    }

    #[Override]
    public function addFinalizer(string $class, callable $finalizer): self
    {
        $this->finalizers[$class] = $finalizer;
        return $this;
    }

    #[Override]
    public function factory(string $className, callable $factory): self
    {
        $this->factoryStorage->set($className, $factory);
        return $this;
    }

    #[Override]
    public function compile(): array
    {
        $errors = [];

        // Validate bindings first
        $bindingErrors = $this->dependencyMapper->validateBindings();
        $errors = array_merge($errors, $bindingErrors);

        // Then validate dependency resolution
        $classMap = $this->dependencyMapper->getClassMap();

        foreach ($classMap as $interface => $implementation) {
            try {
                if (!isset($this->dependenciesTree[$implementation])) {
                    $this->dependenciesTree[$implementation] = $this->dependencyMapper->resolve($implementation);
                }
            } catch (Throwable $exception) {
                $errors[] = sprintf(
                    'Failed to resolve "%s" bound to "%s": %s',
                    $interface,
                    $implementation,
                    $exception->getMessage(),
                );
            }
        }

        return $errors;
    }

    #[Override]
    public function tag(string $serviceId, string|array $tags): self
    {
        $this->tagStorage->tag($serviceId, $tags);
        return $this;
    }

    #[Override]
    public function tagged(string $tag): array
    {
        $serviceIds = $this->tagStorage->tagged($tag);
        /** @var array<object> */
        $services = [];

        foreach ($serviceIds as $serviceId) {
            /** @var object */
            $service = $this->get($serviceId);
            $services[] = $service;
        }

        return $services;
    }

    /**
     * @return array<string>
     */
    private function getAvailableServices(): array
    {
        $services = [];

        $services = array_merge($services, array_keys($this->serviceStorage->getAll()));
        $services = array_merge($services, array_keys($this->dependencyMapper->getClassMap()));
        $services = array_merge($services, array_keys($this->providerStorage->getAll()));

        return array_unique($services);
    }

    public function enableDebug(): self
    {
        $this->debugInfo->enable();
        return $this;
    }

    public function disableDebug(): self
    {
        $this->debugInfo->disable();
        return $this;
    }

    public function isDebugEnabled(): bool
    {
        return $this->debugInfo->isEnabled();
    }

    public function getDebugInfo(): DebugInfo
    {
        return $this->debugInfo;
    }

    public function on(string $eventName, callable $listener): self
    {
        $this->eventDispatcher->addListener($eventName, $listener);
        return $this;
    }

    public function getEventDispatcher(): Event\EventDispatcher
    {
        return $this->eventDispatcher;
    }

    /**
     * @param class-string $serviceId
     */
    public function decorate(string $serviceId, callable $decorator): self
    {
        $this->decoratorStorage->add($serviceId, $decorator);
        return $this;
    }

    /**
     * @param class-string $className
     */
    private function applyAttributes(string $className): void
    {
        if (!class_exists($className)) {
            return;
        }

        $hasAttributes = $this->attributeReader->hasAttributes($className);

        if (!$hasAttributes && !$this->autoTagging) {
            return;
        }

        if ($hasAttributes) {
            $scope = $this->attributeReader->getScope($className);
            if ($scope !== null && !$this->scopeStorage->has($className)) {
                $this->scopeStorage->set($className, $scope);
            }

            $tags = $this->attributeReader->getTags($className);
            if (!empty($tags)) {
                $this->tagStorage->tag($className, $tags);
            }

            $binding = $this->attributeReader->getBinding($className);
            if ($binding !== null) {
                $this->bind([$binding => $className]);
            }
        }

        if ($this->autoTagging) {
            $interfaces = $this->attributeReader->getInterfaces($className);
            if (!empty($interfaces)) {
                $this->tagStorage->tag($className, $interfaces);
            }
        }
    }

    private function applyDecorators(string $serviceId, object $service): object
    {
        if (!$this->decoratorStorage->has($serviceId)) {
            return $service;
        }

        $decorators = $this->decoratorStorage->get($serviceId);

        foreach ($decorators as $decorator) {
            $decorated = $decorator($service, $this);
            if (!is_object($decorated)) {
                throw new RuntimeException("Decorator for {$serviceId} must return an object");
            }
            $service = $decorated;
        }

        return $service;
    }

    private function resolveService(string $id, callable $resolver, bool $cache = false): object
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        /** @var object $service */
        $service = $resolver();
        $service = $this->applyDecorators($id, $service);

        if ($cache) {
            $this->serviceStorage->set($id, $service);
        }

        if ($this->debugInfo->isEnabled()) {
            $time = microtime(true) - $startTime;
            $memory = memory_get_usage() - $startMemory;
            $this->debugInfo->recordResolution($id, $time, $memory);
        }

        $this->eventDispatcher->dispatch(new Event\ContainerEvent(
            Event\ContainerEvents::AFTER_RESOLVE,
            $id,
            $service,
            microtime(true) - $startTime,
        ));

        return $service;
    }
}
