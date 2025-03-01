<?php

declare(strict_types=1);

namespace Duyler\DI;

use Override;
use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    #[Override]
    public function get(string $id): object;

    /**
     * @param array<string, string> $classMap
     */
    public function bind(array $classMap): self;

    /**
     * @param array<string, string> $providers
     */
    public function addProviders(array $providers): self;

    /**
     * @return array<string, string>
     */
    public function getClassMap(): array;

    public function set(object $definition): self;

    public function addDefinition(Definition $definition): self;

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function getDependencyTree(): array;

    public function reset(): self;

    public function finalize(): self;

    public function addFinalizer(string $class, callable $finalizer): self;
}
