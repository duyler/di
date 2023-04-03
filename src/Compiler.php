<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Exception\ResolveDependenciesTreeException;
use Duyler\DependencyInjection\Provider\ProviderInterface;
use Throwable;
use function end;
use function key;
use function current;
use function prev;

class Compiler
{
    protected ServiceStorage $serviceStorage;
    protected array $definitions = [];
    protected array $providers = [];
    protected bool $singleton = true;
    protected array $dependenciesTree = [];
    protected array $tmp = [];

    public function __construct(ServiceStorage $serviceStorage)
    {
        $this->serviceStorage = $serviceStorage;
    }

    public function singleton(bool $flag): void
    {
        $this->singleton = $flag;
    }

    public function addProvider(string $id, ProviderInterface $provider): void
    {
        $this->providers[$id] = $provider;
    }

    /**
     * @throws ResolveDependenciesTreeException
     */
    public function compile(string $className, array $dependenciesTree = []): void
    {
        $this->dependenciesTree = $dependenciesTree;
        $this->tmp = [];

        if (empty($this->dependenciesTree)) {
            $this->instanceClass($className);
            return;
        }

        $this->iterateDependenciesTree();
    }

    public function setDefinition(string $className, $definition): void
    {
        if ($this->singleton) {
            if ($this->serviceStorage->has($className) === false) {
                $this->serviceStorage->set($className, $definition);
            }
        } else {
            $this->tmp[$className] = $definition;
        }
    }

    public function getDefinition(string $className): object
    {
        if ($this->singleton && $this->hasDefinition($className)) {
            return $this->serviceStorage->get($className);
        }

        return $this->tmp[$className];
    }

    public function hasDefinition(string $className): bool
    {
        if ($this->singleton) {
            return $this->serviceStorage->has($className);
        }

        return isset($this->tmp[$className]);
    }

    /**
     * @throws ResolveDependenciesTreeException
     */
    protected function iterateDependenciesTree(): void
    {
        $deps = end($this->dependenciesTree);

        while ($deps !== false) {

            $class = key($this->dependenciesTree);

            $deps = current($this->dependenciesTree);

            $this->instanceClass($class, $deps);

            if (prev($this->dependenciesTree) === false) {
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
                $dependencies[$argName] = $this->getDefinition($dep);
            }
        }

        $this->prepareDependencies($className, $dependencies);
    }

    protected function prepareDependencies(string $className, array $dependencies = []): void
    {
        $params = [];

        if (isset($this->providers[$className])) {
            $provider = $this->providers[$className];
            $params = $provider->getParams();
        }

        if ($this->hasDefinition($className) === false) {
            try {
                $this->setDefinition($className, new $className(...$params + $dependencies));
            } catch (Throwable $exception) {
                throw new ResolveDependenciesTreeException($exception->getMessage());
            }
        }
    }
}
