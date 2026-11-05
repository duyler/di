<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Scan\Attribute;

use Duyler\DI\Attribute\Bind;

#[Bind(ScanLoggerInterface::class)]
class ScanConsoleLogger implements ScanLoggerInterface {}
