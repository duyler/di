<?php

namespace Konveyer\DependencyInjection\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class DefinitionIsNotObjectTypeException extends Exception implements ContainerExceptionInterface
{
    public function __construct($type)
    {
        $message = 'Defination is not object type. Type of ' . $type . ' given.';
    
        parent::__construct($message);
    }
}
