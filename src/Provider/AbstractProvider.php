<?php

declare(strict_types=1);

namespace Duyler\DI\Provider;

use Duyler\DI\ContainerService;

abstract class AbstractProvider implements ProviderInterface
{
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }

    public function bind(): array
    {
        return [];
    }

    public function accept(object $definition): void {}

    public function finalizer(): ?callable
    {
        return null;
    }

    public function factory(ContainerService $containerService): ?object
    {
        return null;
    }
}
