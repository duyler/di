<?php

declare(strict_types=1);

namespace Duyler\DI;

/**
 * Definition arguments override provider arguments and resolved
 * dependencies: the union (+) merge is left-wins —
 * Definition arguments > provider arguments > resolved dependencies.
 */
readonly class Definition
{
    public function __construct(
        public string $id,
        /** @var array<string, mixed> */
        public array $arguments,
    ) {}
}
