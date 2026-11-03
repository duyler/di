<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

/**
 * null values are never stored here (add(string, object)), so the isset
 * semantics of has() match every other storage.
 */
final class ProviderFactoryServiceStorage
{
    /** @var array<string, object> */
    private array $services = [];

    public function add(string $class, object $service): void
    {
        $this->services[$class] = $service;
    }

    public function has(string $class): bool
    {
        return isset($this->services[$class]);
    }

    public function get(string $class): object
    {
        return $this->services[$class];
    }

    public function reset(): void
    {
        $this->services = [];
    }
}
