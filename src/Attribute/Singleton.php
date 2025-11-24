<?php

declare(strict_types=1);

namespace Duyler\DI\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Singleton {}
