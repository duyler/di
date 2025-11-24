<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

final class DecoratorStorage
{
    /** @var array<string, array<callable>> */
    private array $decorators = [];

    public function add(string $serviceId, callable $decorator): void
    {
        if (!isset($this->decorators[$serviceId])) {
            $this->decorators[$serviceId] = [];
        }

        $this->decorators[$serviceId][] = $decorator;
    }

    /**
     * @return array<callable>
     */
    public function get(string $serviceId): array
    {
        return $this->decorators[$serviceId] ?? [];
    }

    public function has(string $serviceId): bool
    {
        return isset($this->decorators[$serviceId]) && !empty($this->decorators[$serviceId]);
    }

    public function reset(): void
    {
        $this->decorators = [];
    }
}
