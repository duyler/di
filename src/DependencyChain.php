<?php

declare(strict_types=1);

namespace Duyler\DI;

final class DependencyChain
{
    /** @var array<string> */
    private array $chain = [];

    public function push(string $className): void
    {
        $this->chain[] = $className;
    }

    public function pop(): void
    {
        array_pop($this->chain);
    }

    public function reset(): void
    {
        $this->chain = [];
    }

    /**
     * @return array<string>
     */
    public function getChain(): array
    {
        return $this->chain;
    }

    public function toString(): string
    {
        return implode(' -> ', $this->chain);
    }

    public function has(string $className): bool
    {
        return in_array($className, $this->chain, true);
    }

    public function isEmpty(): bool
    {
        return empty($this->chain);
    }

    public function getDepth(): int
    {
        return count($this->chain);
    }

    public function getCurrent(): ?string
    {
        $count = count($this->chain);
        return $count > 0 ? $this->chain[$count - 1] : null;
    }
}
