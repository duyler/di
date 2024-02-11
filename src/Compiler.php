<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Exception\ResolveDependenciesTreeException;
use Duyler\DependencyInjection\Provider\ProviderInterface;
use Throwable;

use function current;
use function end;
use function key;
use function prev;

class Compiler
{
    protected ServiceStorage $serviceStorage;
    protected array $definitions = [];
    protected array $providers = [];

    /** @var Definition[]  */
    protected array $externalDefinitions = [];
    protected array $dependenciesTree = [];

    public function __construct(ServiceStorage $serviceStorage)
    {
        $this->serviceStorage = $serviceStorage;
    }

    public function addProvider(string $id, ProviderInterface $provider): void
    {
        $this->providers[$id] = $provider;
    }

    public function addDefinition(Definition $definition): void
    {
        $this->externalDefinitions[$definition->id] = $definition;
    }

    /**
     * @throws ResolveDependenciesTreeException
     */
    public function compile(string $className, array $dependenciesTree = []): void
    {
        $this->dependenciesTree = $dependenciesTree;

        if (empty($this->dependenciesTree)) {
            $this->instanceClass($className);
            return;
        }

        $this->iterateDependenciesTree();
    }

    public function setDefinitions(string $className, $definition): void
    {
        if (false === $this->serviceStorage->has($className)) {
            $this->serviceStorage->set($className, $definition);
        }
    }

    public function getDefinitions(string $className): object
    {
        return $this->serviceStorage->get($className);
    }

    public function hasDefinition(string $className): bool
    {
        return $this->serviceStorage->has($className);
    }

    /**
     * @throws ResolveDependenciesTreeException
     */
    protected function iterateDependenciesTree(): void
    {
        $deps = end($this->dependenciesTree);

        while (false !== $deps) {
            $class = key($this->dependenciesTree);

            $deps = current($this->dependenciesTree);

            $this->instanceClass($class, $deps);

            if (false === prev($this->dependenciesTree)) {
                break;
            }
        }
    }

    /**
     * @throws ResolveDependenciesTreeException
     */
    protected function instanceClass(string $className, array $deps = []): void
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
            }
        }

        $this->prepareDependencies($className, $dependencies);
    }

    /**
     * @throws ResolveDependenciesTreeException
     */
    protected function prepareDependencies(string $className, array $dependencies = []): void
    {
        $arguments = [];
        $provider = null;

        if (isset($this->providers[$className])) {
            /** @var ProviderInterface $provider */
            $provider = $this->providers[$className];
            $arguments   = $provider->getArguments();
        }

        if (isset($this->externalDefinitions[$className])) {
            $arguments = $this->externalDefinitions[$className]->arguments + $arguments;
        }

        if (false === $this->hasDefinition($className)) {
            try {
                $definition = new $className(...$arguments + $dependencies);
                $provider?->accept($definition);
                $this->setDefinitions($className, $definition);
            } catch (Throwable $exception) {
                throw new ResolveDependenciesTreeException($exception->getMessage() . ' in ' . $className);
            }
        }
    }
}
