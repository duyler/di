<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Provider;

abstract class AbstractProvider implements ProviderInterface
{
    public function getParams(): array
    {
        return [];
    }

    public function bind(): array
    {
        return [];
    }
}
