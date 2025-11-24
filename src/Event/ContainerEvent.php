<?php

declare(strict_types=1);

namespace Duyler\DI\Event;

final readonly class ContainerEvent
{
    public function __construct(
        public string $name,
        public ?string $serviceId = null,
        public mixed $service = null,
        public ?float $time = null,
    ) {}
}
