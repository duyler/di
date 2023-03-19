<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Exception\NotFoundException;
use Duyler\DependencyInjection\Exception\DefinitionIsNotObjectTypeException;

use function is_object;
use function interface_exists;

class Container implements ContainerInterface
{
    protected Compiler $compiler;
    protected DependencyMapper $dependencyMapper;

    public function __construct(Compiler $compiler, DependencyMapper $dependencyMapper)
    {
        $this->compiler = $compiler;
        $this->dependencyMapper = $dependencyMapper;
    }

    public function get(string $id): object
    {
        if ($this->has($id) === false) {
            throw new NotFoundException($id);
        }
        return $this->compiler->getDefinition($id);
    }

    public function has(string $id): bool
    {
        return $this->compiler->hasDefinition($id);
    }

    public function set($definition): void
    {
        if (!is_object($definition)) {
            throw new DefinitionIsNotObjectTypeException(gettype($definition));
        }

        $this->compiler->setDefinition($definition::class, $definition);
    }

    public function make(string $className, string $provider = '', bool $singleton = true): mixed
    {
        $this->compiler->singleton($singleton);

        if (!empty($provider)) {
            $this->setProviders([$className => $provider]);
        }

        if (interface_exists($className)) {
            $className = $this->dependencyMapper->getBind($className);
        }

        return $this->makeRequiredObject($className);
    }

    public function bind(array $classMap): void
    {
        $this->dependencyMapper->bind($classMap);
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
        $dependenciesTree = $this->dependencyMapper->resolve($className);

        $this->compiler->compile($className, $dependenciesTree);

        return $this->get($className);
    }
}
