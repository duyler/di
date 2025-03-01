<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

final class ServiceStorage
{
    /** @var array<string, object> */
    private array $services = [];

    public function set(string $className, object $definition): void
    {
        $this->services[$className] = $definition;
    }

    public function get(string $className): object
    {
        return $this->services[$className];
    }

    public function has(string $className): bool
    {
        return isset($this->services[$className]);
    }

    /** @return  array<string, object> */
    public function getAll(): array
    {
        return $this->services;
    }

    public function reset(): void
    {
        $this->services = [];
    }
}
