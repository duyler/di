<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class InvalidBindingException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $interface, string $implementation, string $reason)
    {
        parent::__construct(
            sprintf(
                'Invalid binding: "%s" => "%s". %s',
                $interface,
                $implementation,
                $reason,
            ),
        );
    }
}
