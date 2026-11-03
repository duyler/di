<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

final class ProviderArgumentsStorage
{
    /** @var array<string, array<string, mixed>> */
    private array $arguments = [];

    public function get(string $className): array
    {
        return $this->arguments[$className] ?? [];
    }

    /**
     * Merge semantics: new arguments win over old ones, old keys are kept
     * until reset().
     */
    /** @param array<string, mixed> $arguments */
    public function set(string $className, array $arguments): void
    {
        $existsArguments = $this->arguments[$className] ?? [];
        $this->arguments[$className] = $arguments + $existsArguments;
    }

    public function reset(): void
    {
        $this->arguments = [];
    }
}
