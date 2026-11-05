<?php

declare(strict_types=1);

namespace Duyler\DI;

use function current;

use Duyler\DI\Exception\NotFoundException;
use Duyler\DI\Exception\ResolveDependenciesTreeException;
use Duyler\DI\Exception\ServiceNotCreatedException;
use Duyler\DI\Storage\ProviderArgumentsStorage;
use Duyler\DI\Storage\ProviderFactoryServiceStorage;
use Duyler\DI\Storage\ProviderStorage;
use Duyler\DI\Storage\ReflectionStorage;
use Duyler\DI\Storage\ServiceStorage;

use function end;
use function key;
use function prev;

use ReflectionClass;
use ReflectionNamedType;
use Throwable;

final class Injector
{
    /** @var Definition[] */
    private array $externalDefinitions = [];

    public function __construct(
        private readonly ServiceStorage $serviceStorage,
        private readonly ProviderStorage $providerStorage,
        private readonly ProviderArgumentsStorage $argumentsStorage,
        private readonly ProviderFactoryServiceStorage $providerFactoryServiceStorage,
        private readonly ReflectionStorage $reflectionStorage,
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
        if ($dependenciesTree === []) {
            $this->instanceClass($className);

            return;
        }

        $this->iterateDependenciesTree($dependenciesTree);
    }

    /**
     * @param array<string, array<string, string>> $dependenciesTree
     * @throws ResolveDependenciesTreeException
    */
    public function buildTransient(string $className, array $dependenciesTree = []): object
    {
        if ($dependenciesTree === []) {
            return $this->createTransientInstance($className);
        }

        return $this->createTransientWithDependencies($className, $dependenciesTree);
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
     * @param array<string, array<string, string>> $dependenciesTree
     * @throws ResolveDependenciesTreeException
    */
    private function iterateDependenciesTree(array $dependenciesTree): void
    {
        $deps = end($dependenciesTree);

        while (false !== $deps) {
            /** @var string $class */
            $class = key($dependenciesTree);

            /** @var array<string, string> $deps */
            $deps = current($dependenciesTree);

            $this->instanceClass($class, $deps, $dependenciesTree);

            if (false === prev($dependenciesTree)) {
                break;
            }
        }
    }

    /**
     * @param array<string, string> $deps
     * @param array<string, array<string, string>> $dependenciesTree
     * @throws NotFoundException
    */
    private function instanceClass(string $className, array $deps = [], array $dependenciesTree = []): void
    {
        $dependencies = [];

        foreach ($deps as $argName => $dep) {
            if (isset($dependenciesTree[$dep])) {
                $this->instanceClass($dep, $dependenciesTree[$dep], $dependenciesTree);
            } else {
                $this->prepareDependencies($dep);
            }

            if ($this->hasDefinition($dep)) {
                $dependencies[$argName] = $this->getDefinitions($dep);
            } else {
                throw new NotFoundException($dep, []);
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
            if (false === $this->providerFactoryServiceStorage->has($className)) {
                $this->assertArgumentsSatisfiable($className, $arguments + $dependencies);
            }

            try {
                $definition = null;

                if ($this->providerFactoryServiceStorage->has($className)) {
                    $definition = $this->providerFactoryServiceStorage->get($className);
                }

                if (null === $definition) {
                    /** @psalm-suppress InvalidStringClass */
                    $definition = new $className(...$arguments + $dependencies);
                }

                if ($this->providerStorage->has($className)) {
                    $provider = $this->providerStorage->get($className);
                    $provider->accept($definition);
                }

                $this->setDefinitions($className, $definition);
            } catch (Throwable $exception) {
                throw new ResolveDependenciesTreeException(
                    $className,
                    $exception->getMessage(),
                    [],
                    $exception,
                );
            }
        }
    }

    /**
     * @throws ResolveDependenciesTreeException
    */
    private function createTransientInstance(string $className): object
    {
        $arguments = $this->argumentsStorage->get($className);

        if (isset($this->externalDefinitions[$className])) {
            $arguments = $this->externalDefinitions[$className]->arguments + $arguments;
        }

        if (false === $this->providerFactoryServiceStorage->has($className)) {
            $this->assertArgumentsSatisfiable($className, $arguments);
        }

        try {
            if ($this->providerFactoryServiceStorage->has($className)) {
                return $this->providerFactoryServiceStorage->get($className);
            }

            /** @psalm-suppress InvalidStringClass */
            $instance = new $className(...$arguments);

            if ($this->providerStorage->has($className)) {
                $provider = $this->providerStorage->get($className);
                $provider->accept($instance);
            }

            return $instance;
        } catch (Throwable $exception) {
            throw new ResolveDependenciesTreeException(
                $className,
                $exception->getMessage(),
                [],
                $exception,
            );
        }
    }

    /**
     * @param array<string, array<string, string>> $dependenciesTree
     * @throws ResolveDependenciesTreeException
    */
    private function createTransientWithDependencies(string $className, array $dependenciesTree): object
    {
        $targetDeps = $dependenciesTree[$className] ?? [];

        if ($targetDeps === []) {
            return $this->createTransientInstance($className);
        }

        $dependencies = [];

        foreach ($targetDeps as $argName => $dep) {
            if ($this->hasDefinition($dep)) {
                $dependencies[$argName] = $this->getDefinitions($dep);
                continue;
            }

            if (isset($dependenciesTree[$dep])) {
                $this->instanceClass($dep, $dependenciesTree[$dep], $dependenciesTree);
            } else {
                $this->instanceClass($dep);
            }

            if ($this->hasDefinition($dep)) {
                $dependencies[$argName] = $this->getDefinitions($dep);
            } else {
                throw new NotFoundException($dep, []);
            }
        }

        return $this->createTransientInstanceWithDeps($className, $dependencies);
    }

    /**
     * @param string $className owner class whose constructor is checked
     * @param array<array-key, mixed> $arguments final merged arguments of the
     * @throws ServiceNotCreatedException when a non-variadic class-typed
    */
    private function assertArgumentsSatisfiable(string $className, array $arguments): void
    {
        if (!class_exists($className) && !enum_exists($className)) {
            return;
        }

        if (false === $this->reflectionStorage->has($className)) {
            $this->reflectionStorage->set($className, new ReflectionClass($className));
        }

        $constructor = $this->reflectionStorage->get($className)->getConstructor();

        if (null === $constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isVariadic() || $parameter->isDefaultValueAvailable() || $parameter->allowsNull()) {
                continue;
            }

            if (array_key_exists($parameter->getName(), $arguments)) {
                continue;
            }

            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
                continue;
            }

            throw new ServiceNotCreatedException(
                $className,
                sprintf('Parameter "%s" of "%s" cannot be autowired', $parameter->getName(), $className),
            );
        }
    }

    /**
     * @param array<string, object> $dependencies
     * @throws ResolveDependenciesTreeException
    */
    private function createTransientInstanceWithDeps(string $className, array $dependencies): object
    {
        $arguments = $this->argumentsStorage->get($className);

        if (isset($this->externalDefinitions[$className])) {
            $arguments = $this->externalDefinitions[$className]->arguments + $arguments;
        }

        $this->assertArgumentsSatisfiable($className, $arguments + $dependencies);

        try {
            /** @psalm-suppress InvalidStringClass */
            $instance = new $className(...$arguments + $dependencies);

            if ($this->providerStorage->has($className)) {
                $provider = $this->providerStorage->get($className);
                $provider->accept($instance);
            }

            return $instance;
        } catch (Throwable $exception) {
            throw new ResolveDependenciesTreeException(
                $className,
                $exception->getMessage(),
                [],
                $exception,
            );
        }
    }
}
