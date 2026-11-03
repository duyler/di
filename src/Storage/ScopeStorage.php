<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

use Duyler\DI\Scope;

final class ScopeStorage
{
    /** @var array<string, Scope> */
    private array $scopes = [];

    public function set(string $className, Scope $scope): void
    {
        $this->scopes[$className] = $scope;
    }

    public function get(string $className): Scope
    {
        return $this->scopes[$className] ?? Scope::Singleton;
    }

    public function has(string $className): bool
    {
        return isset($this->scopes[$className]);
    }
}
