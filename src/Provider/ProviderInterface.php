<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Provider;

use Duyler\DependencyInjection\ContainerService;

interface ProviderInterface
{
    public function getArguments(ContainerService $containerService): array;

    public function bind(): array;

    public function accept(object $definition): void;
}
