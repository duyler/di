<?php

declare(strict_types=1);

namespace Konveyer\DependencyInjection;

use Konveyer\DependencyInjection\Exception\NotFoundException;
use Konveyer\DependencyInjection\Exception\DefinitionIsNotObjectTypeException;

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

    public function get($className): mixed
    {
        if (!isset($this->definitions[$className])) {
            throw new NotFoundException($className);
        }
        return $this->definitions[$className];
    }

    public function has($className): bool
    {
        return isset($this->definitions[$className]);
    }

    public function set($definition): void
    {
        if (!is_object($definition)) {
            throw new DefinitionIsNotObjectTypeException(gettype($definition));
        }
        $className = $definition::class;

        $this->definitions[$className] = $definition;
    }

    // TODO скорее всего будут проблемы с Enum
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
        $resolvedProviders = $this->prepareProviders($providers);
        $this->compiler->setProviders($resolvedProviders);
        $this->dependencyMapper->setProviders($resolvedProviders);
    }

    protected function prepareProviders(array $providers): array
    {
        $resolvedProviders = [];

        foreach ($providers as $bindClassName => $providerClassName) {
            $resolvedProviders[$bindClassName] = $this->makeRequiredObject($providerClassName);
        }

        return $resolvedProviders;
    }

    protected function makeRequiredObject(string $className): mixed
    {
        $dependenciesTree = $this->dependencyMapper->resolve($className);

        $this->definitions = $this->compiler->compile($className, $dependenciesTree);

        return $this->get($className);
    }
}
