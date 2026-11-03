<?php

declare(strict_types=1);

namespace Duyler\DI\Provider;

use Duyler\DI\ContainerService;

interface ProviderInterface
{
    /** @return array<string, mixed> */
    /** @return array<string, mixed> */
    public function getArguments(ContainerService $containerService): array;

    /** @return array<string, string> */
    public function bind(): array;

    public function accept(object $definition): void;

    public function finalizer(): ?callable;

    /**
     * Returns a service for the resolved class or null if the provider abstains
     * (the service will then be created through its constructor).
     *
     * When the class is resolved as a dependency, the returned object must be
     * an instance of the resolved class, otherwise ServiceNotCreatedException
     * is thrown. When the interface is resolved directly, an incompatible
     * result is silently discarded and the bound implementation is used
     * instead (unifying these paths is a candidate for the next major
     * version).
     */
    public function factory(ContainerService $containerService): ?object;
}
