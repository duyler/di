<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    public function bind(array $classMap): self;
    public function addProviders(array $providers): self;
    public function getClassMap(): array;
    public function set(object $definition): self;
    public function addDefinition(Definition $definition): self;
    public function softCleanUp(): self;
}
