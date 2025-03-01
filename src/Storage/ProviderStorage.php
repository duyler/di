<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

use Duyler\DI\Provider\ProviderInterface;

final class ProviderStorage
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    public function add(string $id, ProviderInterface $provider): void
    {
        $this->providers[$id] = $provider;
    }

    public function get(string $id): ProviderInterface
    {
        return $this->providers[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }
}
