<?php

declare(strict_types=1);

namespace Duyler\DI\Provider;

use Duyler\DI\ContainerService;

interface ProviderInterface
{
    public function getArguments(ContainerService $containerService): array;

    /** @return array<string, string> */
    public function bind(): array;

    public function accept(object $definition): void;

    public function finalizer(): ?callable;

    public function factory(ContainerService $containerService): ?object;
}
