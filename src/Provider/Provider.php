<?php

declare(strict_types=1);

namespace Konveyer\DependencyInjection\Provider;

abstract class Provider implements ProviderInterface
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
