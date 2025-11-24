<?php

declare(strict_types=1);

namespace Duyler\DI;

use function current;

use Duyler\DI\Exception\NotFoundException;
use Duyler\DI\Exception\ResolveDependenciesTreeException;
use Duyler\DI\Storage\ProviderArgumentsStorage;
use Duyler\DI\Storage\ProviderFactoryServiceStorage;
use Duyler\DI\Storage\ProviderStorage;
use Duyler\DI\Storage\ServiceStorage;

use function end;
use function key;
use function prev;

use Throwable;

final class Injector
{
    /** @var Definition[] */
    private array $externalDefinitions = [];

    /** @var array<string, array<string, string>> */
    private array $dependenciesTree = [];

    public function __construct(
        private readonly ServiceStorage $serviceStorage,
        private readonly ProviderStorage $providerStorage,
        private readonly ProviderArgumentsStorage $argumentsStorage,
        private readonly ProviderFactoryServiceStorage $providerFactoryServiceStorage,
    ) {}

    public function addDefinition(Definition $definition): void
    {
        $this->externalDefinitions[$definition->id] = $definition;
    }

    /**
     * @param array<string, array<string, string>> $dependenciesTree
     * @throws ResolveDependenciesTreeException
     */
    public function build(string $className, array $dependenciesTree = []): void
    {
        $this->dependenciesTree = $dependenciesTree;

        if (empty($this->dependenciesTree)) {
            $this->instanceClass($className);

            return;
        }

        $this->iterateDependenciesTree();
    }

    /**
     * @param array<string, array<string, string>> $dependenciesTree
     * @throws ResolveDependenciesTreeException
     */
    public function buildTransient(string $className, array $dependenciesTree = []): object
    {
        $this->dependenciesTree = $dependenciesTree;

        if (empty($this->dependenciesTree)) {
            return $this->createTransientInstance($className);
        }

        return $this->createTransientWithDependencies($className);
    }

    private function setDefinitions(string $className, object $definition): void
    {
        if (false === $this->serviceStorage->has($className)) {
            $this->serviceStorage->set($className, $definition);
        }
    }

    private function getDefinitions(string $className): object
    {
        return $this->serviceStorage->get($className);
    }

    private function hasDefinition(string $className): bool
    {
        return $this->serviceStorage->has($className);
    }

    /**
     * @throws ResolveDependenciesTreeException
     */
    private function iterateDependenciesTree(): void
    {
        $deps = end($this->dependenciesTree);

        while (false !== $deps) {
            /** @var string $class */
            $class = key($this->dependenciesTree);

            /** @var array<string, string> $deps */
            $deps = current($this->dependenciesTree);

            $this->instanceClass($class, $deps);

            if (false === prev($this->dependenciesTree)) {
                break;
            }
        }
    }

    /**
     * @param array<string, string> $deps
     * @throws NotFoundException
     */
    private function instanceClass(string $className, array $deps = []): void
    {
        $dependencies = [];

        foreach ($deps as $argName => $dep) {
            if (isset($this->dependenciesTree[$dep])) {
                $this->instanceClass($dep, $this->dependenciesTree[$dep]);
            } else {
                $this->prepareDependencies($dep);
            }

            if ($this->hasDefinition($dep)) {
                $dependencies[$argName] = $this->getDefinitions($dep);
            } else {
                throw new NotFoundException($dep);
            }
        }

        $this->prepareDependencies($className, $dependencies);
    }

    /**
     * @throws ResolveDependenciesTreeException
     * @psalm-suppress InvalidStringClass
     */
    private function prepareDependencies(string $className, array $dependencies = []): void
    {
        $arguments = $this->argumentsStorage->get($className);

        if (isset($this->externalDefinitions[$className])) {
            $arguments = $this->externalDefinitions[$className]->arguments + $arguments;
        }

        if (false === $this->hasDefinition($className)) {

            try {
                $definition = null;

                if ($this->providerFactoryServiceStorage->has($className)) {
                    $definition = $this->providerFactoryServiceStorage->get($className);
                }

                if (null === $definition) {
                    $definition = new $className(...$arguments + $dependencies);
                }

                if ($this->providerStorage->has($className)) {
                    $provider = $this->providerStorage->get($className);
                    $provider->accept($definition);
                }

                $this->setDefinitions($className, $definition);
            } catch (Throwable $exception) {
                throw new ResolveDependenciesTreeException($exception->getMessage() . ' in ' . $className);
            }
        }
    }

    /**
     * @throws ResolveDependenciesTreeException
     * @psalm-suppress InvalidStringClass
     */
    private function createTransientInstance(string $className): object
    {
        $arguments = $this->argumentsStorage->get($className);

        if (isset($this->externalDefinitions[$className])) {
            $arguments = $this->externalDefinitions[$className]->arguments + $arguments;
        }

        try {
            if ($this->providerFactoryServiceStorage->has($className)) {
                return $this->providerFactoryServiceStorage->get($className);
            }

            $instance = new $className(...$arguments);

            if ($this->providerStorage->has($className)) {
                $provider = $this->providerStorage->get($className);
                $provider->accept($instance);
            }

            return $instance;
        } catch (Throwable $exception) {
            throw new ResolveDependenciesTreeException($exception->getMessage() . ' in ' . $className);
        }
    }

    /**
     * @throws ResolveDependenciesTreeException
     */
    private function createTransientWithDependencies(string $className): object
    {
        $targetDeps = $this->dependenciesTree[$className] ?? [];

        if (empty($targetDeps)) {
            return $this->createTransientInstance($className);
        }

        $dependencies = [];

        foreach ($targetDeps as $argName => $dep) {
            if ($this->hasDefinition($dep)) {
                $dependencies[$argName] = $this->getDefinitions($dep);
                continue;
            }

            if (isset($this->dependenciesTree[$dep])) {
                $this->instanceClass($dep, $this->dependenciesTree[$dep]);
            } else {
                $this->instanceClass($dep);
            }

            if ($this->hasDefinition($dep)) {
                $dependencies[$argName] = $this->getDefinitions($dep);
            } else {
                throw new NotFoundException($dep);
            }
        }

        return $this->createTransientInstanceWithDeps($className, $dependencies);
    }

    /**
     * @param array<string, object> $dependencies
     * @throws ResolveDependenciesTreeException
     * @psalm-suppress InvalidStringClass
     */
    private function createTransientInstanceWithDeps(string $className, array $dependencies): object
    {
        $arguments = $this->argumentsStorage->get($className);

        if (isset($this->externalDefinitions[$className])) {
            $arguments = $this->externalDefinitions[$className]->arguments + $arguments;
        }

        try {
            $instance = new $className(...$arguments + $dependencies);

            if ($this->providerStorage->has($className)) {
                $provider = $this->providerStorage->get($className);
                $provider->accept($instance);
            }

            return $instance;
        } catch (Throwable $exception) {
            throw new ResolveDependenciesTreeException($exception->getMessage() . ' in ' . $className);
        }
    }
}
