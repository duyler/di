<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Attribute\Finalize;
use Duyler\DependencyInjection\Exception\FinalizeNotImplementException;
use Duyler\DependencyInjection\Provider\ProviderInterface;
use Duyler\DependencyInjection\Storage\ProviderArgumentsStorage;
use Duyler\DependencyInjection\Storage\ProviderStorage;
use Duyler\DependencyInjection\Storage\ReflectionStorage;
use Duyler\DependencyInjection\Storage\ServiceStorage;
use Override;
use ReflectionClass;

use function interface_exists;

class Container implements ContainerInterface
{
    protected readonly Compiler $compiler;
    protected readonly DependencyMapper $dependencyMapper;
    protected readonly ServiceStorage $serviceStorage;
    protected readonly ProviderStorage $providerStorage;
    protected readonly ReflectionStorage $reflectionStorage;
    protected readonly ProviderArgumentsStorage $argumentsStorage;
    private array $dependenciesTree = [];

    public function __construct(
        ?ContainerConfig $containerConfig = null,
    ) {
        $this->serviceStorage = new ServiceStorage();
        $this->providerStorage = new ProviderStorage();
        $this->reflectionStorage = new ReflectionStorage();
        $this->argumentsStorage = new ProviderArgumentsStorage();

        $this->compiler = new Compiler(
            serviceStorage: $this->serviceStorage,
            providerStorage: $this->providerStorage,
            argumentsStorage: $this->argumentsStorage,
        );

        $this->dependencyMapper = new DependencyMapper(
            reflectionStorage: $this->reflectionStorage,
            serviceStorage: $this->serviceStorage,
            providerStorage: $this->providerStorage,
            argumentsStorage: $this->argumentsStorage,
            containerService: new ContainerService($this),
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

    private function make(string $className): mixed
    {
        if (interface_exists($className)) {
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
        }

        return $this;
    }

    #[Override]
    public function addDefinition(Definition $definition): self
    {
        $this->compiler->addDefinition($definition);

        return $this;
    }

    protected function makeRequiredObject(string $className): mixed
    {
        if (!isset($this->dependenciesTree[$className])) {
            $this->dependenciesTree[$className] = $this->dependencyMapper->resolve($className);
        }
        $this->compiler->compile($className, $this->dependenciesTree[$className]);

        return $this->get($className);
    }

    #[Override]
    public function reset(): self
    {
        $this->serviceStorage->reset();

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

        return $this;
    }
}
