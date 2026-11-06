<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ScanPathException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $path, string $reason)
    {
        $message = sprintf('Invalid scan path "%s": %s', $path, $reason);

        parent::__construct($message);
    }
}
