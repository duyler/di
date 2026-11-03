<?php

declare(strict_types=1);

namespace Duyler\DI\Event;

final readonly class ContainerEvent
{
    /**
     * @param ?float $time resolution time in seconds; for AFTER_RESOLVE and AFTER_MAKE
    */
    public function __construct(
        public string $name,
        public ?string $serviceId = null,
        public mixed $service = null,
        public ?float $time = null,
    ) {}
}
