<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Exception\CircularReferenceException;
use Duyler\DependencyInjection\Exception\InterfaceMapNotFoundException;
use Duyler\DependencyInjection\Exception\InvalidArgumentException;
use Duyler\DependencyInjection\Exception\ResolveDependenciesTreeException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

use function interface_exists;

class Container implements ContainerInterface
{
    protected readonly Compiler $compiler;
    protected readonly DependencyMapper $dependencyMapper;
    protected readonly ServiceStorage $serviceStorage;

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
    public function get(string $id): object
    {
        if ($this->has($id)) {
            return $this->serviceStorage->get($id);
        }

        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return $this->serviceStorage->has($id);
    }

    public function set(object $definition): void
    {
        $className = $definition::class;
        if ($this->has($className)) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" service is already initialized, you cannot replace it.',
                $className,
            ));
        }

        $this->serviceStorage->set($className, $definition);
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

    public function bind(array $classMap): void
    {
        $this->dependencyMapper->bind($classMap);
    }

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
    public function addProviders(array $providers): void
    {
        foreach ($providers as $bindClassName => $providerClassName) {
            $provider = $this->makeRequiredObject($providerClassName);
            $this->compiler->addProvider($bindClassName, $provider);
            $this->dependencyMapper->addProvider($bindClassName, $provider);
        }
    }

    public function addDefinition(Definition $definition): void
    {
        $this->compiler->addDefinition($definition);
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
        $dependenciesTree = $this->dependencyMapper->resolve($className);
        $this->compiler->compile($className, $dependenciesTree);

        return $this->get($className);
    }
}
