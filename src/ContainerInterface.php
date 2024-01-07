<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    public function bind(array $classMap): void;
    public function addProviders(array $providers): void;
    public function getClassMap(): array;
    public function set(object $definition): void;
    public function addDefinition(Definition $definition): void;
    public function softCleanUp(): void;
}
