<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Cache;

interface CacheHandlerInterface
{
    public function isExists(string $id): bool;
    public function get(string $id): array;
    public function record(string $id, array $dependencyTree): void;
    public function invalidate(string $id): void;
}
