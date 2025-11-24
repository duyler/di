<?php

declare(strict_types=1);

namespace Duyler\DI\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Bind
{
    /**
     * @param class-string $interface
     */
    public function __construct(
        public string $interface,
    ) {}
}
