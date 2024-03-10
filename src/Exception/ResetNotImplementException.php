<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Exception;

use Exception;

class ResetNotImplementException extends Exception
{
    public function __construct(string $className)
    {
        parent::__construct('Class ' . $className . ' not implement "reset()" method for Reset attribute.');
    }
}
