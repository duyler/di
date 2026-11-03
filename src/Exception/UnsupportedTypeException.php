<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

final class UnsupportedTypeException extends Exception implements ContainerExceptionInterface
{
    public function __construct(
        string $className,
        string $parameterName,
        string $type,
    ) {
        parent::__construct(sprintf(
            'Parameter "%s" of "%s" has unsupported type "%s":'
            . ' union and intersection types are not supported for autowired constructor parameters',
            $parameterName,
            $className,
            $type,
        ));
    }
}
