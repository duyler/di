<?php

declare(strict_types=1);

namespace Duyler\DI;

use Duyler\DI\Attribute\Finalize;
use Duyler\DI\Exception\FinalizeNotImplementException;
use Duyler\DI\Provider\ProviderInterface;
use Duyler\DI\Storage\ProviderArgumentsStorage;
use Duyler\DI\Storage\ProviderFactoryServiceStorage;
use Duyler\DI\Storage\ProviderStorage;
use Duyler\DI\Storage\ReflectionStorage;
use Duyler\DI\Storage\ServiceStorage;
use Override;
use ReflectionClass;

use function interface_exists;

class Container implements ContainerInterface
{
    private readonly Compiler $compiler;
    private readonly DependencyMapper $dependencyMapper;
    private readonly ServiceStorage $serviceStorage;
    private readonly ProviderStorage $providerStorage;
    private readonly ReflectionStorage $reflectionStorage;
    private readonly ProviderArgumentsStorage $argumentsStorage;
    private readonly ProviderFactoryServiceStorage $providerFactoryServiceStorage;
    private array $dependenciesTree = [];
    private array $finalizers = [];

    public function __construct(
        ?ContainerConfig $containerConfig = null,
    ) {
        $this->serviceStorage = new ServiceStorage();
        $this->providerStorage = new ProviderStorage();
        $this->reflectionStorage = new ReflectionStorage();
        $this->argumentsStorage = new ProviderArgumentsStorage();
        $this->providerFactoryServiceStorage = new ProviderFactoryServiceStorage();

        $this->compiler = new Compiler(
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
            containerService: new ContainerService($this),
            providerFactoryServiceStorage: $this->providerFactoryServiceStorage,
        );

        $this->addProviders($containerConfig?->getProviders() ?? []);
        $this->bind($containerConfig?->getClassMap()  ?? []);

        foreach ($containerConfig?->getDefinitions() ?? [] as $definition) {
            $this->addDefinition($definition);
        }
    }

    #[Override]
    public function get(string $id): object
    {
        if ($this->has($id)) {
            return $this->serviceStorage->get($id);
        }

        return $this->make($id);
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
                $service = $provider->factory(new ContainerService($this));

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
        $this->compiler->addDefinition($definition);

        return $this;
    }

    protected function makeRequiredObject(string $className): object
    {
        if (!isset($this->dependenciesTree[$className])) {
            $this->dependenciesTree[$className] = $this->dependencyMapper->resolve($className);
        }
        $this->compiler->compile($className, $this->dependenciesTree[$className]);

        return $this->get($className);
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
        return $this;
    }

    #[Override]
    public function finalize(): self
    {
        foreach ($this->serviceStorage->getAll() as $className => $service) {
            if ($this->reflectionStorage->has($className)) {
                $reflection = $this->reflectionStorage->get($className);
            } else {
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

        return $this;
    }

    #[Override]
    public function addFinalizer(string $class, callable $finalizer): self
    {
        $this->finalizers[$class] = $finalizer;
        return $this;
    }
}
