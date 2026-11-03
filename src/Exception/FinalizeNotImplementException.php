<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class FinalizeNotImplementException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $className, string $method)
    {
        parent::__construct('Class ' . $className . ' not implement "' . $method . '" method for Finalize attribute.');
    }
}
