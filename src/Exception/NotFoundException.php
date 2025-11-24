<?php

declare(strict_types=1);

namespace Duyler\DI\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    /**
     * @param array<string> $availableServices
     * @param array<string> $chain
     */
    public function __construct(
        string $className,
        array $availableServices = [],
        array $chain = [],
    ) {
        $message = sprintf('Service "%s" not found in the container.', $className);

        if (!empty($chain)) {
            $message .= sprintf(
                "\n\nDependency chain:\n  %s\n  └─> %s (not found)",
                implode("\n  └─> ", $chain),
                $className,
            );
        }

        $suggestions = $this->findSuggestions($className, $availableServices);
        if (!empty($suggestions)) {
            $message .= "\n\nDid you mean one of these?";
            foreach ($suggestions as $suggestion) {
                $message .= "\n  - " . $suggestion;
            }
        }

        $message .= "\n\nPossible solutions:";
        $message .= "\n  1. Register the service using \$container->set('{$className}', \$instance)";
        $message .= "\n  2. Bind an interface using \$container->bind(['{$className}' => Implementation::class])";
        $message .= "\n  3. Add a service provider for '{$className}'";
        $message .= "\n  4. Use a factory: \$container->factory('{$className}', fn() => new {$className}())";

        parent::__construct($message);
    }

    /**
     * @param array<string> $availableServices
     * @return array<string>
     */
    private function findSuggestions(string $className, array $availableServices): array
    {
        if (empty($availableServices)) {
            return [];
        }

        $suggestions = [];
        $classNameLower = strtolower($className);

        foreach ($availableServices as $service) {
            $serviceLower = strtolower($service);
            $distance = levenshtein($classNameLower, $serviceLower);

            if ($distance <= 3 || str_contains($serviceLower, $classNameLower) || str_contains($classNameLower, $serviceLower)) {
                $suggestions[] = ['service' => $service, 'distance' => $distance];
            }
        }

        usort($suggestions, fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);

        return array_slice(array_column($suggestions, 'service'), 0, 3);
    }
}
