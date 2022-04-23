<?php

declare(strict_types=1);

namespace Konveyer\DependencyInjection;

use ReflectionClass;

class ReflectionStorage
{
    /**
     * @var array[Reflection]
     */
    private array $reflections = [];

    public function get(string $dependencyClassName): ReflectionClass
    {
        return $this->reflections[$dependencyClassName];
    }

    public function set(string $dependencyClassName, ReflectionClass $reflection): void
    {
        $this->reflections[$dependencyClassName] = $reflection;
    }

    public function has(string $dependencyClassName): bool
    {
        return isset($this->reflections[$dependencyClassName]);
    }
}
