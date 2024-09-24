<?php

declare(strict_types=1);

namespace Duyler\DI;

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
