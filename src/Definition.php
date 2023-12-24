<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

readonly class Definition
{
    public function __construct(
        public string $id,
        /** @var array<string, mixed> */
        public array $arguments,
    ) {}
}
