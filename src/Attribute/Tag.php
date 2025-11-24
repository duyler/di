<?php

declare(strict_types=1);

namespace Duyler\DI\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Tag
{
    /**
     * @param string|array<string> $tags
     */
    public function __construct(
        public string|array $tags,
    ) {}
}
