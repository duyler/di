<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Storage;

class ProviderArgumentsStorage
{
    private array $arguments = [];

    public function get(string $className): array
    {
        return $this->arguments[$className] ?? [];
    }

    public function set(string $className, array $arguments): void
    {
        $this->arguments[$className] = $arguments;
    }

    public function reset(): void
    {
        $this->arguments = [];
    }
}
