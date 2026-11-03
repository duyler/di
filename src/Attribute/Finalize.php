<?php

declare(strict_types=1);

namespace Duyler\DI\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Finalize
{
    public function __construct(public string $method = 'finalize') {}
}
