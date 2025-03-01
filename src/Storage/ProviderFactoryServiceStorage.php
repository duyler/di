<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

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
        return array_key_exists($class, $this->services);
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
