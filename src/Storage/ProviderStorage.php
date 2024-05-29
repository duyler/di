<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Storage;

use Duyler\DependencyInjection\Provider\ProviderInterface;

class ProviderStorage
{
    private array $providers = [];

    public function add(string $id, ProviderInterface $provider): void
    {
        $this->providers[$id] = $provider;
    }

    public function get(string $id): ?ProviderInterface
    {
        return $this->providers[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }
}
