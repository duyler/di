<?php

declare(strict_types=1);

namespace Duyler\DI;

class ContainerConfig
{
    /** @var array<string, string> */
    private array $classMap = [];

    /** @var array<string, string> */
    private array $providers = [];

    /** @var Definition[] */
    private array $definitions = [];

    /** @var array<string, Scope> */
    private array $scopes = [];

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

    public function withScope(string $className, Scope $scope): ContainerConfig
    {
        $this->scopes[$className] = $scope;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getClassMap(): array
    {
        return $this->classMap;
    }

    /**
     * @return array<string, string>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * @return array<array-key, Definition>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, Scope>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }
}
