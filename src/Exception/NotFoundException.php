<?php

namespace Duyler\DependencyInjection\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    public function __construct($className)
    {
        $message = 'Instance of ' . $className . ' not found.';
    
        parent::__construct($message);
    }
}
