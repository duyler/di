<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Provider;

use Duyler\DependencyInjection\ContainerService;

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
}
