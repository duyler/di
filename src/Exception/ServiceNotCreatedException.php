<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

final class ServiceNotCreatedException extends Exception implements ContainerExceptionInterface
{
    public function __construct(
        string $serviceId,
        string $reason,
        ?Throwable $previous = null,
    ) {
        $message = sprintf('Failed to create service "%s"', $serviceId);
        $message .= sprintf("\n\nReason: %s", $reason);
        parent::__construct($message, 0, $previous);
    }
}
