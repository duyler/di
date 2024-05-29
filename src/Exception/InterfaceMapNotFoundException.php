<?php

namespace Duyler\DependencyInjection\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class InterfaceMapNotFoundException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $interfaceName, ?string $className = null)
    {
        $message = 'Interface map not found for ' . $interfaceName . ' in ' . $className ?? 'required interface';

        parent::__construct($message);
    }
}
