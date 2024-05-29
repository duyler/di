<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

class ContainerService
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function getInstance(string $class): object
    {
        return $this->container->get($class);
    }
}
