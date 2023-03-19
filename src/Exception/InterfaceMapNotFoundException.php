<?php

namespace Duyler\DependencyInjection\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class InterfaceMapNotFoundException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $interfaceName)
    {
        $message = 'Interface map not found for ' . $interfaceName;
    
        parent::__construct($message);
    }
} 
 
