<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use InvalidArgumentException;

class ServiceStorage
{
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
}
