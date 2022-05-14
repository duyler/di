<?php

declare(strict_types=1);

namespace Konveyer\DependencyInjection;

use function key;
use function current;
use function prev;
use function end;

class Compiler
{
    protected array $definitions = [];
    protected array $providers = [];
    protected bool $singleton = true;
    protected array $dependenciesTree = [];

    public function singleton(bool $flag): void
    {
        $this->singleton = $flag;
    }

    public function setProviders(array $providers): void
    {
        $this->providers = $this->providers + $providers;
    }

    public function compile(string $className, array $dependenciesTree = []): array
    {
        $this->dependenciesTree = $dependenciesTree;

        if (empty($this->dependenciesTree)) {
            $this->instanceClass($className);
            return $this->definitions;
        }

        $this->iterateDependenciesTree();

        return $this->definitions;
    }

    // Проходит по массиву (дереву) зависимостей
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

        $this->setDefinition($className, new $className(...$params = $params + $dependencies));
    }

    protected function setDefinition(string $className, $definition): void
    {
        if ($this->singleton && !isset($this->definitions[$className])) {
            $this->definitions[$className] = $definition;
        } else {
            $this->tmp[$className] = $definition;
        }
    }

    protected function getDefinition(string $className): null|object
    {
        if ($this->singleton && $this->hasDefinition($className)) {
            return $this->definitions[$className];
        }

        if ($this->hasDefinition($className)) {
            return $this->tmp[$className];
        }
        return null;
    }

    protected function hasDefinition(string $className): bool
    {
        if ($this->singleton) {
            return isset($this->definitions[$className]);
        }
        return isset($this->tmp[$className]);
    }
}
