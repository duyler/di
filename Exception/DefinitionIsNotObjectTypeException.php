<?php

namespace Konveyer\DependencyInjection\Exception;

use Exception;

class DefinitionIsNotObjectTypeException extends Exception
{
    public function __construct($type)
    {
        $message = 'Defination is not object type. Type of ' . $type . ' given.';
    
        parent::__construct($message);
    }
}
