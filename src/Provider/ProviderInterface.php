<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Provider;

interface ProviderInterface
{
    public function getArguments(): array;

    public function bind(): array;

    public function accept(object $definition): void;
}
