<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class DuplicateBindingException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $interface, string ...$implementations)
    {
        $message = sprintf(
            'Duplicate attribute binding for interface %s: %s',
            $interface,
            implode(', ', $implementations),
        );

        parent::__construct($message);
    }
}
