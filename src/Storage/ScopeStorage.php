<?php

declare(strict_types=1);

namespace Duyler\DI\Storage;

use Duyler\DI\Scope;

/**
 * Configuration storage: survives container reset().
 * Singleton is the default scope; manage non-default scopes
 * through withScope() or the #[Transient] attribute.
 */
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
