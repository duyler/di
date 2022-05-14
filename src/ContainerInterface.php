<?php

declare(strict_types=1);

namespace Konveyer\DependencyInjection;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    public function make(string $className, string $provider = '', bool $singleton = true): mixed;
}
