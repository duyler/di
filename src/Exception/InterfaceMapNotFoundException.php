<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class InterfaceMapNotFoundException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $interfaceName, string $className)
    {
        $message = 'Interface map not found for ' . $interfaceName . ' in ' . $className;

        parent::__construct($message);
    }
}
