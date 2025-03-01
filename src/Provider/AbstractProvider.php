<?php

declare(strict_types=1);

namespace Duyler\DI\Provider;

use Duyler\DI\ContainerService;
use Override;

abstract class AbstractProvider implements ProviderInterface
{
    #[Override]
    public function getArguments(ContainerService $containerService): array
    {
        return [];
    }

    #[Override]
    public function bind(): array
    {
        return [];
    }

    #[Override]
    public function accept(object $definition): void {}

    #[Override]
    public function finalizer(): ?callable
    {
        return null;
    }

    #[Override]
    public function factory(ContainerService $containerService): ?object
    {
        return null;
    }
}
