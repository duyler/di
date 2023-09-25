<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Cache\CacheHandlerInterface;
use Duyler\DependencyInjection\Exception\InvalidArgumentException;
use Duyler\DependencyInjection\Exception\NotFoundException;
use Duyler\DependencyInjection\Exception\DefinitionIsNotObjectTypeException;
use Duyler\DependencyInjection\Exception\ResolveDependenciesTreeException;

use function is_object;
use function interface_exists;

class Container implements ContainerInterface
{
    public function __construct(
        protected readonly Compiler $compiler,
        protected readonly DependencyMapper $dependencyMapper,
        protected readonly ServiceStorage $serviceStorage,
        protected readonly CacheHandlerInterface $cacheHandler
    ) {
    }

    public function get(string $id): object
    {
        if ($this->has($id) === false) {
            throw new NotFoundException($id);
        }
        return $this->serviceStorage->get($id);
    }

    public function has(string $id): bool
    {
        return $this->serviceStorage->has($id);
    }

    public function set($definition): void
    {
        if (!is_object($definition)) {
            throw new DefinitionIsNotObjectTypeException(gettype($definition));
        }

        $className = $definition::class;
        if ($this->has($className)) {
            throw new InvalidArgumentException(
                sprintf('The "%s" service is already initialized, you cannot replace it.', $className)
            );
        }

        $this->serviceStorage->set($className, $definition);
    }

    public function make(string $className, string $provider = '', bool $singleton = true): mixed
    {
        $this->compiler->singleton($singleton);

        if (interface_exists($className)) {
            $className = $this->dependencyMapper->getBind($className);
        }

        if (!empty($provider)) {
            $this->setProviders([$className => $provider]);
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

    public function setProviders(array $providers): void
    {
        foreach ($providers as $bindClassName => $providerClassName) {
            $provider = $this->makeRequiredObject($providerClassName);
            $this->compiler->addProvider($bindClassName, $provider);
            $this->dependencyMapper->addProvider($bindClassName, $provider);
        }
    }

    protected function makeRequiredObject(string $className): mixed
    {
        if ($this->cacheHandler->isExists($className)) {
            $dependenciesTree = $this->cacheHandler->get($className);
        } else {
            $dependenciesTree = $this->dependencyMapper->resolve($className);
            $this->cacheHandler->record($className, $dependenciesTree);
        }

        try {
            $this->compiler->compile($className, $dependenciesTree);
        } catch (ResolveDependenciesTreeException $exception) {
            $this->cacheHandler->invalidate($className);
            $dependenciesTree = $this->dependencyMapper->resolve($className);
            $this->cacheHandler->record($className, $dependenciesTree);
            $this->compiler->compile($className, $dependenciesTree);
        }

        return $this->get($className);
    }

    private function __clone()
    {
    }
}
