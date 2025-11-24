<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class InvalidBindingException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $interface, string $implementation, string $reason)
    {
        $message = sprintf('Invalid binding: "%s" => "%s"', $interface, $implementation);
        $message .= sprintf("\n\nReason: %s", $reason);

        $message .= "\n\nBinding requirements:";
        $message .= "\n  1. The first parameter must be an interface or abstract class";
        $message .= "\n  2. The second parameter must be a concrete class";
        $message .= "\n  3. The implementation must implement/extend the interface/abstract class";
        $message .= "\n  4. Both classes must exist and be autoloadable";

        $message .= sprintf(
            "\n\nExample:\n  \$container->bind(['%s' => ConcreteImplementation::class]);",
            $interface,
        );

        parent::__construct($message);
    }
}
