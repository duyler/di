<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

use ReflectionClass;

final class ReflectionStorage
{
    /**
     * @var ReflectionClass[]
     */
    private array $reflections = [];

    public function get(string $reflectionClassName): ReflectionClass
    {
        return $this->reflections[$reflectionClassName];
    }

    public function set(string $reflectionClassName, ReflectionClass $reflection): void
    {
        $this->reflections[$reflectionClassName] = $reflection;
    }

    public function has(string $reflectionClassName): bool
    {
        return isset($this->reflections[$reflectionClassName]);
    }
}
