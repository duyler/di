<?php

declare(strict_types=1);

namespace Duyler\DI;

use Duyler\DI\Exception\ResolveDependenciesTreeException;
use Duyler\DI\Storage\ProviderArgumentsStorage;
use Duyler\DI\Storage\ProviderFactoryServiceStorage;
use Duyler\DI\Storage\ProviderStorage;
use Duyler\DI\Storage\ServiceStorage;
use Throwable;

use function current;
use function end;
use function key;
use function prev;

class Compiler
{
    protected array $definitions = [];

    /** @var Definition[] */
    protected array $externalDefinitions = [];
    protected array $dependenciesTree = [];

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

    public function setDefinitions(string $className, object $definition): void
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
    private function iterateDependenciesTree(): void
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
            }
        }

        $this->prepareDependencies($className, $dependencies);
    }

    /**
     * @throws ResolveDependenciesTreeException
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
}
