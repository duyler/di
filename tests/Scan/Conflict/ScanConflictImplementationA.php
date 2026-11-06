<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Scan\Conflict;

use Duyler\DI\Attribute\Bind;

#[Bind(ScanConflictInterface::class)]
class ScanConflictImplementationA implements ScanConflictInterface {}
