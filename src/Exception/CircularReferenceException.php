<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class CircularReferenceException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $className, string $depClassName)
    {
        $message = 'The class ' . $className . ' has a cyclic dependence on the class ' . $depClassName;

        parent::__construct($message);
    }
}
