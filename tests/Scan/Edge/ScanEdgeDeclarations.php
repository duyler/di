<?php

declare(strict_types=1);

namespace Duyler\DI\Test\Scan\Edge;

interface ScanEdgeContract {}

enum ScanEdgeUnit
{
    case Single;
}

trait ScanEdgeFeature {}
