<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Scan\Attribute;

use Duyler\DI\Attribute\Finalize;

#[Finalize]
class ScanFinalizableService
{
    public function finalize(): void {}
}
