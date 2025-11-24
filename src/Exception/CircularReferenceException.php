<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class CircularReferenceException extends Exception implements ContainerExceptionInterface
{
    /**
     * @param array<string> $chain
     */
    public function __construct(string $className, string $depClassName, array $chain = [])
    {
        $message = sprintf(
            'Circular reference detected for class "%s"',
            $className,
        );

        if (!empty($chain)) {
            $fullChain = array_merge($chain, [$depClassName]);
            $message .= sprintf(
                "\n\nDependency chain:\n  %s\n  └─> %s (circular reference back to %s)",
                implode("\n  └─> ", $chain),
                $depClassName,
                $className,
            );
        } else {
            $message .= sprintf(
                ' (depends on "%s" which creates a circular dependency)',
                $depClassName,
            );
        }

        $message .= "\n\nHint: Review your constructor dependencies to break the circular reference.";

        parent::__construct($message);
    }
}
