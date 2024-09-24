<?php

declare(strict_types=1);

namespace Duyler\DI\Attribute;

use Attribute;

#[Attribute]
class Finalize
{
    public function __construct(public string $method = 'finalize') {}
}
