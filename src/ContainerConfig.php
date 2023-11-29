<?php

declare(strict_types=1);

namespace Duyler\DependencyInjection;

use Duyler\DependencyInjection\Cache\CacheHandlerInterface;

readonly class ContainerConfig
{
    public function __construct(
        public bool $enableCache = false,
        public string $fileCacheDirPath = '',
        public ?CacheHandlerInterface $cacheExternalHandler = null,
    ) {}
}
