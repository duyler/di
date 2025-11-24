<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

class ResolveDependenciesTreeException extends Exception implements ContainerExceptionInterface
{
    /**
     * @param array<string> $chain
     */
    public function __construct(
        string $className,
        string $reason,
        array $chain = [],
        ?Throwable $previous = null,
    ) {
        $message = sprintf('Failed to resolve dependency tree for "%s"', $className);

        if (!empty($chain)) {
            $message .= sprintf(
                "\n\nDependency chain:\n  %s\n  └─> %s (resolution failed here)",
                implode("\n  └─> ", $chain),
                $className,
            );
        }

        $message .= sprintf("\n\nReason: %s", $reason);

        $message .= "\n\nPossible solutions:";
        $message .= "\n  1. Ensure all constructor dependencies are resolvable";
        $message .= "\n  2. Check if the class exists and is autoloadable";
        $message .= "\n  3. Verify that interfaces are bound to implementations";
        $message .= "\n  4. Use \$container->compile() to check dependencies before runtime";

        parent::__construct($message, 0, $previous);
    }
}
