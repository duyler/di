<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;

class FinalizeNotImplementException extends Exception
{
    public function __construct(string $className, string $method)
    {
        parent::__construct('Class ' . $className . ' not implement "' . $method . '" method for Finalize attribute.');
    }
}
