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

final class Injector
{
    private array $definitions = [];

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

            $deps = current($this->dependenciesTree);

            $this->instanceClass($class, $deps);

            if (false === prev($this->dependenciesTree)) {
                break;
            }
        }
    }

    /**
     * @param array<string, string> $deps
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
}
