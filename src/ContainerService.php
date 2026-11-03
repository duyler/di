<?php

declare(strict_types=1);

namespace Duyler\DI;

/**
 * Restricted projection of the container exposed to providers: providers
 * can only instantiate services through getInstance(). Closure factories
 * registered via factory() receive the full Container instead.
 */
class ContainerService
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function getInstance(string $class): object
    {
        /** @var object */
        return $this->container->get($class);
    }
}
