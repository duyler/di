<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    public function __construct(string $className)
    {
        $message = 'Instance of ' . $className . ' not found.';

        parent::__construct($message);
    }
}
