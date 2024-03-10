<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

class ContainerConfig
{
    /** @var array<string, string> */
    private array $classMap = [];

    /** @var array<string, string> */
    private array $providers = [];

    /** @var Definition[] */
    private array $definitions = [];

    /** @param array<string, string> $bind */
    public function withBind(array $bind): ContainerConfig
    {
        $this->classMap = $bind + $this->classMap;

        return $this;
    }

    /** @param array<string, string> $provider */
    public function withProvider(array $provider): ContainerConfig
    {
        $this->providers = $provider + $this->providers;

        return $this;
    }

    public function withDefinition(Definition $definition): ContainerConfig
    {
        $this->definitions[] = $definition;

        return $this;
    }

    public function getClassMap(): array
    {
        return $this->classMap;
    }

    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getDefinitions(): array
    {
        return $this->definitions;
    }
}
