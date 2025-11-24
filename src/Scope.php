<?php

declare(strict_types=1);

namespace Duyler\DI;

enum Scope
{
    case Singleton;
    case Transient;
}
