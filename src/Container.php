<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Exception\CircularReferenceException;
use Duyler\DependencyInjection\Exception\InterfaceMapNotFoundException;
use Duyler\DependencyInjection\Exception\ResolveDependenciesTreeException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

use function interface_exists;

class Container implements ContainerInterface
{
    protected readonly Compiler $compiler;
    protected readonly DependencyMapper $dependencyMapper;
    protected readonly ServiceStorage $serviceStorage;
    private array $dependenciesTree = [];

    public function __construct(
        ContainerConfig $containerConfig = null,
    ) {
        $this->serviceStorage = new ServiceStorage();
        $this->compiler = new Compiler($this->serviceStorage);
        $this->dependencyMapper = new DependencyMapper(
            reflectionStorage: new ReflectionStorage(),
            serviceStorage: $this->serviceStorage,
        );

        $this->addProviders($containerConfig?->getProviders() ?? []);
        $this->bind($containerConfig?->getClassMap()  ?? []);

        foreach ($containerConfig?->getDefinitions() ?? [] as $definition) {
            $this->addDefinition($definition);
        }
    }

    /**
     * @throws ResolveDependenciesTreeException
     * @throws InterfaceMapNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws CircularReferenceException
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
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

    /**
     * @throws ResolveDependenciesTreeException
     * @throws InterfaceMapNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws CircularReferenceException
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
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

    /**
     * @throws ResolveDependenciesTreeException
     * @throws NotFoundExceptionInterface
     * @throws InterfaceMapNotFoundException
     * @throws CircularReferenceException
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
    #[Override]
    public function addProviders(array $providers): self
    {
        foreach ($providers as $bindClassName => $providerClassName) {
            $provider = $this->makeRequiredObject($providerClassName);
            $this->compiler->addProvider($bindClassName, $provider);
            $this->dependencyMapper->addProvider($bindClassName, $provider);
        }

        return $this;
    }

    #[Override]
    public function addDefinition(Definition $definition): self
    {
        $this->compiler->addDefinition($definition);
        return $this;
    }

    /**
     * @throws ResolveDependenciesTreeException
     * @throws NotFoundExceptionInterface
     * @throws InterfaceMapNotFoundException
     * @throws CircularReferenceException
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    protected function makeRequiredObject(string $className): mixed
    {
        if (!isset($this->dependenciesTree[$className])) {
            $this->dependenciesTree[$className] = $this->dependencyMapper->resolve($className);
        }

        $this->compiler->compile($className, $this->dependenciesTree[$className]);

        return $this->get($className);
    }

    #[Override]
    public function softCleanUp(): self
    {
        $this->serviceStorage->cleanUp();
        return $this;
    }
}
