<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class InterfaceBindNotFoundException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $interfaceName)
    {
        $message = 'Class for binding with interface ' . $interfaceName . ' not found';

        parent::__construct($message);
    }
}
