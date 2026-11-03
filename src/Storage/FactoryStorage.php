<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

final class FactoryStorage
{
    /** @var array<string, (callable(\Duyler\DI\Container=): object)> */
    private array $factories = [];

    /** @param (callable(\Duyler\DI\Container=): object) $factory */
    public function set(string $className, callable $factory): void
    {
        $this->factories[$className] = $factory;
    }

    /** @return (callable(\Duyler\DI\Container=): object) */
    public function get(string $className): callable
    {
        return $this->factories[$className];
    }

    public function has(string $className): bool
    {
        return isset($this->factories[$className]);
    }

    public function reset(): void
    {
        $this->factories = [];
    }
}
