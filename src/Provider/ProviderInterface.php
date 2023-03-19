<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection\Provider;

interface ProviderInterface
{
    public function getParams(): array;
    public function bind(): array;
}
